<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

abstract class InventoryTestCase extends TestCase
{
    use RefreshDatabase;

    protected const TENANT_A = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    protected const TENANT_B = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

    // migrate:fresh dev database eka wipe karanawa nisa, test database ekata witharai yanna denne
    protected function beforeRefreshingDatabase()
    {
        $db = config('database.connections.' . config('database.default') . '.database');

        if ($db !== 'inventory_test_db') {
            throw new RuntimeException("Tests must run on inventory_test_db, not '$db'. Check phpunit.xml.");
        }
    }

    protected function day(int $offset): string
    {
        return now('Asia/Colombo')->addDays($offset)->toDateString();
    }

    protected function api(string $method, string $uri, array $data = [], string $role = 'OM', string $tenant = self::TENANT_A, array $headers = []): TestResponse
    {
        return $this->json($method, '/api/v1' . $uri, $data, array_merge([
            'Accept'      => 'application/json',
            'X-Tenant-Id' => $tenant,
            'X-User-Role' => $role,
        ], $headers));
    }

    // Shop ekak: category, locations (STORE, DISP), supplier, Mango (KG, Rs. 650)
    protected function setUpShop(string $tenant = self::TENANT_A): array
    {
        $cat      = $this->api('POST', '/categories', ['name' => 'Tropical'], 'OM', $tenant)->assertCreated()->json('data.id');
        $store    = $this->api('POST', '/locations', ['code' => 'STORE', 'name' => 'Store Room'], 'OM', $tenant)->assertCreated()->json('data.id');
        $disp     = $this->api('POST', '/locations', ['code' => 'DISP', 'name' => 'Display'], 'OM', $tenant)->assertCreated()->json('data.id');
        $supplier = $this->api('POST', '/suppliers', ['supplier_code' => 'SUP-001', 'name' => 'Nimal Traders', 'phone' => '0771234567'], 'OM', $tenant)->assertCreated()->json('data.id');
        $mango    = $this->api('POST', '/products', [
            'sku' => 'MNG-KAR-A', 'name' => 'Mango', 'category_id' => $cat,
            'variety' => 'Karthakolomban', 'grade' => 'A',
            'base_unit' => 'KG', 'sale_unit' => 'KG', 'selling_price' => 650,
            'tax_rate' => 0, 'low_stock_threshold' => 10, 'reorder_target' => 50,
        ], 'OM', $tenant)->assertCreated()->json('data.id');

        return compact('cat', 'store', 'disp', 'supplier', 'mango');
    }

    // Goods receipt eka draft karala post karanawa. $line eken defaults override karanna puluwan.
    protected function receive(array $shop, array $line, string $tenant = self::TENANT_A): array
    {
        $body = [
            'supplier_id'  => $shop['supplier'],
            'receipt_date' => $this->day(0),
            'lines'        => [array_merge([
                'product_id'   => $shop['mango'],
                'location_id'  => $shop['store'],
                'ripeness'     => 'READY',
                'sell_by_date' => $this->day(10),
            ], $line)],
        ];

        $receipt = $this->api('POST', '/goods-receipts', $body, 'OM', $tenant)->assertCreated()->json('data');
        $posted  = $this->api('POST', "/goods-receipts/{$receipt['id']}/post", [], 'OM', $tenant)->assertOk()->json('data');

        return ['receipt' => $posted, 'batch_id' => $posted['lines'][0]['batch_id']];
    }

    protected function receiveKg(array $shop, float $qty, float $totalCost, array $line = []): array
    {
        return $this->receive($shop, array_merge([
            'delivered_qty' => $qty, 'accepted_qty' => $qty, 'line_cost' => $totalCost,
        ], $line));
    }

    protected function opening(array $shop, float $qty, array $extra = [], array $headers = [], string $tenant = self::TENANT_A): TestResponse
    {
        return $this->api('POST', '/inventory/opening-balances', array_merge([
            'product_id' => $shop['mango'], 'location_id' => $shop['store'],
            'quantity' => $qty, 'unit_cost' => 400, 'ripeness' => 'READY', 'sell_by_date' => $this->day(10),
        ], $extra), 'OM', $tenant, $headers);
    }

    protected function reserve(string $requestId, string $productId, float $qty): TestResponse
    {
        return $this->api('POST', '/inventory/reservations', [
            'request_id' => $requestId,
            'lines'      => [['product_id' => $productId, 'quantity' => $qty]],
        ]);
    }

    // Product ekakata physical / reserved / sellable / blocked
    protected function stock(string $productId, string $tenant = self::TENANT_A): array
    {
        $rows = $this->api('GET', '/inventory/availability', [], 'OM', $tenant)->assertOk()->json('data');

        foreach ($rows as $row) {
            if ($row['product_id'] === $productId) {
                return $row;
            }
        }

        return ['physical_qty' => '0', 'reserved_qty' => '0', 'sellable_qty' => '0', 'blocked_qty' => '0'];
    }

    protected function assertQty($expected, $actual, string $message = ''): void
    {
        $this->assertEqualsWithDelta((float) $expected, (float) $actual, 0.0005, $message);
    }

    protected function physicalSum(string $tenant = self::TENANT_A): float
    {
        return (float) DB::table('stock_positions')->where('tenant_id', $tenant)->sum('physical_qty');
    }

    protected function ledgerSum(string $tenant = self::TENANT_A): float
    {
        return (float) DB::table('stock_movements')->where('tenant_id', $tenant)->sum('signed_qty');
    }
}