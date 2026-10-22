<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OpeningStockController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; 

    public function store(Request $request, StockService $stock)
    {
        $tenant = app('tenant_id');

        $data = $request->validate([
            'product_id' => ['required', 'uuid',
                Rule::exists('products', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'location_id' => ['required', 'uuid',
                Rule::exists('locations', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'quantity'  => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
            'ripeness'  => ['required', Rule::in(['UNRIPE', 'READY', 'OVERRIPE'])],
            'sell_by_date' => ['nullable', 'date_format:Y-m-d'],
            'use_by_date'  => ['nullable', 'date_format:Y-m-d'],
            'harvest_date' => ['nullable', 'date_format:Y-m-d'],
            'supplier_id'  => ['nullable', 'uuid',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenant)],
            'origin' => ['nullable', 'string', 'max:100'],
            'notes'  => ['nullable', 'string'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        if ($product->base_unit === 'EACH' && floor((float) $data['quantity']) != (float) $data['quantity']) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['quantity' => ['EACH quantities must be whole numbers.']],
            ], 422);
        }

        $actor = $request->header('X-User-Id', self::TEMP_USER);

        $result = DB::transaction(function () use ($data, $stock, $actor) {
            $batch = Batch::create([
                'product_id'       => $data['product_id'],
                'supplier_id'      => $data['supplier_id'] ?? null,  
                'source'           => 'OPENING',
                'received_at'      => now(),
                'accepted_qty'     => $data['quantity'],
                'unit_cost'        => $data['unit_cost'],
                'origin'           => $data['origin'] ?? null,
                'inspection_notes' => $data['notes'] ?? null,
                'harvest_date'     => $data['harvest_date'] ?? null,
                'ripeness'         => $data['ripeness'],
                'sell_by_date'     => $data['sell_by_date'] ?? null,
                'use_by_date'      => $data['use_by_date'] ?? null,
                'quality_state'    => 'SALEABLE',
            ]);

            $movement = $stock->post([
                'product_id'  => $data['product_id'],
                'batch_id'    => $batch->id,
                'location_id' => $data['location_id'],
                'qty'         => $data['quantity'],
                'type'        => 'OPENING',
                'source_type' => 'opening',
                'source_id'   => $batch->id,
                'reason'      => 'Opening Stock',
                'actor_id'    => $actor,
                'unit_cost'   => $data['unit_cost'],
            ]);

            return [$batch, $movement];
        });

        return response()->json([
            'data' => ['batch' => $result[0]->fresh(), 'movement' => $result[1]->fresh()],
        ], 201);
    }
}