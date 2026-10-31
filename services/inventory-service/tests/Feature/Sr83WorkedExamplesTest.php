<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

class Sr83WorkedExamplesTest extends InventoryTestCase
{
    public function test_receiving_example_adds_only_accepted_quantity_at_400_per_kg(): void
    {
        $shop = $this->setUpShop();

        $r = $this->receive($shop, [
            'delivered_qty' => 20, 'accepted_qty' => 18, 'rejected_qty' => 2,
            'gross_weight' => 24, 'tare_weight' => 4,
            'line_cost' => 7200, 'reject_reason' => 'Bruised',
        ]);

        $this->assertQty(20, $r['receipt']['lines'][0]['net_weight']);          // 24 - 4

        $batch = $this->api('GET', "/batches/{$r['batch_id']}")->assertOk()->json('data.batch');
        $this->assertQty(400, $batch['unit_cost']);                              // 7200 / 18

        $this->assertQty(18, $this->stock($shop['mango'])['physical_qty']);      // rejected 2 kg is not stock
        $this->assertSame($this->physicalSum(), $this->ledgerSum());
    }

    public function test_posting_the_same_receipt_twice_adds_nothing(): void
    {
        $shop = $this->setUpShop();
        $r = $this->receiveKg($shop, 18, 7200);

        $this->api('POST', "/goods-receipts/{$r['receipt']['id']}/post")->assertOk();

        $this->assertQty(18, $this->stock($shop['mango'])['physical_qty']);
        $this->assertSame(1, DB::table('stock_movements')->where('type', 'RECEIPT')->count());
    }

    public function test_remaining_stock_example_sale_spoilage_transfer_and_count(): void
    {
        $shop  = $this->setUpShop();
        $batch = $this->receiveKg($shop, 18, 7200)['batch_id'];

        // sell 1.250 kg
        $res = $this->reserve('REQ-83-1', $shop['mango'], 1.25)->assertCreated()->json('data');
        $this->assertQty(400, $res['lines'][0]['unit_cost']);
        $this->api('POST', "/inventory/reservations/{$res['id']}/commit")->assertOk();
        $this->assertQty(16.75, $this->stock($shop['mango'])['physical_qty']);
        $this->assertDatabaseHas('stock_movements', ['type' => 'SALE', 'signed_qty' => '-1.250']);

        // spoilage 0.750 (clerk request, owner approval)
        $w = $this->api('POST', '/wastage-records', [
            'batch_id' => $batch, 'location_id' => $shop['store'], 'quantity' => 0.75, 'reason' => 'SPOILAGE',
        ], 'SC')->assertCreated()->json('data');
        $this->api('POST', "/wastage-records/{$w['id']}/approve")->assertOk()->assertJsonPath('data.status', 'POSTED');
        $this->assertQty(300, $this->api('GET', "/wastage-records/{$w['id']}")->json('data.loss_value'));   // 0.750 x 400
        $this->assertQty(16, $this->stock($shop['mango'])['physical_qty']);                                   // 18 - 1.250 - 0.750

        // move 5 kg to Display: total unchanged
        $this->api('POST', '/inventory/transfers', [
            'batch_id' => $batch, 'from_location_id' => $shop['store'],
            'to_location_id' => $shop['disp'], 'quantity' => 5,
        ])->assertCreated();
        $this->assertQty(16, $this->stock($shop['mango'])['physical_qty']);

        // count finds 15.800: approved -0.200 adjustment
        $count = $this->api('POST', '/stock-counts', ['product_id' => $shop['mango']], 'SC')->assertCreated()->json('data');
        $this->assertCount(2, $count['lines']);

        $entries = [];
        foreach ($count['lines'] as $line) {
            $counted = (float) $line['snapshot_qty'];
            if ($line['location_id'] === $shop['store']) {
                $counted -= 0.2;
            }
            $entries[] = ['line_id' => $line['id'], 'counted_qty' => round($counted, 3)];
        }

        $this->api('POST', "/stock-counts/{$count['id']}/entries", ['lines' => $entries], 'SC')->assertOk();
        $this->api('POST', "/stock-counts/{$count['id']}/submit", [], 'SC')->assertOk();
        $this->api('POST', "/stock-counts/{$count['id']}/approve", ['review_notes' => 'Dried out'])->assertOk();

        $adjustments = DB::table('stock_movements')->where('type', 'COUNT_ADJUSTMENT')->get();
        $this->assertCount(1, $adjustments);
        $this->assertQty(-0.2, $adjustments[0]->signed_qty);
        $this->assertQty(15.8, $this->stock($shop['mango'])['physical_qty']);

        // physical stock = sum of movements
        $this->assertEqualsWithDelta($this->physicalSum(), $this->ledgerSum(), 0.0005);
    }
}