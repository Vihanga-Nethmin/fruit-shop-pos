<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\QualityAssessment;
use App\Models\SellByReview;
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SellByReviewController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace

    private function error(string $code, string $message, int $status = 409)
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    public function show(string $id)
    {
        Batch::findOrFail($id); // tenant check: anith tenant ge nam 404

        return response()->json(['data' => SellByReview::where('batch_id', $id)->first()]);
    }

    public function store(Request $request, string $id)
    {
        Batch::findOrFail($id);

        $role  = $request->header('X-User-Role', 'OM'); // TEMP: gateway token eken replace (OM = owner)
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        if ($role !== 'OM') {
            return $this->error('FORBIDDEN', 'Only the owner can review past sell-by stock.', 403);
        }

        $today = Availability::today();
        $max   = Carbon::parse($today)->addDays(14)->toDateString();

        $data = $request->validate([
            'decision'    => ['required', Rule::in(['SALE_APPROVED', 'NOT_FOR_SALE'])],
            'reason'      => ['required', 'string', 'max:255'],
            'valid_until' => [
                Rule::requiredIf($request->input('decision') === 'SALE_APPROVED'),
                'nullable', 'date_format:Y-m-d', 'after_or_equal:' . $today, 'before_or_equal:' . $max,
            ],
        ]);

        return DB::transaction(function () use ($id, $data, $actor, $today) {
            $batch = Batch::lockForUpdate()->findOrFail($id);

            if ($batch->quality_state !== 'SALEABLE') {
                return $this->error('INVALID_DOCUMENT_STATE', 'Only saleable batches can be reviewed. Use the quality assessment instead.');
            }

            $sellBy = $batch->sell_by_date?->format('Y-m-d');
            $useBy  = $batch->use_by_date?->format('Y-m-d');

            // Past use-by: blocked until disposed or returned, review eken sell karanna bari
            if ($useBy !== null && $useBy < $today) {
                return $this->error('STOCK_BLOCKED', 'Past use-by stock is blocked until it is disposed of or returned.');
            }

            if ($sellBy === null || $sellBy >= $today) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => ['batch_id' => ['This batch has not passed its sell-by date.']],
                ], 422);
            }

            $existing   = SellByReview::where('batch_id', $batch->id)->lockForUpdate()->first();
            $validUntil = $data['decision'] === 'SALE_APPROVED' ? $data['valid_until'] : null;

            $before = [
                'sell_by_review' => $existing?->decision,
                'valid_until'    => $existing?->valid_until?->format('Y-m-d'),
            ];

            $values = [
                'decision'    => $data['decision'],
                'valid_until' => $validUntil,
                'reason'      => $data['reason'],
                'decided_by'  => $actor,
                'decided_at'  => now(),
            ];

            if ($existing) {
                $existing->update($values);
                $existing->increment('version');
                $review = $existing;
            } else {
                $review = SellByReview::create($values + ['batch_id' => $batch->id]);
            }
            $batch->update(['sell_by_approved_until' => $validUntil]);
            
            // Decision eka quality history ekata (kawuruda, mokakda, reason)
            QualityAssessment::create([
                'batch_id'      => $batch->id,
                'before_values' => $before,
                'after_values'  => ['sell_by_review' => $data['decision'], 'valid_until' => $validUntil],
                'reason'        => $data['reason'],
                'notes'         => 'Past sell-by owner review',
                'actor_id'      => $actor,
                'created_at'    => now(),
            ]);

            return response()->json(
                ['data' => ['review' => $review->fresh(), 'sell_by_date' => $sellBy]],   // original sell-by date wenas wenne nae
                $existing ? 200 : 201
            );
        });
    }
}