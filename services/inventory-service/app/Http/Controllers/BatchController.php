<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\QualityAssessment;
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BatchController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace
    private const TRACKED   = ['ripeness', 'quality_state', 'sell_by_date', 'use_by_date'];

    private function error(string $code, string $message, int $status)
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    private function dateOf($batch, string $field): ?string
    {
        return $batch->$field?->format('Y-m-d');
    }

    public function index(Request $request)
    {
        $q = Batch::query()->with('product:id,sku,name,base_unit')->orderByDesc('received_at');

        if ($request->filled('product_id')) {
            $q->where('product_id', $request->query('product_id'));
        }

        return response()->json(['data' => $q->paginate(50)]);
    }

    public function show(string $id)
    {
        $batch   = Batch::with('product:id,sku,name,base_unit')->findOrFail($id);
        $history = QualityAssessment::where('batch_id', $batch->id)->orderByDesc('created_at')->get();

        return response()->json(['data' => ['batch' => $batch, 'assessments' => $history]]);
    }

    public function assess(Request $request, string $id)
    {
        Batch::findOrFail($id); // tenant check: anith tenant ge nam 404

        $data = $request->validate([
            'ripeness'      => ['sometimes', Rule::in(['UNRIPE', 'READY', 'OVERRIPE'])],
            'quality_state' => ['sometimes', Rule::in(['SALEABLE', 'QUARANTINED', 'SPOILED'])],
            'sell_by_date'  => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'use_by_date'   => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'reason'        => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string'],
        ]);

        $actor = $request->header('X-User-Id', self::TEMP_USER);
        $role  = $request->header('X-User-Role', 'OM'); // TEMP: gateway token eken replace (OM = owner, SC = stock clerk)

        return DB::transaction(function () use ($id, $data, $actor, $role) {
            $batch = Batch::lockForUpdate()->findOrFail($id);

            $before = [];
            $after  = [];

            foreach (self::TRACKED as $f) {
                if (!array_key_exists($f, $data)) {
                    continue;
                }
                $current = in_array($f, ['sell_by_date', 'use_by_date'], true) ? $this->dateOf($batch, $f) : $batch->$f;
                if ($current !== $data[$f]) {
                    $before[$f] = $current;
                    $after[$f]  = $data[$f];
                }
            }

            if (!$after) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => ['changes' => ['Nothing to change.']],
                ], 422);
            }

            // Quality / date wenas karanna reason one
            $needsReason = array_diff(array_keys($after), ['ripeness']);
            if ($needsReason && empty($data['reason'])) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => ['reason' => ['A reason is required for quality or date changes.']],
                ], 422);
            }


            if (isset($after['quality_state'])) {
                if ($batch->quality_state === 'SPOILED') {
                    return $this->error('INVALID_DOCUMENT_STATE', 'A spoiled batch cannot be changed.', 409);
                }
                if ($after['quality_state'] === 'SALEABLE' && $role !== 'OM') {
                    return $this->error('FORBIDDEN', 'Only the owner can release a batch from quarantine.', 403);
                }
            }

            // Past use-by date eka override karanna bari (SRS 6.3)
            if (array_key_exists('use_by_date', $after)) {
                $currentUseBy = $this->dateOf($batch, 'use_by_date');
                if ($currentUseBy !== null && $currentUseBy < Availability::today()) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors'  => ['use_by_date' => ['A past use-by date cannot be changed.']],
                    ], 422);
                }
            }
                        // Past sell-by date eka wenas karanna bari: owner review (sell-by-review) use karanna (SRS 6.3)
            if (array_key_exists('sell_by_date', $after)) {
                $currentSellBy = $this->dateOf($batch, 'sell_by_date');
                if ($currentSellBy !== null && $currentSellBy < Availability::today()) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors'  => ['sell_by_date' => ['A past sell-by date cannot be changed. Use the owner review instead.']],
                    ], 422);
                }
            }

            $batch->update($after);
            $batch->increment('version');

            $assessment = QualityAssessment::create([
                'batch_id'      => $batch->id,
                'before_values' => $before,
                'after_values'  => $after,
                'reason'        => $data['reason'] ?? null,
                'notes'         => $data['notes'] ?? null,
                'actor_id'      => $actor,
                'created_at'    => now(),
            ]);

            return response()->json(['data' => ['batch' => $batch->fresh(), 'assessment' => $assessment]], 201);
        });
    }
}