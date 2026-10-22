<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Expire ACTIVE reservations past their expiry time and free the held stock';

    public function handle(ReservationService $svc): int
    {
        $ids = DB::table('reservations')
            ->where('status', 'ACTIVE')
            ->where('expires_at', '<', now())
            ->pluck('id');

        foreach ($ids as $id) {
            $svc->release($id, 'EXPIRED');
        }

        $this->info('Expired: ' . $ids->count());

        return self::SUCCESS;
    }
}