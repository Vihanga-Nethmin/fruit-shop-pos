<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\StockPosition;
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function positions(Request $request)
    {
        $cond = Availability::sellableSql('b');

        $q = StockPosition::query()
            ->select('stock_positions.*')
            ->selectRaw('stock_positions.physical_qty - stock_positions.reserved_qty AS available_qty')
            ->selectRaw(
                "CASE WHEN EXISTS (SELECT 1 FROM batches b WHERE b.id = stock_positions.batch_id AND $cond)
                      THEN stock_positions.physical_qty - stock_positions.reserved_qty
                      ELSE 0 END AS sellable_qty",
                Availability::bindings()
            )
            ->with([
                'product:id,sku,name,base_unit',
                'batch:id,source,ripeness,sell_by_date,use_by_date,unit_cost,quality_state',
                'location:id,code,name',
            ]);

        if ($request->filled('product_id')) {
            $q->where('stock_positions.product_id', $request->query('product_id'));
        }

        return response()->json(['data' => $q->paginate(50)]);
    }

    // Product ekakata total: physical, reserved, sellable, blocked
    public function availability(Request $request)
    {
        $cond = Availability::sellableSql('b');

        $q = DB::table('stock_positions as sp')
            ->join('batches as b', 'b.id', '=', 'sp.batch_id')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('sp.tenant_id', app('tenant_id'))
            ->groupBy('p.id', 'p.sku', 'p.name', 'p.base_unit')
            ->select('p.id as product_id', 'p.sku', 'p.name', 'p.base_unit')
            ->selectRaw('SUM(sp.physical_qty) AS physical_qty')
            ->selectRaw('SUM(sp.reserved_qty) AS reserved_qty')
            ->selectRaw(
                "SUM(CASE WHEN $cond THEN sp.physical_qty - sp.reserved_qty ELSE 0 END) AS sellable_qty",
                Availability::bindings()
            )
            ->selectRaw(
                "SUM(CASE WHEN $cond THEN 0 ELSE sp.physical_qty END) AS blocked_qty",
                Availability::bindings()
            );

        if ($request->filled('product_id')) {
            $q->where('p.id', $request->query('product_id'));
        }

        return response()->json(['data' => $q->get()]);
    }

    public function movements(Request $request)
    {
        $q = StockMovement::query()->orderByDesc('occurred_at');

        foreach (['product_id', 'batch_id', 'location_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }

        return response()->json(['data' => $q->paginate(50)]);
    }
}