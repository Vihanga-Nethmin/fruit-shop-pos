<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Models\QualityAssessment;
use App\Models\StockPosition;
use App\Models\WastageRecord;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WastageController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace
    private const REASONS   = ['SPOILAGE', 'BRUISING', 'SHRINKAGE', 'HANDLING_DAMAGE', 'THEFT', 'OTHER'];

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

    // loss_value = quantity x batch cost snapshot
    private function present(string $id)
    {
        return WastageRecord::selectRaw('wastage_records.*, ROUND(quantity * unit_cost, 2) AS loss_value')
            ->findOrFail($id);
    }

    public function index(Request $request)
    {
        $q = WastageRecord::selectRaw('wastage_records.*, ROUND(quantity * unit_cost, 2) AS loss_value')
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
            'reason'   => ['required', Rule::in(self::REASONS)],
            'notes'    => [Rule::requiredIf($request->input('reason') === 'OTHER'), 'nullable', 'string'],
        ]);

        $batch    = Batch::findOrFail($data['batch_id']);
        $product  = Product::findOrFail($batch->product_id);
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

        // Reserved quantity waste karanna bari
        $unreserved = $this->milli($position->physical_qty) - $this->milli($position->reserved_qty);
        if ($this->milli($data['quantity']) > $unreserved) {
            return $this->error('INSUFFICIENT_STOCK', 'Not enough unreserved stock to write off.', 409, [
                'requested'  => number_format($this->milli($data['quantity']) / 1000, 3, '.', ''),
                'unreserved' => number_format($unreserved / 1000, 3, '.', ''),
            ]);
        }

        $wastage = WastageRecord::create([
            'batch_id'    => $batch->id,
            'product_id'  => $batch->product_id,
            'location_id' => $data['location_id'],
            'quantity'    => $data['quantity'],
            'reason'      => $data['reason'],
            'notes'       => $data['notes'] ?? null,
            'status'      => 'SUBMITTED',
            'unit_cost'   => $batch->unit_cost,
            'reported_by' => $request->header('X-User-Id', self::TEMP_USER),
        ]);

        return response()->json(['data' => $this->present($wastage->id)], 201);
    }

    // Owner approve karanawa: stock ekama transaction ekak athule adu wenawa
    public function approve(Request $request, string $id, StockService $stock)
    {
        WastageRecord::findOrFail($id); // tenant check: anith tenant ge nam 404

        $role  = $request->header('X-User-Role', 'OM'); // TEMP: gateway token eken replace (OM = owner)
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        if ($role !== 'OM') {
            return $this->error('FORBIDDEN', 'Only the owner can approve wastage.', 403);
        }

        try {
            $status = DB::transaction(function () use ($id, $stock, $actor) {
                $w = WastageRecord::lockForUpdate()->findOrFail($id);

                if ($w->status !== 'SUBMITTED') {
                    return $w->status;                        // POSTED nam retry: stock duplicate wenne nae
                }

                $qty    = number_format((float) $w->quantity, 3, '.', '');
                $before = StockPosition::where('batch_id', $w->batch_id)
                    ->where('location_id', $w->location_id)->first();

                $movement = $stock->post([
                    'product_id'     => $w->product_id,
                    'batch_id'       => $w->batch_id,
                    'location_id'    => $w->location_id,
                    'qty'            => '-' . $qty,
                    'type'           => 'WASTAGE',
                    'source_type'    => 'wastage',
                    'source_id'      => $w->id,
                    'reason'         => $w->reason,
                    'actor_id'       => $w->reported_by,
                    'approved_by'    => $actor,
                    'unit_cost'      => $w->unit_cost,        // original batch cost
                ]);

                // Spoilage = quality change + authorized removal: history ekata entry ekak
                if ($w->reason === 'SPOILAGE') {
                    $after = StockPosition::where('batch_id', $w->batch_id)
                        ->where('location_id', $w->location_id)->first();

                    QualityAssessment::create([
                        'batch_id'      => $w->batch_id,
                        'before_values' => ['physical_qty' => (string) $before->physical_qty],
                        'after_values'  => ['physical_qty' => (string) $after->physical_qty, 'written_off' => $qty],
                        'reason'        => 'Spoilage write-off',
                        'notes'         => $w->notes,
                        'actor_id'      => $actor,
                        'created_at'    => now(),
                    ]);
                }

                $w->update([
                    'status'      => 'POSTED',
                    'decided_by'  => $actor,
                    'decided_at'  => now(),
                    'movement_id' => $movement->id,
                ]);
                $w->increment('version');

                return 'POSTED';
            });
        } catch (DomainException $e) {
            return $this->error('INSUFFICIENT_STOCK', 'Stock is reserved or no longer available to write off.');
        }

        return match ($status) {
            'POSTED' => response()->json(['data' => $this->present($id)]),
            default  => $this->error('INVALID_DOCUMENT_STATE', "Wastage is $status."),
        };
    }

    public function reject(Request $request, string $id)
    {
        WastageRecord::findOrFail($id);

        $role  = $request->header('X-User-Role', 'OM');
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        if ($role !== 'OM') {
            return $this->error('FORBIDDEN', 'Only the owner can reject wastage.', 403);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return DB::transaction(function () use ($id, $data, $actor) {
            $w = WastageRecord::lockForUpdate()->findOrFail($id);

            if ($w->status !== 'SUBMITTED') {
                return $this->error('INVALID_DOCUMENT_STATE', "Wastage is {$w->status}.");
            }

            $w->update([
                'status'         => 'REJECTED',
                'decided_by'     => $actor,
                'decided_at'     => now(),
                'decision_notes' => $data['reason'],
            ]);
            $w->increment('version');

            return response()->json(['data' => $this->present($id)]);
        });
    }

    // Submit karapu kenata (ho owner ta) cancel karanna puluwan
    public function cancel(Request $request, string $id)
    {
        WastageRecord::findOrFail($id);

        $role  = $request->header('X-User-Role', 'OM');
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        return DB::transaction(function () use ($id, $role, $actor) {
            $w = WastageRecord::lockForUpdate()->findOrFail($id);

            if ($role !== 'OM' && $w->reported_by !== $actor) {
                return $this->error('FORBIDDEN', 'Only the reporter or the owner can cancel this request.', 403);
            }
            if ($w->status !== 'SUBMITTED') {
                return $this->error('INVALID_DOCUMENT_STATE', "Wastage is {$w->status}.");
            }

            $w->update(['status' => 'CANCELLED', 'decided_by' => $actor, 'decided_at' => now()]);
            $w->increment('version');

            return response()->json(['data' => $this->present($id)]);
        });
    }
}