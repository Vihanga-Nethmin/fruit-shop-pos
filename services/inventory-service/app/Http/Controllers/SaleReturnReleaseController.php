<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\SaleReturnReceiptLine;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleReturnReleaseController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace

    private function error(string $code, string $message, int $status = 409)
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    public function release(Request $request, string $lineId, StockService $stock)
    {
        SaleReturnReceiptLine::findOrFail($lineId); // tenant check: anith tenant ge nam 404

        $role  = $request->header('X-User-Role', 'OM'); // TEMP: gateway token eken replace (OM = owner)
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        if ($role !== 'OM') {
            return $this->error('FORBIDDEN', 'Only the owner can release returned fruit.', 403);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $outcome = DB::transaction(function () use ($lineId, $data, $stock, $actor) {
                $line = SaleReturnReceiptLine::lockForUpdate()->findOrFail($lineId);

                if ($line->released_at) {
                    return 'ALREADY';                       // retry: stock duplicate wenne nae
                }
                if (!$line->original_batch_id) {
                    return 'UNTRACEABLE';
                }

                $original = Batch::lockForUpdate()->findOrFail($line->original_batch_id);
                $return   = Batch::lockForUpdate()->findOrFail($line->batch_id);

                if ($return->quality_state !== 'QUARANTINED') {
                    return 'INVALID_STATE';
                }
                if ($original->quality_state !== 'SALEABLE') {
                    return 'ORIGINAL_BLOCKED';
                }

                $qty  = number_format((float) $line->quantity, 3, '.', '');
                $base = [
                    'product_id'     => $line->product_id,
                    'location_id'    => $line->location_id,
                    'source_type'    => 'return_release',
                    'source_id'      => $line->id,
                    'source_line_id' => $line->id,
                    'reason'         => $data['reason'],
                    'actor_id'       => $actor,
                    'approved_by'    => $actor,
                ];

                $stock->post($base + [
                    'batch_id'  => $return->id,
                    'qty'       => '-' . $qty,
                    'type'      => 'TRANSFER_OUT',
                    'unit_cost' => $return->unit_cost,
                ]);

                $stock->post($base + [
                    'batch_id'  => $original->id,
                    'qty'       => $qty,
                    'type'      => 'TRANSFER_IN',
                    'unit_cost' => $original->unit_cost,
                ]);

                $line->update([
                    'released_at'    => now(),
                    'released_by'    => $actor,
                    'release_reason' => $data['reason'],
                ]);

                return 'RELEASED';
            });
        } catch (DomainException $e) {
            return $this->error('INSUFFICIENT_STOCK', 'Returned stock is no longer available to release.');
        }

        return match ($outcome) {
            'RELEASED', 'ALREADY' => response()->json(['data' => SaleReturnReceiptLine::findOrFail($lineId)]),
            'UNTRACEABLE'      => $this->error('UNTRACEABLE_RETURN', 'Original batch is unknown. Release the return batch through batch assessment instead.'),
            'ORIGINAL_BLOCKED' => $this->error('STOCK_BLOCKED', 'The original batch is not saleable.'),
            default            => $this->error('INVALID_DOCUMENT_STATE', 'The returned batch is not in quarantine.'),
        };
    }
}