<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PublishOutbox extends Command
{
    protected $signature = 'outbox:publish {--limit= : Maximum events to send in this run}';

    protected $description = 'Send pending outbox events (at-least-once; consumers dedupe by event_id)';

    public function handle(): int
    {
        $sink  = config('outbox.sink');
        $url   = config('outbox.url');
        $limit = (int) ($this->option('limit') ?: config('outbox.batch'));

        if ($sink === 'http' && !$url) {
            $this->error('OUTBOX_SINK=http but REPORTING_EVENTS_URL is not set.');
            return self::FAILURE;
        }

        $rows = DB::table('outbox')
            ->whereNull('published_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $sent   = 0;
        $failed = 0;

        foreach ($rows as $r) {
            $envelope = [
                'event_id'       => $r->id,
                'event_type'     => $r->event_type,
                'schema_version' => (int) $r->schema_version,
                'tenant_id'      => $r->tenant_id,
                'occurred_at'    => Carbon::parse($r->occurred_at, 'UTC')->toIso8601String(),
                'correlation_id' => $r->correlation_id,
                'data'           => json_decode($r->payload, true),
            ];

            try {
                if ($sink === 'http') {
                    $res = Http::timeout(5)->acceptJson()->post($url, $envelope);
                    if (!$res->successful()) {
                        throw new RuntimeException('HTTP ' . $res->status());
                    }
                } else {
                    file_put_contents(storage_path('logs/outbox-events.log'), json_encode($envelope) . PHP_EOL, FILE_APPEND | LOCK_EX);
                }

                DB::table('outbox')->where('id', $r->id)->update(['published_at' => now(), 'last_error' => null]);
                $sent++;
            } catch (Throwable $e) {
                DB::table('outbox')->where('id', $r->id)->update([
                    'attempts'   => DB::raw('attempts + 1'),
                    'last_error' => Str::limit($e->getMessage(), 250),
                ]);
                $failed++;
            }
        }

        $this->info("Published: $sent, failed: $failed");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}