<?php

namespace App\Http\Controllers;

use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    private function milli($v): int
    {
        return (int) round(((float) $v) * 1000);
    }

    private function cents($v): int
    {
        return (int) round(((float) $v) * 100);
    }

    private function fmt3(int $m): string
    {
        return number_format($m / 1000, 3, '.', '');
    }

    private function fmt2(int $c): string
    {
        return number_format($c / 100, 2, '.', '');
    }

    // Cost data thiyena nisa owner witharai (FR-23)
    private function denied(Request $request)
    {
        if ($request->header('X-User-Role', 'OM') !== 'OM') { // TEMP: gateway token eken replace
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => 'Only the owner can view reports.'],
            ], 403);
        }

        return null;
    }

    // CSV formula injection walata: text cell = + - @ walin patan gattoth ' ekak ekathu karanawa
    private function safe($v)
    {
        if (is_string($v) && $v !== '' && !is_numeric($v) && in_array($v[0], ['=', '+', '-', '@'], true)) {
            return "'" . $v;
        }

        return $v;
    }

    private function csv(array $rows, string $filename)
    {
        $headers = $rows ? array_keys($rows[0]) : [];

        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($v) => $this->safe($v), array_values($r)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function stock(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $data = $request->validate([
            'group_by' => ['nullable', Rule::in(['product', 'batch', 'location'])],
            'format'   => ['nullable', Rule::in(['json', 'csv'])],
        ]);

        $groupBy = $data['group_by'] ?? 'batch';
        $cond    = Availability::sellableSql('b');

        $select = match ($groupBy) {
            'product'  => 'p.sku, p.name, p.base_unit AS unit',
            'location' => 'l.code AS location_code, l.name AS location, p.sku, p.name, p.base_unit AS unit',
            default    => 'p.sku, p.name, p.base_unit AS unit, b.id AS batch_id, b.source,
                           DATE(b.received_at) AS received_date, b.sell_by_date, b.use_by_date,
                           b.quality_state, b.unit_cost',
        };

        $group = match ($groupBy) {
            'product'  => 'p.id, p.sku, p.name, p.base_unit',
            'location' => 'l.id, l.code, l.name, p.id, p.sku, p.name, p.base_unit',
            default    => 'p.id, p.sku, p.name, p.base_unit, b.id, b.source, b.received_at,
                           b.sell_by_date, b.use_by_date, b.quality_state, b.unit_cost',
        };

        $order = match ($groupBy) {
            'product'  => 'p.name',
            'location' => 'l.name, p.name',
            default    => 'p.name, b.received_at',
        };

        $sql = "SELECT $select,
                       SUM(sp.physical_qty) AS physical_qty,
                       SUM(sp.reserved_qty) AS reserved_qty,
                       SUM(CASE WHEN $cond THEN sp.physical_qty - sp.reserved_qty ELSE 0 END) AS sellable_qty,
                       SUM(CASE WHEN $cond THEN 0 ELSE sp.physical_qty END) AS blocked_qty,
                       ROUND(SUM(sp.physical_qty * b.unit_cost), 2) AS stock_value
                  FROM stock_positions sp
                  JOIN batches b   ON b.id = sp.batch_id
                  JOIN products p  ON p.id = sp.product_id
                  JOIN locations l ON l.id = sp.location_id
                 WHERE sp.tenant_id = ? AND sp.physical_qty > 0
                 GROUP BY $group
                 ORDER BY $order";

        $rows = array_map(
            fn ($r) => (array) $r,
            DB::select($sql, array_merge(Availability::bindings(), Availability::bindings(), [app('tenant_id')]))
        );

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->csv($rows, "stock-report-$groupBy.csv");
        }

        // Units wenas nisa quantity totals unit eka walin wenama (KG + EACH sum karanne nae)
        $units = [];
        $value = 0;

        foreach ($rows as $row) {
            $u = $row['unit'];
            $units[$u] ??= ['unit' => $u, 'physical' => 0, 'reserved' => 0, 'sellable' => 0, 'blocked' => 0, 'value' => 0];
            $units[$u]['physical'] += $this->milli($row['physical_qty']);
            $units[$u]['reserved'] += $this->milli($row['reserved_qty']);
            $units[$u]['sellable'] += $this->milli($row['sellable_qty']);
            $units[$u]['blocked']  += $this->milli($row['blocked_qty']);
            $units[$u]['value']    += $this->cents($row['stock_value']);
            $value                 += $this->cents($row['stock_value']);
        }

        $totals = array_values(array_map(fn ($t) => [
            'unit'         => $t['unit'],
            'physical_qty' => $this->fmt3($t['physical']),
            'reserved_qty' => $this->fmt3($t['reserved']),
            'sellable_qty' => $this->fmt3($t['sellable']),
            'blocked_qty'  => $this->fmt3($t['blocked']),
            'stock_value'  => $this->fmt2($t['value']),
        ], $units));

        return response()->json(['data' => [
            'report'         => 'stock',
            'group_by'       => $groupBy,
            'generated_at'   => now()->toIso8601String(),
            'rows'           => $rows,
            'totals_by_unit' => $totals,
            'total_value'    => $this->fmt2($value),
        ]]);
    }

    private function bucket(int $days): string
    {
        return match (true) {
            $days <= 2  => '0-2 days',
            $days <= 7  => '3-7 days',
            $days <= 14 => '8-14 days',
            default     => '15+ days',
        };
    }

    public function ageing(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $data = $request->validate([
            'format' => ['nullable', Rule::in(['json', 'csv'])],
        ]);

        $rows = DB::select(
            "SELECT p.sku, p.name, p.base_unit AS unit, b.id AS batch_id, b.source,
                    DATE(b.received_at) AS received_date,
                    DATEDIFF(?, DATE(b.received_at)) AS age_days,
                    b.quality_state,
                    SUM(sp.physical_qty) AS physical_qty,
                    ROUND(SUM(sp.physical_qty * b.unit_cost), 2) AS stock_value
               FROM stock_positions sp
               JOIN batches b  ON b.id = sp.batch_id
               JOIN products p ON p.id = sp.product_id
              WHERE sp.tenant_id = ? AND sp.physical_qty > 0
              GROUP BY p.id, p.sku, p.name, p.base_unit, b.id, b.source, b.received_at, b.quality_state
              ORDER BY age_days DESC, p.name",
            [Availability::today(), app('tenant_id')]
        );

        $out     = [];
        $summary = [];

        foreach ($rows as $r) {
            $row               = (array) $r;
            $row['age_days']   = max(0, (int) $row['age_days']);
            $row['age_bucket'] = $this->bucket($row['age_days']);
            $out[]             = $row;

            $key = $row['age_bucket'] . '|' . $row['unit'];
            $summary[$key] ??= ['age_bucket' => $row['age_bucket'], 'unit' => $row['unit'], 'qty' => 0, 'value' => 0];
            $summary[$key]['qty']   += $this->milli($row['physical_qty']);
            $summary[$key]['value'] += $this->cents($row['stock_value']);
        }

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->csv($out, 'ageing-report.csv');
        }

        $order = ['0-2 days' => 0, '3-7 days' => 1, '8-14 days' => 2, '15+ days' => 3];
        $list  = array_values($summary);
        usort($list, fn ($a, $b) => [$order[$a['age_bucket']], $a['unit']] <=> [$order[$b['age_bucket']], $b['unit']]);

        return response()->json(['data' => [
            'report'       => 'ageing',
            'generated_at' => now()->toIso8601String(),
            'rows'         => $out,
            'summary'      => array_map(fn ($s) => [
                'age_bucket'   => $s['age_bucket'],
                'unit'         => $s['unit'],
                'physical_qty' => $this->fmt3($s['qty']),
                'stock_value'  => $this->fmt2($s['value']),
            ], $list),
        ]]);
    }
}