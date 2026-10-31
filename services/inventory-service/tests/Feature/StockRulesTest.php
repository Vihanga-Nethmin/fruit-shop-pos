<?php

namespace Tests\Feature;

use App\Models\StockMovement;

class StockRulesTest extends InventoryTestCase
{
    public function test_fefo_reserves_the_batch_that_expires_first(): void
    {
        $shop  = $this->setUpShop();
        $late  = $this->receiveKg($shop, 5, 2000, ['sell_by_date' => $this->day(8)]);
        $early = $this->receiveKg($shop, 5, 2000, ['sell_by_date' => $this->day(3)]);

        $res = $this->reserve('REQ-F1', $shop['mango'], 1)->assertCreated()->json('data');

        $this->assertCount(1, $res['lines']);
        $this->assertSame($early['batch_id'], $res['lines'][0]['batch_id']);
        $this->assertNotSame($late['batch_id'], $res['lines'][0]['batch_id']);
    }

    public function test_a_sale_can_take_stock_from_several_batches(): void
    {
        $shop  = $this->setUpShop();
        $late  = $this->receiveKg($shop, 5, 2000, ['sell_by_date' => $this->day(8)]);
        $early = $this->receiveKg($shop, 3, 1200, ['sell_by_date' => $this->day(3)]);

        $res = $this->reserve('REQ-F2', $shop['mango'], 5)->assertCreated()->json('data');
        $by  = collect($res['lines'])->keyBy('batch_id');

        $this->assertCount(2, $res['lines']);
        $this->assertQty(3, $by[$early['batch_id']]['qty']);
        $this->assertQty(2, $by[$late['batch_id']]['qty']);
    }

    public function test_it_is_not_possible_to_oversell(): void
    {
        $shop = $this->setUpShop();
        $this->receiveKg($shop, 2, 800);

        $this->reserve('REQ-O1', $shop['mango'], 3)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');

        $this->assertDatabaseCount('reservations', 0);                                   // nothing is held
        $this->assertQty(0, $this->stock($shop['mango'])['reserved_qty']);
    }

    public function test_reserve_release_and_commit_move_the_right_numbers(): void
    {
        $shop = $this->setUpShop();
        $this->receiveKg($shop, 5, 2000);

        $a = $this->reserve('REQ-R1', $shop['mango'], 2)->assertCreated()->json('data');
        $s = $this->stock($shop['mango']);
        $this->assertQty(5, $s['physical_qty']);                                          // reserve does not reduce physical
        $this->assertQty(2, $s['reserved_qty']);
        $this->assertQty(3, $s['sellable_qty']);

        $this->api('POST', "/inventory/reservations/{$a['id']}/release")->assertOk();
        $this->assertQty(0, $this->stock($shop['mango'])['reserved_qty']);

        $b = $this->reserve('REQ-R2', $shop['mango'], 2)->assertCreated()->json('data');
        $this->api('POST', "/inventory/reservations/{$b['id']}/commit")->assertOk();
        $s = $this->stock($shop['mango']);
        $this->assertQty(3, $s['physical_qty']);                                          // commit reduces physical
        $this->assertQty(0, $s['reserved_qty']);

        // committed one cannot be released
        $this->api('POST', "/inventory/reservations/{$b['id']}/release")->assertStatus(409);
    }

    public function test_the_same_request_id_gives_the_same_reservation(): void
    {
        $shop = $this->setUpShop();
        $this->receiveKg($shop, 5, 2000);

        $first  = $this->reserve('REQ-I1', $shop['mango'], 2)->assertCreated()->json('data.id');
        $second = $this->reserve('REQ-I1', $shop['mango'], 2)->assertOk()->json('data.id');

        $this->assertSame($first, $second);
        $this->assertQty(2, $this->stock($shop['mango'])['reserved_qty']);               // not 4
    }

    public function test_quarantined_stock_is_blocked_until_the_owner_releases_it(): void
    {
        $shop  = $this->setUpShop();
        $batch = $this->receiveKg($shop, 5, 2000)['batch_id'];

        $this->api('POST', "/batches/$batch/assessments", ['quality_state' => 'QUARANTINED', 'reason' => 'Mould'], 'SC')->assertCreated();

        $s = $this->stock($shop['mango']);
        $this->assertQty(5, $s['physical_qty']);
        $this->assertQty(0, $s['sellable_qty']);
        $this->assertQty(5, $s['blocked_qty']);
        $this->reserve('REQ-Q1', $shop['mango'], 1)->assertStatus(409);

        // clerk cannot release, owner can
        $this->api('POST', "/batches/$batch/assessments", ['quality_state' => 'SALEABLE', 'reason' => 'Fine'], 'SC')->assertStatus(403);
        $this->api('POST', "/batches/$batch/assessments", ['quality_state' => 'SALEABLE', 'reason' => 'Checked'], 'OM')->assertCreated();
        $this->assertQty(5, $this->stock($shop['mango'])['sellable_qty']);
    }

