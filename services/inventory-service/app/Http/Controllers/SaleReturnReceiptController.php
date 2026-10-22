<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Models\SaleReturnReceipt;
use App\Models\SaleReturnReceiptLine;
use App\Services\StockService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleReturnReceiptController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: service identity eken replace

    private function milli($v): int
    {
        return (int) round(((float) $v) * 1000);
    }

    private function present(string $id)
    {
        return SaleReturnReceipt::with('lines')->findOrFail($id);
    }

    public function store(Request $request, StockService $stock)
    {
        $tenant = app('tenant_id');

        $data = $request->validate([
            'request_id'     => ['required', 'string', 'max:100'],
            'sale_id'        => ['required', 'uuid'],
            'sale_return_id' => ['required', 'uuid'],
            'lines'          => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid',
                Rule::exists('products', 'id')->where('tenant_id', $tenant)],
            'lines.*.location_id' => ['required', 'uuid',
                Rule::exists('locations', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'lines.*.original_batch_id' => ['nullable', 'uuid',
                Rule::exists('batches', 'id')->where('tenant_id', $tenant)],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,6'],
            'lines.*.notes'     => ['nullable', 'string'],
        ]);

        // Idempotency: same request_id nam kalin hadapu receipt eka denawa
        $existing = SaleReturnReceipt::where('request_id', $data['request_id'])->first();
        if ($existing) {
            return response()->json(['data' => $this->present($existing->id)], 200);
        }

        $errors = [];
        $costs  = [];

        foreach ($data['lines'] as $i => $line) {
            $product = Product::findOrFail($line['product_id']);

            if ($product->base_unit === 'EACH' && $this->milli($line['quantity']) % 1000 !== 0) {
                $errors["lines.$i.quantity"][] = 'EACH quantities must be whole numbers.';
            }

            if (!empty($line['original_batch_id'])) {
                $original = Batch::findOrFail($line['original_batch_id']);
                if ($original->product_id !== $line['product_id']) {
                    $errors["lines.$i.original_batch_id"][] = 'Batch does not belong to this product.';
                }
                $costs[$i] = $original->unit_cost;           // traceable: original batch cost
            } elseif (!isset($line['unit_cost'])) {
                $errors["lines.$i.unit_cost"][] = 'Unit cost is required when the original batch is unknown.';
            } else {
                $costs[$i] = number_format((float) $line['unit_cost'], 6, '.', '');
            }
        }

        if ($errors) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $errors], 422);
        }

        $actor = $request->header('X-User-Id', self::TEMP_USER);

        try {
            $id = DB::transaction(function () use ($data, $costs, $stock, $actor) {
                $receipt = SaleReturnReceipt::create([
                    'request_id'     => $data['request_id'],
                    'sale_id'        => $data['sale_id'],
                    'sale_return_id' => $data['sale_return_id'],
                    'created_by'     => $actor,
                ]);

                foreach ($data['lines'] as $i => $line) {
                    $qty = number_format((float) $line['quantity'], 3, '.', '');

                    // Returned fruit = aluth QUARANTINED batch ekak (sellable wenne nae)
                    $batch = Batch::create([
                        'product_id'        => $line['product_id'],
                        'source'            => 'RETURN',
                        'original_batch_id' => $line['original_batch_id'] ?? null,
                        'received_at'       => now(),
                        'accepted_qty'      => $qty,
                        'unit_cost'         => $costs[$i],
                        'ripeness'          => 'READY',
                        'quality_state'     => 'QUARANTINED',
                        'inspection_notes'  => 'Customer return' . (!empty($line['notes']) ? ': ' . $line['notes'] : ''),
                    ]);

                    $receiptLine = SaleReturnReceiptLine::create([
                        'sale_return_receipt_id' => $receipt->id,
                        'product_id'             => $line['product_id'],
                        'location_id'            => $line['location_id'],
                        'quantity'               => $qty,
                        'unit_cost'              => $costs[$i],
                        'original_batch_id'      => $line['original_batch_id'] ?? null,
                        'batch_id'               => $batch->id,
                        'notes'                  => $line['notes'] ?? null,
                    ]);

                    $stock->post([
                        'product_id'     => $line['product_id'],
                        'batch_id'       => $batch->id,
                        'location_id'    => $line['location_id'],
                        'qty'            => $qty,
                        'type'           => 'SALE_RETURN',
                        'source_type'    => 'sale_return',
                        'source_id'      => $receipt->id,
                        'source_line_id' => $receiptLine->id,
                        'reason'         => 'Customer return (quarantine)',
                        'actor_id'       => $actor,
                        'unit_cost'      => $costs[$i],
                    ]);
                }

                return $receipt->id;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Same request_id ekata parallel requests: eka win, anith eka eka thamai return karanne
            $existing = SaleReturnReceipt::where('request_id', $data['request_id'])->firstOrFail();
            return response()->json(['data' => $this->present($existing->id)], 200);
        }

        return response()->json(['data' => $this->present($id)], 201);
    }

    public function showByRequest(string $requestId)
    {
        $receipt = SaleReturnReceipt::where('request_id', $requestId)->firstOrFail();

        return response()->json(['data' => $this->present($receipt->id)]);
    }
}