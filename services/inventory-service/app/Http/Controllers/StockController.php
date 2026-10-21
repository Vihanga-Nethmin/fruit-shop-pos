<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\StockPosition;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function positions(Request $request)
    {
        $q = StockPosition::query()
            ->select('stock_positions.*')
            ->selectRaw('stock_positions.physical_qty - stock_positions.reserved_qty AS available_qty')
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