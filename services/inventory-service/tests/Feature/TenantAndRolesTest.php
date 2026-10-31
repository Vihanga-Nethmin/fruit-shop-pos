<?php

namespace Tests\Feature;

class TenantAndRolesTest extends InventoryTestCase
{
    public function test_one_shop_cannot_see_or_change_another_shops_data(): void
    {
        $a = $this->setUpShop(self::TENANT_A);
        $b = $this->setUpShop(self::TENANT_B);
        $this->opening($a, 5)->assertCreated();

        $ids = collect($this->api('GET', '/products', [], 'OM', self::TENANT_B)->assertOk()->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($b['mango']));
        $this->assertFalse($ids->contains($a['mango']));

        $this->api('GET', "/products/{$a['mango']}", [], 'OM', self::TENANT_B)->assertNotFound();
        $this->api('PATCH', "/products/{$a['mango']}", ['is_active' => false], 'OM', self::TENANT_B)->assertNotFound();

        $this->assertQty(0, $this->stock($b['mango'], self::TENANT_B)['physical_qty']);
        $this->assertSame([], $this->api('GET', '/inventory/positions', [], 'OM', self::TENANT_B)->json('data.data'));
    }

    public function test_the_same_sku_is_allowed_in_two_shops_but_not_twice_in_one(): void
    {
        $a = $this->setUpShop(self::TENANT_A);
        $this->setUpShop(self::TENANT_B);                                                   // same SKU, other shop: fine

        $this->api('POST', '/products', [
            'sku' => 'MNG-KAR-A', 'name' => 'Duplicate', 'category_id' => $a['cat'],
            'base_unit' => 'KG', 'sale_unit' => 'KG', 'selling_price' => 100,
        ])->assertStatus(422);
    }

    public function test_a_request_without_a_shop_is_rejected(): void
    {
        $this->json('GET', '/api/v1/products', [], ['Accept' => 'application/json'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'TENANT_MISSING');
    }

    public function test_a_price_change_needs_a_reason_and_keeps_history(): void
    {
        $shop = $this->setUpShop();

        $this->api('PATCH', "/products/{$shop['mango']}", ['selling_price' => 700])->assertStatus(422);
        $this->api('PATCH', "/products/{$shop['mango']}", ['selling_price' => 700, 'reason' => 'Season price'])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseCount('price_history', 2);                                     // initial price + this change
        $this->assertDatabaseHas('price_history', ['old_price' => '650.00', 'new_price' => '700.00', 'reason' => 'Season price']);
    }

    public function test_a_clerk_cannot_approve_wastage_but_the_owner_can(): void
    {
        $shop  = $this->setUpShop();
        $batch = $this->receiveKg($shop, 10, 4000)['batch_id'];

        $w = $this->api('POST', '/wastage-records', [
            'batch_id' => $batch, 'location_id' => $shop['store'], 'quantity' => 0.5, 'reason' => 'BRUISING',
        ], 'SC')->assertCreated()->json('data');

        $this->assertQty(10, $this->stock($shop['mango'])['physical_qty']);                // request alone changes nothing

        $this->api('POST', "/wastage-records/{$w['id']}/approve", [], 'SC')->assertStatus(403);
        $this->api('POST', "/wastage-records/{$w['id']}/approve", [], 'OM')->assertOk();
        $this->assertQty(9.5, $this->stock($shop['mango'])['physical_qty']);

        $this->api('POST', "/wastage-records/{$w['id']}/approve", [], 'OM')->assertOk();   // retry
        $this->assertQty(9.5, $this->stock($shop['mango'])['physical_qty']);
    }

    public function test_reports_are_for_the_owner_only(): void
    {
        $this->setUpShop();

        $this->api('GET', '/reports/stock', [], 'SC')->assertStatus(403);
        $this->api('GET', '/reports/stock', [], 'OM')->assertOk();
    }

    public function test_writes_are_audited_including_denied_ones(): void
    {
        $shop  = $this->setUpShop();
        $batch = $this->receiveKg($shop, 10, 4000)['batch_id'];

        $this->assertDatabaseHas('audit_events', ['method' => 'POST', 'entity_type' => 'products', 'outcome' => 'SUCCESS']);

        $w = $this->api('POST', '/wastage-records', [
            'batch_id' => $batch, 'location_id' => $shop['store'], 'quantity' => 1, 'reason' => 'THEFT',
        ], 'SC')->json('data');
        $this->api('POST', "/wastage-records/{$w['id']}/approve", [], 'SC')->assertStatus(403);

        $this->assertDatabaseHas('audit_events', ['actor_role' => 'SC', 'status_code' => 403, 'outcome' => 'DENIED']);
    }

    public function test_a_supplier_return_is_a_clerk_request_and_an_owner_approval(): void
    {
        $shop  = $this->setUpShop();
        $batch = $this->receiveKg($shop, 10, 4000)['batch_id'];

        $r = $this->api('POST', '/supplier-returns', [
            'batch_id' => $batch, 'location_id' => $shop['store'], 'quantity' => 2, 'reason' => 'Below agreed grade',
        ], 'SC')->assertCreated()->json('data');

        $this->assertQty(10, $this->stock($shop['mango'])['physical_qty']);
        $this->api('POST', "/supplier-returns/{$r['id']}/approve", [], 'SC')->assertStatus(403);
        $this->api('POST', "/supplier-returns/{$r['id']}/approve", [], 'OM')->assertOk();
        $this->api('POST', "/supplier-returns/{$r['id']}/approve", [], 'OM')->assertOk();  // retry: stock reduced once

        $this->assertQty(8, $this->stock($shop['mango'])['physical_qty']);
        $this->assertDatabaseCount('stock_movements', 2);                                   // receipt + one supplier return
    }
}