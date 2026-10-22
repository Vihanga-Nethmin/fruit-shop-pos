<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\Availability;
use App\Services\ReservationService;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservationActionController extends Controller
{
    private const TEMP_USER = '00000000-0000-0000-0000-000000000000'; // TEMP: gateway token eken replace

    private function present(string $id)
    {
        return Reservation::with('lines')->findOrFail($id);
    }

    private function error(string $code, string $message, int $status = 409)
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    public function commit(Request $request, string $id, StockService $stock, ReservationService $svc)
    {
        $actor = $request->header('X-User-Id', self::TEMP_USER);

        Reservation::findOrFail($id); // tenant check: anith tenant ge nam 404

        try {
            $outcome = DB::transaction(function () use ($id, $stock, $svc, $actor) {
                // Row lock: commit saha expiry ekama row ekata compete wenawa, ekak witharai win
                $r = Reservation::lockForUpdate()->findOrFail($id);

                if ($r->status === 'COMMITTED') {
                    return 'ALREADY';                       // retry: stock duplicate wenne nae
                }
                if ($r->status !== 'ACTIVE') {
                    return $r->status;
                }

                if ($r->expires_at->isPast()) {
                    $svc->release($r->id, 'EXPIRED');
                    return 'EXPIRED';
                }

                // Commit welawe quality / dates ayeth check (SRS 19.1)
                $blocked = DB::selectOne(
                    'SELECT COUNT(*) AS n
                       FROM reservation_lines l
                       JOIN batches b ON b.id = l.batch_id
                      WHERE l.reservation_id = ? AND NOT (' . Availability::sellableSql('b') . ')',
                    array_merge([$r->id], Availability::bindings())
                )->n;

                if ($blocked > 0) {
                    return 'BLOCKED';
                }

                $lines = DB::table('reservation_lines')->where('reservation_id', $r->id)->get();

                foreach ($lines as $l) {
                    // Reserved palamuwa ain karanawa, ita passe physical adu karanawa
                    $affected = DB::update(
                        'UPDATE stock_positions
                            SET reserved_qty = reserved_qty - ?, updated_at = ?
                          WHERE tenant_id = ? AND batch_id = ? AND location_id = ? AND reserved_qty >= ?',
                        [$l->qty, now()->toDateTimeString(), $r->tenant_id, $l->batch_id, $l->location_id, $l->qty]
                    );

                    if ($affected === 0) {
                        throw new RuntimeException('Reservation does not match stock position.');
                    }

                    $stock->post([
                        'product_id'     => $l->product_id,
                        'batch_id'       => $l->batch_id,
                        'location_id'    => $l->location_id,
                        'qty'            => '-' . $l->qty,
                        'type'           => 'SALE',
                        'source_type'    => 'reservation',
                        'source_id'      => $r->id,
                        'source_line_id' => $l->id,
                        'reason'         => 'Checkout commit',
                        'actor_id'       => $actor,
                        'unit_cost'      => $l->unit_cost,      // batch cost snapshot
                    ]);
                }

                $r->update(['status' => 'COMMITTED', 'committed_at' => now()]);
                $r->increment('version');

                return 'COMMITTED';
            });
        } catch (DomainException $e) {
            return $this->error('INSUFFICIENT_STOCK', 'Stock could not be committed.');
        }

        return match ($outcome) {
            'COMMITTED', 'ALREADY' => response()->json(['data' => $this->present($id)]),
            'EXPIRED'  => $this->error('RESERVATION_EXPIRED', 'Reservation expired. Create a fresh reservation.'),
            'BLOCKED'  => $this->error('STOCK_BLOCKED', 'Reserved stock is no longer sellable (quality or date change).'),
            default    => $this->error('INVALID_DOCUMENT_STATE', "Reservation is $outcome."),
        };
    }

    public function release(string $id, ReservationService $svc)
    {
        Reservation::findOrFail($id); // tenant check

        $status = $svc->release($id, 'RELEASED');

        if ($status === 'COMMITTED') {
            return $this->error('INVALID_DOCUMENT_STATE', 'Reservation is already committed.');
        }

        return response()->json(['data' => $this->present($id)]);
    }
}