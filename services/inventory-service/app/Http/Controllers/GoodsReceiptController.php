<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GoodsReceiptController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace

    private function milli($v): int
    {
        return (int) round(((float) $v) * 1000);
    }

    public function index()
    {
        return response()->json([
            'data' => GoodsReceipt::orderByDesc('created_at')->paginate(25),
        ]);
    }

    public function show(string $id)
    {
        return response()->json(['data' => GoodsReceipt::with('lines')->findOrFail($id)]);
    }

    public function store(Request $request)
    {
        $tenant = app('tenant_id');

        $data = $request->validate([
            'supplier_id' => ['required', 'uuid',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'purchase_order_id' => ['nullable', 'uuid',
                Rule::exists('purchase_orders', 'id')->where('tenant_id', $tenant)],
            'delivery_reference' => ['nullable', 'string', 'max:60'],
            'receipt_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid',
                Rule::exists('products', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'lines.*.location_id' => ['required', 'uuid',
                Rule::exists('locations', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'lines.*.purchase_order_line_id' => ['nullable', 'uuid',
                Rule::exists('purchase_order_lines', 'id')->where('tenant_id', $tenant)],
            'lines.*.delivered_qty' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'lines.*.accepted_qty'  => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'lines.*.rejected_qty'  => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'lines.*.gross_weight'  => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'lines.*.tare_weight'   => ['nullable', 'numeric', 'min:0', 'decimal:0,3'],
            'lines.*.line_cost'     => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.reject_reason' => ['nullable', 'string', 'max:255'],
            'lines.*.supplier_lot_ref' => ['nullable', 'string', 'max:60'],
            'lines.*.origin'        => ['nullable', 'string', 'max:100'],
            'lines.*.ripeness'      => ['nullable', Rule::in(['UNRIPE', 'READY', 'OVERRIPE'])],
            'lines.*.sell_by_date'  => ['nullable', 'date_format:Y-m-d'],
            'lines.*.use_by_date'   => ['nullable', 'date_format:Y-m-d'],
            'lines.*.harvest_date'  => ['nullable', 'date_format:Y-m-d'],
            'lines.*.inspection_notes' => ['nullable', 'string'],
        ]);

        $errors = [];
        $po = null;

        if (!empty($data['purchase_order_id'])) {
            $po = PurchaseOrder::findOrFail($data['purchase_order_id']);
            if (!in_array($po->status, ['APPROVED', 'PARTIALLY_RECEIVED'], true)) {
                return $this->invalidState($po->status);
            }
            if ($po->supplier_id !== $data['supplier_id']) {
                $errors['supplier_id'][] = 'Supplier must match the purchase order.';
            }
        }

        foreach ($data['lines'] as $i => $line) {
            $product   = Product::findOrFail($line['product_id']);
            $delivered = $this->milli($line['delivered_qty']);
            $accepted  = $this->milli($line['accepted_qty']);
            $rejected  = $this->milli($line['rejected_qty'] ?? 0);

            if ($accepted + $rejected !== $delivered) {
                $errors["lines.$i.accepted_qty"][] = 'Accepted + rejected must equal delivered.';
            }
            if ($rejected > 0 && empty($line['reject_reason'])) {
                $errors["lines.$i.reject_reason"][] = 'A reason is required for rejected quantity.';
            }

            if ($product->base_unit === 'EACH') {
                foreach (['delivered_qty', 'accepted_qty', 'rejected_qty'] as $f) {
                    if ($this->milli($line[$f] ?? 0) % 1000 !== 0) {
                        $errors["lines.$i.$f"][] = 'EACH quantities must be whole numbers.';
                    }
                }
                if (isset($line['gross_weight']) || isset($line['tare_weight'])) {
                    $errors["lines.$i.gross_weight"][] = 'Gross/tare apply to KG items only.';
                }
            } else {
                $hasGross = isset($line['gross_weight']);
                $hasTare  = isset($line['tare_weight']);
                if ($hasGross !== $hasTare) {
                    $errors["lines.$i.gross_weight"][] = 'Gross and tare weight must be given together.';
                } elseif ($hasGross) {
                    $gross = $this->milli($line['gross_weight']);
                    $tare  = $this->milli($line['tare_weight']);
                    if ($gross < $tare) {
                        $errors["lines.$i.tare_weight"][] = 'Tare weight cannot exceed gross weight.';
                    } elseif ($gross - $tare !== $delivered) {
                        $errors["lines.$i.delivered_qty"][] = 'Delivered quantity must equal net weight (gross - tare).';
                    }
                }
            }

            if ($po) {
                $pl = isset($line['purchase_order_line_id'])
                    ? PurchaseOrderLine::where('purchase_order_id', $po->id)->find($line['purchase_order_line_id'])
                    : null;
                if (!$pl || $pl->product_id !== $line['product_id']) {
                    $errors["lines.$i.purchase_order_line_id"][] = 'Line must match a line of the purchase order.';
                }
            } elseif (isset($line['purchase_order_line_id'])) {
                $errors["lines.$i.purchase_order_line_id"][] = 'No purchase order selected.';
            }
        }

        if ($errors) {
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $errors], 422);
        }

        $receipt = DB::transaction(function () use ($data, $request) {
            $receipt = GoodsReceipt::create([
                'supplier_id'        => $data['supplier_id'],
                'purchase_order_id'  => $data['purchase_order_id'] ?? null,
                'delivery_reference' => $data['delivery_reference'] ?? null,
                'receipt_date'       => $data['receipt_date'],
                'notes'              => $data['notes'] ?? null,
                'status'             => 'DRAFT',
                'received_by'        => $request->header('X-User-Id', self::TEMP_USER),
            ]);

            foreach ($data['lines'] as $line) {
                $net = null;
                if (isset($line['gross_weight'], $line['tare_weight'])) {
                    $net = number_format(
                        ($this->milli($line['gross_weight']) - $this->milli($line['tare_weight'])) / 1000,
                        3, '.', ''
                    );
                }

                GoodsReceiptLine::create([
                    'goods_receipt_id'       => $receipt->id,
                    'product_id'             => $line['product_id'],
                    'purchase_order_line_id' => $line['purchase_order_line_id'] ?? null,
                    'location_id'            => $line['location_id'],
                    'delivered_qty'          => $line['delivered_qty'],
                    'accepted_qty'           => $line['accepted_qty'],
                    'rejected_qty'           => $line['rejected_qty'] ?? 0,
                    'gross_weight'           => $line['gross_weight'] ?? null,
                    'tare_weight'            => $line['tare_weight'] ?? null,
                    'net_weight'             => $net,
                    'line_cost'              => $line['line_cost'],
                    'reject_reason'          => $line['reject_reason'] ?? null,
                    'supplier_lot_ref'       => $line['supplier_lot_ref'] ?? null,
                    'origin'                 => $line['origin'] ?? null,
                    'ripeness'               => $line['ripeness'] ?? 'READY',
                    'sell_by_date'           => $line['sell_by_date'] ?? null,
                    'use_by_date'            => $line['use_by_date'] ?? null,
                    'harvest_date'           => $line['harvest_date'] ?? null,
                    'inspection_notes'       => $line['inspection_notes'] ?? null,
                ]);
            }

            return $receipt;
        });

        return response()->json(['data' => GoodsReceipt::with('lines')->findOrFail($receipt->id)], 201);
    }

    public function post(Request $request, string $id, StockService $stock)
    {
        $actor = $request->header('X-User-Id', self::TEMP_USER);
        $role  = $request->header('X-User-Role', 'OM'); // TEMP: gateway token eken replace (OM = owner, SC = stock clerk)

        DB::transaction(function () use ($id, $stock, $actor, $role) {
            $receipt = GoodsReceipt::lockForUpdate()->findOrFail($id);

            if ($receipt->status === 'POSTED') {
                return; 
            }
            if ($receipt->status !== 'DRAFT') {
                abort($this->invalidState($receipt->status));
            }

            $lines     = GoodsReceiptLine::where('goods_receipt_id', $receipt->id)->get();
            $ownerOnly = $receipt->purchase_order_id === null;   // direct receipt = owner witharai
            $po        = null;
            $poLines   = [];

            if ($receipt->purchase_order_id) {
                $po = PurchaseOrder::lockForUpdate()->findOrFail($receipt->purchase_order_id);
                if (!in_array($po->status, ['APPROVED', 'PARTIALLY_RECEIVED'], true)) {
                    abort($this->invalidState($po->status));
                }
                foreach ($lines as $l) {
                    $pl = PurchaseOrderLine::lockForUpdate()->findOrFail($l->purchase_order_line_id);
                    $outstanding = $this->milli($pl->ordered_qty) - $this->milli($pl->received_qty) - $this->milli($pl->cancelled_qty);
                    if ($this->milli($l->accepted_qty) > $outstanding) {
                        $ownerOnly = true;                       // excess receipt = owner witharai
                    }
                    $poLines[$l->id] = $pl;
                }
            }

            if ($ownerOnly && $role !== 'OM') {
                abort(response()->json([
                    'error' => [
                        'code'    => 'FORBIDDEN',
                        'message' => 'Owner authorization required for direct or excess receipts.',
                    ],
                ], 403));
            }

            foreach ($lines as $l) {
                $batchId = null;

                if ($this->milli($l->accepted_qty) > 0) {
                    $unitCost = DB::selectOne(
                        'SELECT ROUND(CAST(? AS DECIMAL(14,2)) / CAST(? AS DECIMAL(14,3)), 6) AS c',
                        [$l->line_cost, $l->accepted_qty]
                    )->c;

                    $batch = Batch::create([
                        'product_id'       => $l->product_id,
                        'supplier_id'      => $receipt->supplier_id,
                        'receipt_line_id'  => $l->id,
                        'source'           => 'RECEIPT',
                        'received_at'      => now(),
                        'supplier_lot_ref' => $l->supplier_lot_ref,
                        'accepted_qty'     => $l->accepted_qty,
                        'unit_cost'        => $unitCost,
                        'origin'           => $l->origin,
                        'inspection_notes' => $l->inspection_notes,
                        'harvest_date'     => $l->harvest_date,
                        'ripeness'         => $l->ripeness,
                        'sell_by_date'     => $l->sell_by_date,
                        'use_by_date'      => $l->use_by_date,
                        'quality_state'    => 'SALEABLE',
                    ]);

                    $stock->post([
                        'product_id'     => $l->product_id,
                        'batch_id'       => $batch->id,
                        'location_id'    => $l->location_id,
                        'qty'            => $l->accepted_qty,          // accepted witharai stock ekata
                        'type'           => 'RECEIPT',
                        'source_type'    => 'goods_receipt',
                        'source_id'      => $receipt->id,
                        'source_line_id' => $l->id,
                        'reason'         => 'Goods receipt',
                        'actor_id'       => $actor,
                        'unit_cost'      => $unitCost,
                    ]);

                    $batchId = $batch->id;
                }

                $l->update(['batch_id' => $batchId]);

                if (isset($poLines[$l->id])) {
                    DB::update(
                        'UPDATE purchase_order_lines
                            SET received_qty = received_qty + ?, updated_at = ?
                          WHERE id = ? AND tenant_id = ?',
                        [$l->accepted_qty, now()->toDateTimeString(), $l->purchase_order_line_id, app('tenant_id')]
                    );
                }
            }

            if ($po) {
                $open = DB::selectOne(
                    'SELECT COUNT(*) AS n FROM purchase_order_lines
                      WHERE purchase_order_id = ? AND tenant_id = ?
                        AND ordered_qty - received_qty - cancelled_qty > 0',
                    [$po->id, app('tenant_id')]
                )->n;

                $po->update(['status' => $open > 0 ? 'PARTIALLY_RECEIVED' : 'RECEIVED']);
                $po->increment('version');
            }

            $receipt->update([
                'status'    => 'POSTED',
                'posted_by' => $actor,
                'posted_at' => now(),
            ]);
            $receipt->increment('version');
        });

        return response()->json(['data' => GoodsReceipt::with('lines')->findOrFail($id)]);
    }

    private function invalidState(string $current)
    {
        return response()->json([
            'error' => [
                'code'    => 'INVALID_DOCUMENT_STATE',
                'message' => "Action not allowed while document is $current.",
            ],
        ], 409);
    }
}