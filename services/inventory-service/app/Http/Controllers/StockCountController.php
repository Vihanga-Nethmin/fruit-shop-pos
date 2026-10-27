<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockPosition;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockCountController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace

    private function milli($v): int
    {
        return (int) round(((float) $v) * 1000);
    }

    private function fromMilli(int $m): string
    {
        return number_format($m / 1000, 3, '.', '');
    }

    private function error(string $code, string $message, int $status = 409, $details = null)
    {
        $body = ['error' => ['code' => $code, 'message' => $message]];
        if ($details) {
            $body['error']['details'] = $details;
        }
        return response()->json($body, $status);
    }

    private function present(string $id)
    {
        return StockCount::with(['lines' => function ($q) {
            $q->select('stock_count_lines.*')
              ->selectRaw('ROUND(variance * unit_cost, 2) AS variance_value')
              ->with([
                  'product:id,sku,name,base_unit',
                  'location:id,code,name',
                  'batch:id,source,ripeness,sell_by_date,use_by_date',
              ]);
        }])->findOrFail($id);
    }

    public function index(Request $request)
    {
        $q = StockCount::orderByDesc('created_at');

        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }

        return response()->json(['data' => $q->paginate(25)]);
    }

    public function show(string $id)
    {
        return response()->json(['data' => $this->present($id)]);
    }

    // Count patan gannawa: scope eke positions lock wenawa, snapshot save wenawa
    public function store(Request $request)
    {
        $tenant = app('tenant_id');

        $data = $request->validate([
            'location_id' => ['nullable', 'uuid',
                Rule::exists('locations', 'id')->where('tenant_id', $tenant)],
            'product_id' => ['nullable', 'uuid',
                Rule::exists('products', 'id')->where('tenant_id', $tenant)],
            'notes' => ['nullable', 'string'],
        ]);

        if (empty($data['location_id']) && empty($data['product_id'])) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['scope' => ['Give a location and/or a product to count.']],
            ], 422);
        }

        $actor = $request->header('X-User-Id', self::TEMP_USER);

        $result = DB::transaction(function () use ($data, $actor) {
            $positions = StockPosition::query()
                ->where('physical_qty', '>', 0)
                ->when(!empty($data['location_id']), fn ($q) => $q->where('location_id', $data['location_id']))
                ->when(!empty($data['product_id']), fn ($q) => $q->where('product_id', $data['product_id']))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($positions->isEmpty()) {
                return ['http' => 422, 'body' => [
                    'message' => 'The given data was invalid.',
                    'errors'  => ['scope' => ['No stock found in this scope.']],
                ]];
            }

            $reserved = $positions->filter(fn ($p) => $this->milli($p->reserved_qty) > 0);
            if ($reserved->isNotEmpty()) {
                return ['http' => 409, 'body' => ['error' => [
                    'code'    => 'POSITIONS_RESERVED',
                    'message' => 'Some positions have active reservations. Wait until they are released or completed.',
                    'details' => $reserved->map(fn ($p) => [
                        'batch_id'     => $p->batch_id,
                        'location_id'  => $p->location_id,
                        'reserved_qty' => (string) $p->reserved_qty,
                    ])->values(),
                ]]];
            }

            if ($positions->contains(fn ($p) => !empty($p->locked_by_count_id))) {
                return ['http' => 409, 'body' => ['error' => [
                    'code'    => 'STOCK_COUNT_LOCKED',
                    'message' => 'Some positions are already locked by another stock count.',
                ]]];
            }

            $count = StockCount::create([
                'location_id' => $data['location_id'] ?? null,
                'product_id'  => $data['product_id'] ?? null,
                'notes'       => $data['notes'] ?? null,
                'status'      => 'OPEN',
                'opened_by'   => $actor,
            ]);

            $batches = Batch::whereIn('id', $positions->pluck('batch_id')->unique())->get()->keyBy('id');

            foreach ($positions as $p) {
                StockCountLine::create([
                    'stock_count_id' => $count->id,
                    'product_id'     => $p->product_id,
                    'batch_id'       => $p->batch_id,
                    'location_id'    => $p->location_id,
                    'snapshot_qty'   => $p->physical_qty,
                    'unit_cost'      => $batches[$p->batch_id]->unit_cost,
                ]);
            }

            StockPosition::whereIn('id', $positions->pluck('id'))->update(['locked_by_count_id' => $count->id]);

            return ['id' => $count->id];
        });

        if (isset($result['http'])) {
            return response()->json($result['body'], $result['http']);
        }

        return response()->json(['data' => $this->present($result['id'])], 201);
    }

    // Counted quantities enter karanawa (count eka OPEN nam witharai)
    public function enter(Request $request, string $id)
    {
        StockCount::findOrFail($id);

        $data = $request->validate([
            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.line_id'      => ['required', 'uuid'],
            'lines.*.counted_qty'  => ['required', 'numeric', 'min:0', 'decimal:0,3'],
        ]);

        $actor = $request->header('X-User-Id', self::TEMP_USER);

        return DB::transaction(function () use ($id, $data, $actor) {
            $count = StockCount::lockForUpdate()->findOrFail($id);

            if ($count->status !== 'OPEN') {
                return $this->error('INVALID_DOCUMENT_STATE', "Stock count is {$count->status}.");
            }

            $errors  = [];
            $targets = [];

            foreach ($data['lines'] as $i => $entry) {
                $line = StockCountLine::where('stock_count_id', $count->id)->find($entry['line_id']);

                if (!$line) {
                    $errors["lines.$i.line_id"][] = 'Line does not belong to this count.';
                    continue;
                }

                $product = Product::findOrFail($line->product_id);
                if ($product->base_unit === 'EACH' && $this->milli($entry['counted_qty']) % 1000 !== 0) {
                    $errors["lines.$i.counted_qty"][] = 'EACH quantities must be whole numbers.';
                    continue;
                }

                $targets[] = [$line, $entry['counted_qty']];
            }

            if ($errors) {
                return response()->json(['message' => 'The given data was invalid.', 'errors' => $errors], 422);
            }

            foreach ($targets as [$line, $qty]) {
                $line->update([
                    'counted_qty' => $qty,
                    'counted_by'  => $actor,
                    'counted_at'  => now(),
                ]);
            }

            return response()->json(['data' => $this->present($id)]);
        });
    }

    // Submit: variance = counted - snapshot. Lock eka thawama thiyenawa.
    public function submit(Request $request, string $id)
    {
        StockCount::findOrFail($id);

        $actor = $request->header('X-User-Id', self::TEMP_USER);

        return DB::transaction(function () use ($id, $actor) {
            $count = StockCount::lockForUpdate()->findOrFail($id);

            if ($count->status === 'SUBMITTED') {
                return response()->json(['data' => $this->present($id)]);   // retry
            }
            if ($count->status !== 'OPEN') {
                return $this->error('INVALID_DOCUMENT_STATE', "Stock count is {$count->status}.");
            }

            $lines   = StockCountLine::where('stock_count_id', $count->id)->get();
            $missing = $lines->filter(fn ($l) => $l->counted_qty === null);

            if ($missing->isNotEmpty()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => ['lines' => [$missing->count() . ' line(s) still have no counted quantity.']],
                ], 422);
            }

            foreach ($lines as $l) {
                $l->update(['variance' => $this->fromMilli($this->milli($l->counted_qty) - $this->milli($l->snapshot_qty))]);
            }

            $count->update(['status' => 'SUBMITTED', 'submitted_by' => $actor, 'submitted_at' => now()]);
            $count->increment('version');

            return response()->json(['data' => $this->present($id)]);
        });
    }

    // Owner approve: adjustments post wenawa + unlock, ekama transaction ekak, idempotent
    public function approve(Request $request, string $id, StockService $stock)
    {
        StockCount::findOrFail($id);

        $role  = $request->header('X-User-Role', 'OM'); // TEMP: gateway token eken replace (OM = owner)
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        if ($role !== 'OM') {
            return $this->error('FORBIDDEN', 'Only the owner can approve a stock count.', 403);
        }

        $data = $request->validate(['review_notes' => ['nullable', 'string', 'max:255']]);

        try {
            $outcome = DB::transaction(function () use ($id, $stock, $actor, $data) {
                $count = StockCount::lockForUpdate()->findOrFail($id);

                if ($count->status === 'POSTED') {
                    return 'ALREADY';                       // retry: adjustment duplicate wenne nae
                }
                if ($count->status !== 'SUBMITTED') {
                    return $count->status;
                }

                $lines = StockCountLine::where('stock_count_id', $count->id)->orderBy('id')->get();

                foreach ($lines as $line) {
                    $variance = $this->milli($line->variance);

                    if ($variance === 0) {
                        continue;                           // variance nathi position walata adjustment nae
                    }

                    $movement = $stock->post([
                        'product_id'     => $line->product_id,
                        'batch_id'       => $line->batch_id,
                        'location_id'    => $line->location_id,
                        'qty'            => $this->fromMilli($variance),   // signed: shortage nam '-'
                        'type'           => 'COUNT_ADJUSTMENT',
                        'source_type'    => 'stock_count',
                        'source_id'      => $count->id,
                        'source_line_id' => $line->id,
                        'reason'         => 'Stock count adjustment',
                        'actor_id'       => $count->submitted_by ?? $actor,
                        'approved_by'    => $actor,
                        'unit_cost'      => $line->unit_cost,
                        'count_id'       => $count->id,                    // lock eka bypass
                    ]);

                    $line->update(['movement_id' => $movement->id]);
                }

                StockPosition::where('locked_by_count_id', $count->id)->update(['locked_by_count_id' => null]);

                $count->update([
                    'status'       => 'POSTED',
                    'decided_by'   => $actor,
                    'decided_at'   => now(),
                    'review_notes' => $data['review_notes'] ?? null,
                ]);
                $count->increment('version');

                return 'POSTED';
            });
        } catch (DomainException $e) {
            return $this->error('INSUFFICIENT_STOCK', 'Stock changed during the count. Cancel the count and recount.');
        }

        return match ($outcome) {
            'POSTED', 'ALREADY' => response()->json(['data' => $this->present($id)]),
            default             => $this->error('INVALID_DOCUMENT_STATE', "Stock count is $outcome."),
        };
    }

    // Cancel: lock eka ain wenawa, stock wenas wenne nae
    public function cancel(Request $request, string $id)
    {
        StockCount::findOrFail($id);

        $role  = $request->header('X-User-Role', 'OM');
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        return DB::transaction(function () use ($id, $role, $actor) {
            $count = StockCount::lockForUpdate()->findOrFail($id);

            // Owner anytime, count eka patan gaththa kenata OPEN nam witharai
            if ($role !== 'OM' && !($count->status === 'OPEN' && $count->opened_by === $actor)) {
                return $this->error('FORBIDDEN', 'Only the owner can cancel this stock count.', 403);
            }
            if ($count->status === 'CANCELLED') {
                return response()->json(['data' => $this->present($id)]);   // retry
            }
            if (!in_array($count->status, ['OPEN', 'SUBMITTED'], true)) {
                return $this->error('INVALID_DOCUMENT_STATE', "Stock count is {$count->status}.");
            }

            StockPosition::where('locked_by_count_id', $count->id)->update(['locked_by_count_id' => null]);

            $count->update(['status' => 'CANCELLED', 'decided_by' => $actor, 'decided_at' => now()]);
            $count->increment('version');

            return response()->json(['data' => $this->present($id)]);
        });
    }
}