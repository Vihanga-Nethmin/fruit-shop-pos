<?php

namespace App\Http\Controllers;

use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AlertController extends Controller
{
    private const TYPES = [
        'LOW_STOCK', 'ZERO_SELLABLE', 'NEAR_DATE', 'PAST_DATE_STOCK',
        'QUARANTINED_STOCK', 'SPOILED_STOCK', 'OVERDUE_QUALITY_REVIEW', 'UNRESOLVED_RESERVATION',
    ];

    private const SEVERITY_ORDER = ['high' => 0, 'medium' => 1, 'low' => 2];

    private function milli($v): int
    {
        return (int) round(((float) $v) * 1000);
    }

    private function qty($v): string
    {
        return number_format((float) $v, 3, '.', '');
    }

    private function unit(string $baseUnit): string
    {
        return $baseUnit === 'KG' ? 'kg' : 'items';
    }

    private function make(string $type, string $severity, string $message, array $extra = []): array
    {
        return array_merge(['type' => $type, 'severity' => $severity, 'message' => $message], $extra);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'near_days'   => ['nullable', 'integer', 'min:0', 'max:30'],
            'review_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'type'        => ['nullable', Rule::in(self::TYPES)],
        ]);

        $nearDays   = (int) ($data['near_days'] ?? 2);     
        $reviewDays = (int) ($data['review_days'] ?? 2);
        $tenant     = app('tenant_id');
        $today      = Availability::today();
        $limit      = Carbon::parse($today)->addDays($nearDays)->toDateString();

        $alerts = [];

        $cond = Availability::sellableSql('b');
        $products = DB::select(
            "SELECT p.id, p.sku, p.name, p.base_unit, p.low_stock_threshold,
                    COALESCE(SUM(CASE WHEN $cond THEN sp.physical_qty - sp.reserved_qty ELSE 0 END), 0) AS sellable_qty
               FROM products p
               LEFT JOIN stock_positions sp ON sp.product_id = p.id AND sp.tenant_id = p.tenant_id
               LEFT JOIN batches b ON b.id = sp.batch_id
              WHERE p.tenant_id = ? AND p.is_active = 1
              GROUP BY p.id, p.sku, p.name, p.base_unit, p.low_stock_threshold",
            array_merge(Availability::bindings(), [$tenant])
        );

        foreach ($products as $p) {
            $sellable  = $this->milli($p->sellable_qty);
            $threshold = $this->milli($p->low_stock_threshold);
            $u         = $this->unit($p->base_unit);
            $base      = ['product_id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'unit' => $p->base_unit];

            if ($sellable <= 0) {
                $alerts[] = $this->make('ZERO_SELLABLE', 'high',
                    "{$p->name} has no sellable stock.",
                    $base + ['quantity' => $this->qty(0)]);
            } elseif ($threshold > 0 && $sellable <= $threshold) {
                $alerts[] = $this->make('LOW_STOCK', 'medium',
                    "{$p->name}: {$this->qty($p->sellable_qty)} $u sellable, at or below the low-stock threshold ({$this->qty($p->low_stock_threshold)} $u).",
                    $base + ['quantity' => $this->qty($p->sellable_qty), 'threshold' => $this->qty($p->low_stock_threshold)]);
            }
        }

        // 3 + 4: dates (use-by / sell-by, ikman iwara wenne eka)
        $dateRows = DB::select(
            "SELECT b.id AS batch_id, b.source, p.id AS product_id, p.sku, p.name, p.base_unit,
                    SUM(sp.physical_qty) AS qty,
                    LEAST(COALESCE(b.use_by_date, b.sell_by_date), COALESCE(b.sell_by_date, b.use_by_date)) AS due,
                    CASE WHEN b.use_by_date IS NOT NULL AND (b.sell_by_date IS NULL OR b.use_by_date <= b.sell_by_date)
                         THEN 'use-by' ELSE 'sell-by' END AS date_type
               FROM batches b
               JOIN stock_positions sp ON sp.batch_id = b.id
               JOIN products p ON p.id = b.product_id
              WHERE b.tenant_id = ? AND b.quality_state = 'SALEABLE' AND sp.physical_qty > 0
                AND (b.use_by_date IS NOT NULL OR b.sell_by_date IS NOT NULL)
              GROUP BY b.id, b.source, p.id, p.sku, p.name, p.base_unit, b.use_by_date, b.sell_by_date",
            [$tenant]
        );

        foreach ($dateRows as $r) {
            $daysLeft = (int) round(Carbon::parse($today)->diffInDays(Carbon::parse($r->due), false));
            $u        = $this->unit($r->base_unit);
            $base     = [
                'product_id' => $r->product_id, 'sku' => $r->sku, 'name' => $r->name, 'unit' => $r->base_unit,
                'batch_id' => $r->batch_id, 'quantity' => $this->qty($r->qty),
                'date_type' => $r->date_type, 'date' => $r->due, 'days_left' => $daysLeft,
            ];

            if ($daysLeft < 0) {
                $alerts[] = $this->make('PAST_DATE_STOCK', 'high',
                    "{$r->name}: {$this->qty($r->qty)} $u passed the {$r->date_type} date ({$r->due}). Owner review or disposal needed.", $base);
            } elseif ($r->due <= $limit) {
                $when = $daysLeft === 0 ? 'today' : "in $daysLeft day(s)";
                $alerts[] = $this->make('NEAR_DATE', 'medium',
                    "{$r->name}: {$this->qty($r->qty)} $u reach the {$r->date_type} date $when ({$r->due}).", $base);
            }
        }

        $blocked = DB::select(
            "SELECT b.id AS batch_id, b.quality_state, p.id AS product_id, p.sku, p.name, p.base_unit,
                    SUM(sp.physical_qty) AS qty
               FROM batches b
               JOIN stock_positions sp ON sp.batch_id = b.id
               JOIN products p ON p.id = b.product_id
              WHERE b.tenant_id = ? AND b.quality_state IN ('QUARANTINED', 'SPOILED') AND sp.physical_qty > 0
              GROUP BY b.id, b.quality_state, p.id, p.sku, p.name, p.base_unit",
            [$tenant]
        );

        foreach ($blocked as $r) {
            $u    = $this->unit($r->base_unit);
            $base = [
                'product_id' => $r->product_id, 'sku' => $r->sku, 'name' => $r->name, 'unit' => $r->base_unit,
                'batch_id' => $r->batch_id, 'quantity' => $this->qty($r->qty),
            ];

            if ($r->quality_state === 'SPOILED') {
                $alerts[] = $this->make('SPOILED_STOCK', 'high',
                    "{$r->name}: {$this->qty($r->qty)} $u spoiled stock waiting for disposal.", $base);
            } else {
                $alerts[] = $this->make('QUARANTINED_STOCK', 'high',
                    "{$r->name}: {$this->qty($r->qty)} $u in quarantine waiting for a decision.", $base);
            }
        }

        $cutoff  = now()->subDays($reviewDays)->toDateTimeString();
        $overdue = DB::select(
            "SELECT b.id AS batch_id, b.source, p.id AS product_id, p.sku, p.name, p.base_unit,
                    SUM(sp.physical_qty) AS qty,
                    COALESCE((SELECT MAX(qa.created_at) FROM quality_assessments qa WHERE qa.batch_id = b.id), b.received_at) AS last_reviewed
               FROM batches b
               JOIN stock_positions sp ON sp.batch_id = b.id
               JOIN products p ON p.id = b.product_id
              WHERE b.tenant_id = ? AND b.quality_state = 'SALEABLE' AND sp.physical_qty > 0
              GROUP BY b.id, b.source, p.id, p.sku, p.name, p.base_unit, b.received_at
             HAVING last_reviewed < ?",
            [$tenant, $cutoff]
        );

        foreach ($overdue as $r) {
            $u = $this->unit($r->base_unit);
            $alerts[] = $this->make('OVERDUE_QUALITY_REVIEW', 'low',
                "{$r->name} ({$r->source} batch, {$this->qty($r->qty)} $u): no quality check since {$r->last_reviewed}.",
                [
                    'product_id' => $r->product_id, 'sku' => $r->sku, 'name' => $r->name, 'unit' => $r->base_unit,
                    'batch_id' => $r->batch_id, 'quantity' => $this->qty($r->qty), 'last_reviewed' => $r->last_reviewed,
                ]);
        }

        $stuck = DB::select(
            "SELECT id, request_id, expires_at FROM reservations
              WHERE tenant_id = ? AND status = 'ACTIVE' AND expires_at < ?",
            [$tenant, now()->toDateTimeString()]
        );

        foreach ($stuck as $r) {
            $alerts[] = $this->make('UNRESOLVED_RESERVATION', 'medium',
                "Reservation {$r->request_id} expired at {$r->expires_at} but is still holding stock.",
                ['reservation_id' => $r->id, 'request_id' => $r->request_id, 'expires_at' => $r->expires_at]);
        }

        if (!empty($data['type'])) {
            $alerts = array_values(array_filter($alerts, fn ($a) => $a['type'] === $data['type']));
        }

        usort($alerts, function ($a, $b) {
            return [self::SEVERITY_ORDER[$a['severity']], $a['type'], $a['name'] ?? '']
               <=> [self::SEVERITY_ORDER[$b['severity']], $b['type'], $b['name'] ?? ''];
        });

        $summary = [];
        foreach ($alerts as $a) {
            $summary[$a['type']] = ($summary[$a['type']] ?? 0) + 1;
        }

        return response()->json(['data' => [
            'generated_at' => now()->toIso8601String(),
            'near_days'    => $nearDays,
            'review_days'  => $reviewDays,
            'summary'      => (object) $summary,
            'alerts'       => $alerts,
        ]]);
    }
}