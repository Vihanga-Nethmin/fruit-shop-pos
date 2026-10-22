<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservationService
{
    /**
     * ACTIVE reservation ekak release / expire karanawa (reserved_qty ain karanawa).
     * Tenant context nathuwath wada karanawa (expiry job ekata one).
     * Final status eka return karanawa. Kalin iwara nam wenasak karanne nae (idempotent).
     */
    public function release(string $reservationId, string $newStatus): string
    {
        return DB::transaction(function () use ($reservationId, $newStatus) {
            $r = DB::table('reservations')->where('id', $reservationId)->lockForUpdate()->first();

            if (!$r) {
                return 'NOT_FOUND';
            }
            if ($r->status !== 'ACTIVE') {
                return $r->status;
            }

            $lines = DB::table('reservation_lines')->where('reservation_id', $r->id)->get();

            foreach ($lines as $l) {
                $affected = DB::update(
                    'UPDATE stock_positions
                        SET reserved_qty = reserved_qty - ?, version = version + 1, updated_at = ?
                      WHERE tenant_id = ? AND batch_id = ? AND location_id = ? AND reserved_qty >= ?',
                    [$l->qty, now()->toDateTimeString(), $r->tenant_id, $l->batch_id, $l->location_id, $l->qty]
                );

                if ($affected === 0) {
                    throw new RuntimeException('Reservation does not match stock position.');
                }
            }

            DB::table('reservations')->where('id', $r->id)->update([
                'status'      => $newStatus,
                'released_at' => now(),
                'updated_at'  => now(),
                'version'     => DB::raw('version + 1'),
            ]);

            return $newStatus;
        });
    }
}