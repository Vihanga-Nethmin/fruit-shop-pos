<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockPosition;
use App\Models\SupplierReturn;
use App\Services\Availability;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierReturnController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace

    private function milli($v): int
    {
        return (int) round(((float) $v) * 1000);
    }

    private function error(string $code, string $message, int $status = 409, $details = null)
    {
        $body = ['error' => ['code' => $code, 'message' => $message]];
        if ($details) {
            $body['error']['details'] = $details;
        }
        return response()->json($body, $status);
    }

    // return_value = quantity x batch cost snapshot
    private function present(string $id)
    {
        return SupplierReturn::selectRaw('supplier_returns.*, ROUND(quantity * unit_cost, 2) AS return_value')
            ->findOrFail($id);
    }

    public function index(Request $request)
    {
        $q = SupplierReturn::selectRaw('supplier_returns.*, ROUND(quantity * unit_cost, 2) AS return_value')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }

        return response()->json(['data' => $q->paginate(25)]);
    }

    public function show(string $id)
    {
        return response()->json(['data' => $this->present($id)]);
    }

    // Clerk submit karanawa: stock effect nae
    public function store(Request $request)
    {
        $tenant = app('tenant_id');

        $data = $request->validate([
            'batch_id' => ['required', 'uuid',
                Rule::exists('batches', 'id')->where('tenant_id', $tenant)],
            'location_id' => ['required', 'uuid',
                Rule::exists('locations', 'id')->where('tenant_id', $tenant)],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'reason'   => ['required', 'string', 'max:255'],
            'return_date' => ['nullable', 'date_format:Y-m-d'],
            'supplier_credit_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $batch   = Batch::findOrFail($data['batch_id']);
        $product = Product::findOrFail($batch->product_id);

        if (!$batch->supplier_id) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['batch_id' => ['This batch has no supplier (opening or customer-return batch).']],
            ], 422);
        }

        $position = StockPosition::where('batch_id', $batch->id)
            ->where('location_id', $data['location_id'])->first();

        if (!$position) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['batch_id' => ['No stock of this batch in this location.']],
            ], 422);
        }

        if ($product->base_unit === 'EACH' && $this->milli($data['quantity']) % 1000 !== 0) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['quantity' => ['EACH quantities must be whole numbers.']],
            ], 422);
        }

        // Reserved quantity return karanna bari
        $unreserved = $this->milli($position->physical_qty) - $this->milli($position->reserved_qty);
        if ($this->milli($data['quantity']) > $unreserved) {
            return $this->error('INSUFFICIENT_STOCK', 'Not enough unreserved stock to return.', 409, [
                'requested'  => number_format($this->milli($data['quantity']) / 1000, 3, '.', ''),
                'unreserved' => number_format($unreserved / 1000, 3, '.', ''),
            ]);
        }

        $return = SupplierReturn::create([
            'supplier_id'     => $batch->supplier_id,
            'batch_id'        => $batch->id,
            'receipt_line_id' => $batch->receipt_line_id,
            'product_id'      => $batch->product_id,
            'location_id'     => $data['location_id'],
            'quantity'        => $data['quantity'],
            'reason'          => $data['reason'],
            'return_date'     => $data['return_date'] ?? Availability::today(),
            'supplier_credit_reference' => $data['supplier_credit_reference'] ?? null,
            'status'          => 'SUBMITTED',
            'unit_cost'       => $batch->unit_cost,
            'reported_by'     => $request->header('X-User-Id', self::TEMP_USER),
        ]);

        return response()->json(['data' => $this->present($return->id)], 201);
    }

    // Owner approve karanawa: stock ekama transaction ekak athule, exactly ekak parak adu wenawa
    public function approve(Request $request, string $id, StockService $stock)
    {
        SupplierReturn::findOrFail($id); // tenant check: anith tenant ge nam 404

        $role  = $request->header('X-User-Role', 'OM'); // TEMP: gateway token eken replace (OM = owner)
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        if ($role !== 'OM') {
            return $this->error('FORBIDDEN', 'Only the owner can approve supplier returns.', 403);
        }

        try {
            $status = DB::transaction(function () use ($id, $stock, $actor) {
                $r = SupplierReturn::lockForUpdate()->findOrFail($id);

                if ($r->status !== 'SUBMITTED') {
                    return $r->status;                       // POSTED nam retry: stock duplicate wenne nae
                }

                $qty = number_format((float) $r->quantity, 3, '.', '');

                $movement = $stock->post([
                    'product_id'     => $r->product_id,
                    'batch_id'       => $r->batch_id,
                    'location_id'    => $r->location_id,
                    'qty'            => '-' . $qty,
                    'type'           => 'SUPPLIER_RETURN',
                    'source_type'    => 'supplier_return',
                    'source_id'      => $r->id,
                    'reason'         => $r->reason,
                    'actor_id'       => $r->reported_by,
                    'approved_by'    => $actor,
                    'unit_cost'      => $r->unit_cost,       // original batch cost
                ]);

                $r->update([
                    'status'      => 'POSTED',
                    'decided_by'  => $actor,
                    'decided_at'  => now(),
                    'movement_id' => $movement->id,
                ]);
                $r->increment('version');

                return 'POSTED';
            });
        } catch (DomainException $e) {
            return $this->error('INSUFFICIENT_STOCK', 'Stock is reserved or no longer available to return.');
        }

        return match ($status) {
            'POSTED' => response()->json(['data' => $this->present($id)]),
            default  => $this->error('INVALID_DOCUMENT_STATE', "Supplier return is $status."),
        };
    }

    public function reject(Request $request, string $id)
    {
        SupplierReturn::findOrFail($id);

        $role  = $request->header('X-User-Role', 'OM');
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        if ($role !== 'OM') {
            return $this->error('FORBIDDEN', 'Only the owner can reject supplier returns.', 403);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return DB::transaction(function () use ($id, $data, $actor) {
            $r = SupplierReturn::lockForUpdate()->findOrFail($id);

            if ($r->status !== 'SUBMITTED') {
                return $this->error('INVALID_DOCUMENT_STATE', "Supplier return is {$r->status}.");
            }

            $r->update([
                'status'         => 'REJECTED',
                'decided_by'     => $actor,
                'decided_at'     => now(),
                'decision_notes' => $data['reason'],
            ]);
            $r->increment('version');

            return response()->json(['data' => $this->present($id)]);
        });
    }

    // Submit karapu kenata (ho owner ta) cancel karanna puluwan
    public function cancel(Request $request, string $id)
    {
        SupplierReturn::findOrFail($id);

        $role  = $request->header('X-User-Role', 'OM');
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        return DB::transaction(function () use ($id, $role, $actor) {
            $r = SupplierReturn::lockForUpdate()->findOrFail($id);

            if ($role !== 'OM' && $r->reported_by !== $actor) {
                return $this->error('FORBIDDEN', 'Only the reporter or the owner can cancel this request.', 403);
            }
            if ($r->status !== 'SUBMITTED') {
                return $this->error('INVALID_DOCUMENT_STATE', "Supplier return is {$r->status}.");
            }

            $r->update(['status' => 'CANCELLED', 'decided_by' => $actor, 'decided_at' => now()]);
            $r->increment('version');

            return response()->json(['data' => $this->present($id)]);
        });
    }
}