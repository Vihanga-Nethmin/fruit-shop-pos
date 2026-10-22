<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TransferController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; 

    public function store(Request $request, StockService $stock)
    {
        $tenant = app('tenant_id');

        $data = $request->validate([
            'batch_id' => ['required', 'uuid',
                Rule::exists('batches', 'id')->where('tenant_id', $tenant)],
            'from_location_id' => ['required', 'uuid',
                Rule::exists('locations', 'id')->where('tenant_id', $tenant)],
            'to_location_id' => ['required', 'uuid', 'different:from_location_id',
                Rule::exists('locations', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'reason'   => ['nullable', 'string', 'max:255'],
        ]);

        $batch   = Batch::findOrFail($data['batch_id']);
        $product = Product::findOrFail($batch->product_id);

        if ($product->base_unit === 'EACH' && floor((float) $data['quantity']) != (float) $data['quantity']) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['quantity' => ['EACH quantities must be whole numbers.']],
            ], 422);
        }

        $qty        = number_format((float) $data['quantity'], 3, '.', '');
        $transferId = (string) Str::uuid();   // movements 2ma link karana id eka
        $actor      = $request->header('X-User-Id', self::TEMP_USER);

        try {
            $movements = DB::transaction(function () use ($stock, $batch, $data, $qty, $transferId, $actor) {
                $base = [
                    'product_id'  => $batch->product_id,
                    'batch_id'    => $batch->id,
                    'source_type' => 'transfer',
                    'source_id'   => $transferId,
                    'reason'      => $data['reason'] ?? 'Location transfer',
                    'actor_id'    => $actor,
                    'unit_cost'   => $batch->unit_cost,
                ];

                $out = $stock->post($base + [
                    'location_id' => $data['from_location_id'],
                    'qty'         => '-' . $qty,
                    'type'        => 'TRANSFER_OUT',
                ]);

                $in = $stock->post($base + [
                    'location_id' => $data['to_location_id'],
                    'qty'         => $qty,
                    'type'        => 'TRANSFER_IN',
                ]);

                return [$out, $in];
            });
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code'    => 'INSUFFICIENT_STOCK',
                    'message' => 'Not enough unreserved stock in the source location.',
                ],
            ], 409);
        }

        return response()->json([
            'data' => [
                'transfer_id' => $transferId,
                'out' => $movements[0]->fresh(),
                'in'  => $movements[1]->fresh(),
            ],
        ], 201);
    }
}