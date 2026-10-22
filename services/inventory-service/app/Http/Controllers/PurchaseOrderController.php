<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace

    private function withLines()
    {
        return PurchaseOrder::with(['lines' => function ($q) {
            $q->select('*')->selectRaw('ordered_qty - received_qty - cancelled_qty AS outstanding_qty');
        }]);
    }

    public function index()
    {
        return response()->json([
            'data' => PurchaseOrder::orderByDesc('created_at')->paginate(25),
        ]);
    }

    public function show(string $id)
    {
        return response()->json(['data' => $this->withLines()->findOrFail($id)]);
    }

    public function store(Request $request)
    {
        $tenant = app('tenant_id');

        $data = $request->validate([
            'supplier_id' => ['required', 'uuid',
                Rule::exists('suppliers', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'reference'     => ['nullable', 'string', 'max:60'],
            'order_date'    => ['required', 'date_format:Y-m-d'],
            'expected_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:order_date'],
            'notes'         => ['nullable', 'string'],
            'lines'         => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid',
                Rule::exists('products', 'id')->where('tenant_id', $tenant)->where('is_active', true)],
            'lines.*.ordered_qty'      => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'lines.*.agreed_unit_cost' => ['required', 'numeric', 'min:0', 'decimal:0,6'],
            'lines.*.container_count'  => ['nullable', 'integer', 'min:0'],
        ]);

        // EACH products walata whole number witharai
        foreach ($data['lines'] as $i => $line) {
            $product = Product::findOrFail($line['product_id']);
            if ($product->base_unit === 'EACH' && floor((float) $line['ordered_qty']) != (float) $line['ordered_qty']) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => ["lines.$i.ordered_qty" => ['EACH quantities must be whole numbers.']],
                ], 422);
            }
        }

        $po = DB::transaction(function () use ($data, $request) {
            $po = PurchaseOrder::create([
                'supplier_id'   => $data['supplier_id'],
                'reference'     => $data['reference'] ?? null,
                'order_date'    => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'notes'         => $data['notes'] ?? null,
                'status'        => 'DRAFT',
                'created_by'    => $request->header('X-User-Id', self::TEMP_USER),
            ]);

            foreach ($data['lines'] as $line) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $line['product_id'],
                    'ordered_qty'       => $line['ordered_qty'],
                    'agreed_unit_cost'  => $line['agreed_unit_cost'],
                    'container_count'   => $line['container_count'] ?? null,
                ]);
            }

            return $po;
        });

        return response()->json(['data' => $this->withLines()->findOrFail($po->id)], 201);
    }

    public function approve(Request $request, string $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        if ($po->status !== 'DRAFT') {
            return $this->invalidState($po->status);
        }

        $po->update([
            'status'      => 'APPROVED',
            'approved_by' => $request->header('X-User-Id', self::TEMP_USER),
        ]);
        $po->increment('version');

        return response()->json(['data' => $this->withLines()->findOrFail($po->id)]);
    }

    public function cancel(string $id)
    {
        $po = PurchaseOrder::findOrFail($id);

        if (!in_array($po->status, ['DRAFT', 'APPROVED', 'PARTIALLY_RECEIVED'], true)) {
            return $this->invalidState($po->status);
        }

        DB::transaction(function () use ($po) {
            DB::update(
                'UPDATE purchase_order_lines
                    SET cancelled_qty = ordered_qty - received_qty
                  WHERE purchase_order_id = ? AND tenant_id = ?',
                [$po->id, app('tenant_id')]
            );

            $received = PurchaseOrderLine::where('purchase_order_id', $po->id)->sum('received_qty');

            $po->update(['status' => ((float) $received > 0) ? 'CLOSED' : 'CANCELLED']);
            $po->increment('version');
        });

        return response()->json(['data' => $this->withLines()->findOrFail($po->id)]);
    }

    private function invalidState(string $current)
    {
        return response()->json([
            'error' => [
                'code'    => 'INVALID_DOCUMENT_STATE',
                'message' => "Action not allowed while order is $current.",
            ],
        ], 409);
    }
}