    public function test_quality_and_date_changes_need_a_reason(): void
    {
        $shop  = $this->setUpShop();
        $batch = $this->receiveKg($shop, 5, 2000)['batch_id'];

        $this->api('POST', "/batches/$batch/assessments", ['quality_state' => 'QUARANTINED'], 'SC')->assertStatus(422);
        $this->api('POST', "/batches/$batch/assessments", ['sell_by_date' => $this->day(12)], 'SC')->assertStatus(422);
    }

    public function test_past_use_by_stock_is_never_sellable(): void
    {
        $shop = $this->setUpShop();
        $this->receiveKg($shop, 5, 2000, ['use_by_date' => $this->day(-1)]);

        $s = $this->stock($shop['mango']);
        $this->assertQty(5, $s['physical_qty']);
        $this->assertQty(0, $s['sellable_qty']);
        $this->assertQty(5, $s['blocked_qty']);
    }

    public function test_past_sell_by_stock_needs_an_owner_review_before_sale(): void
    {
        $shop      = $this->setUpShop();
        $yesterday = $this->day(-1);
        $batch     = $this->receiveKg($shop, 5, 2000, ['sell_by_date' => $yesterday])['batch_id'];

        $this->assertQty(0, $this->stock($shop['mango'])['sellable_qty']);

        $body = ['decision' => 'SALE_APPROVED', 'valid_until' => $this->day(1), 'reason' => 'Checked: firm and fresh'];

        $this->api('POST', "/batches/$batch/sell-by-review", $body, 'SC')->assertStatus(403);
        $this->api('POST', "/batches/$batch/sell-by-review", $body, 'OM')
            ->assertCreated()
            ->assertJsonPath('data.sell_by_date', $yesterday);                            // original date is not changed

        $this->assertQty(5, $this->stock($shop['mango'])['sellable_qty']);

        // the date itself cannot be edited through a quality assessment
        $this->api('POST', "/batches/$batch/assessments", ['sell_by_date' => $this->day(9), 'reason' => 'extend'], 'OM')->assertStatus(422);
    }

    public function test_units_and_money_are_validated(): void
    {
        $shop = $this->setUpShop();

        $this->opening($shop, 1.2345)->assertStatus(422);                                 // KG: max 3 decimals
        $this->opening($shop, -1)->assertStatus(422);

        $apple = $this->api('POST', '/products', [
            'sku' => 'APL-1', 'name' => 'Apple', 'category_id' => $shop['cat'],
            'base_unit' => 'EACH', 'sale_unit' => 'EACH', 'selling_price' => 80,
        ])->assertCreated()->json('data.id');

        $this->opening($shop, 2.5, ['product_id' => $apple])->assertStatus(422);         // EACH: whole numbers only
        $this->opening($shop, 3, ['product_id' => $apple])->assertCreated();

        $this->api('POST', '/products', [
            'sku' => 'BAD-1', 'name' => 'Bad', 'category_id' => $shop['cat'],
            'base_unit' => 'KG', 'sale_unit' => 'KG', 'selling_price' => -5,
        ])->assertStatus(422);
    }

    public function test_the_ledger_cannot_be_edited(): void
    {
        $shop = $this->setUpShop();
        $this->opening($shop, 1)->assertCreated();

        app()->instance('tenant_id', self::TENANT_A);
        $this->expectException(\LogicException::class);

        StockMovement::first()->update(['reason' => 'edited']);
    }

    public function test_every_stock_change_writes_an_outbox_event_and_failures_write_none(): void
    {
        $shop = $this->setUpShop();
        $this->opening($shop, 5)->assertCreated();

        $this->assertDatabaseCount('outbox', 1);
        $this->assertDatabaseHas('outbox', ['event_type' => 'inventory.movement_posted', 'published_at' => null]);

        $batch = $this->api('GET', '/inventory/positions')->json('data.data.0.batch_id');

        // transfer fails (not enough stock): no movement, no event
        $this->api('POST', '/inventory/transfers', [
            'batch_id' => $batch, 'from_location_id' => $shop['store'],
            'to_location_id' => $shop['disp'], 'quantity' => 100,
        ])->assertStatus(409);
        $this->assertDatabaseCount('outbox', 1);

        // transfer works: two movements, two events
        $this->api('POST', '/inventory/transfers', [
            'batch_id' => $batch, 'from_location_id' => $shop['store'],
            'to_location_id' => $shop['disp'], 'quantity' => 1,
        ])->assertCreated();
        $this->assertDatabaseCount('outbox', 3);
    }

    public function test_alerts_show_zero_and_low_stock(): void
    {
        $shop = $this->setUpShop();                                                         // low-stock threshold = 10

        $this->api('GET', '/inventory/alerts?type=ZERO_SELLABLE')->assertOk()->assertJsonCount(1, 'data.alerts');

        $this->receiveKg($shop, 5, 2000);

        $this->api('GET', '/inventory/alerts?type=ZERO_SELLABLE')->assertOk()->assertJsonCount(0, 'data.alerts');
        $this->api('GET', '/inventory/alerts?type=LOW_STOCK')->assertOk()->assertJsonCount(1, 'data.alerts');
    }
}