<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:purge';

    protected $description = 'Delete idempotency keys that passed their retention period';

    public function handle(): int
    {
        $n = DB::table('idempotency_keys')->where('expires_at', '<', now())->delete();

        $this->info("Purged: $n");

        return self::SUCCESS;
    }
}