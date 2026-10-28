<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReportSupport;
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationsReportController extends Controller
{
    use ReportSupport;

    // ---------- Wastage quantity and value ----------
    public function wastage(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $data  = $request->validate(['format' => ['nullable', Rule::in(['json', 'csv'])]]);
        $range = $this->range($request);

        $rows = array_map(fn ($r) => (array) $r, DB::select(
            "SELECT w.reason, p.sku, p.name, p.base_unit AS unit,
                    COUNT(*) AS records,
                    SUM(w.quantity) AS quantity,
                    ROUND(SUM(w.quantity * w.unit_cost), 2) AS loss_value
               FROM wastage_records w
               JOIN products p ON p.id = w.product_id
              WHERE w.tenant_id = ? AND w.status = 'POSTED' AND w.decided_at BETWEEN ? AND ?
              GROUP BY w.reason, p.id, p.sku, p.name, p.base_unit
              ORDER BY w.reason, p.name",
            [app('tenant_id'), $range['start'], $range['end']]
        ));

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->csv($rows, "wastage-report-{$range['from']}-to-{$range['to']}.csv");
        }

        $byUnit = [];
        $byReason = [];
        $total = 0;

        foreach ($rows as $r) {
            $u = $r['unit'];
            $byUnit[$u] ??= ['unit' => $u, 'quantity' => 0, 'value' => 0];
            $byUnit[$u]['quantity'] += $this->milli($r['quantity']);
            $byUnit[$u]['value']    += $this->cents($r['loss_value']);

            $byReason[$r['reason']] ??= ['reason' => $r['reason'], 'value' => 0];
            $byReason[$r['reason']]['value'] += $this->cents($r['loss_value']);

            $total += $this->cents($r['loss_value']);
        }

        return response()->json(['data' => [
            'report'          => 'wastage',
            'from'            => $range['from'],
            'to'              => $range['to'],
            'generated_at'    => now()->toIso8601String(),
            'rows'            => $rows,
            'totals_by_unit'  => array_values(array_map(fn ($t) => [
                'unit' => $t['unit'], 'quantity' => $this->fmt3($t['quantity']), 'loss_value' => $this->fmt2($t['value']),
            ], $byUnit)),
            'by_reason'       => array_values(array_map(fn ($t) => [
                'reason' => $t['reason'], 'loss_value' => $this->fmt2($t['value']),
            ], $byReason)),
            'total_loss_value' => $this->fmt2($total),
        ]]);
    }

    // ---------- Count variance ----------
    public function countVariance(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $data  = $request->validate(['format' => ['nullable', Rule::in(['json', 'csv'])]]);
        $range = $this->range($request);

        $rows = array_map(fn ($r) => (array) $r, DB::select(
            "SELECT sc.id AS count_id, sc.decided_at AS approved_at,
                    p.sku, p.name, p.base_unit AS unit, l.code AS location_code, b.source AS batch_source,
                    scl.snapshot_qty, scl.counted_qty, scl.variance,
                    ROUND(scl.variance * scl.unit_cost, 2) AS variance_value
               FROM stock_count_lines scl
               JOIN stock_counts sc ON sc.id = scl.stock_count_id
               JOIN products p  ON p.id = scl.product_id
               JOIN locations l ON l.id = scl.location_id
               JOIN batches b   ON b.id = scl.batch_id
              WHERE sc.tenant_id = ? AND sc.status = 'POSTED' AND sc.decided_at BETWEEN ? AND ?
                AND scl.variance <> 0
              ORDER BY sc.decided_at DESC, p.name",
            [app('tenant_id'), $range['start'], $range['end']]
        ));

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->csv($rows, "count-variance-{$range['from']}-to-{$range['to']}.csv");
        }

        $t = [];
        $net = 0;

        foreach ($rows as $r) {
            $u = $r['unit'];
            $v = $this->milli($r['variance']);
            $c = $this->cents($r['variance_value']);
            $t[$u] ??= ['unit' => $u, 'short' => 0, 'over' => 0, 'short_value' => 0, 'over_value' => 0];

            if ($v < 0) {
                $t[$u]['short'] += $v;
                $t[$u]['short_value'] += $c;
            } else {
                $t[$u]['over'] += $v;
                $t[$u]['over_value'] += $c;
            }
            $net += $c;
        }

        return response()->json(['data' => [
            'report'         => 'count_variance',
            'from'           => $range['from'],
            'to'             => $range['to'],
            'generated_at'   => now()->toIso8601String(),
            'rows'           => $rows,
            'totals_by_unit' => array_values(array_map(fn ($x) => [
                'unit'            => $x['unit'],
                'shortage_qty'    => $this->fmt3($x['short']),
                'overage_qty'     => $this->fmt3($x['over']),
                'net_qty'         => $this->fmt3($x['short'] + $x['over']),
                'shortage_value'  => $this->fmt2($x['short_value']),
                'overage_value'   => $this->fmt2($x['over_value']),
            ], $t)),
            'net_variance_value' => $this->fmt2($net),
        ]]);
    }

    // ---------- Supplier receipts ----------
    public function supplierReceipts(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $data  = $request->validate(['format' => ['nullable', Rule::in(['json', 'csv'])]]);
        $range = $this->range($request);

        $rows = array_map(fn ($r) => (array) $r, DB::select(
            "SELECT s.supplier_code, s.name AS supplier, p.sku, p.name, p.base_unit AS unit,
                    COUNT(DISTINCT gr.id) AS receipts,
                    SUM(l.delivered_qty) AS delivered_qty,
                    SUM(l.accepted_qty)  AS accepted_qty,
                    SUM(l.rejected_qty)  AS rejected_qty,
                    ROUND(SUM(l.line_cost), 2) AS total_cost,
                    ROUND(SUM(l.line_cost) / NULLIF(SUM(l.accepted_qty), 0), 6) AS avg_unit_cost
               FROM goods_receipt_lines l
               JOIN goods_receipts gr ON gr.id = l.goods_receipt_id
               JOIN suppliers s ON s.id = gr.supplier_id
               JOIN products p  ON p.id = l.product_id
              WHERE gr.tenant_id = ? AND gr.status = 'POSTED' AND gr.receipt_date BETWEEN ? AND ?
              GROUP BY s.id, s.supplier_code, s.name, p.id, p.sku, p.name, p.base_unit
              ORDER BY s.name, p.name",
            [app('tenant_id'), $range['from'], $range['to']]
        ));

        foreach ($rows as &$r) {
            $d = $this->milli($r['delivered_qty']);
            $r['rejection_rate_pct'] = $d > 0
                ? number_format(round($this->milli($r['rejected_qty']) * 10000 / $d) / 100, 2, '.', '')
                : '0.00';
        }
        unset($r);

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->csv($rows, "supplier-receipts-{$range['from']}-to-{$range['to']}.csv");
        }

        $t = [];
        $cost = 0;

        foreach ($rows as $r) {
            $u = $r['unit'];
            $t[$u] ??= ['unit' => $u, 'delivered' => 0, 'accepted' => 0, 'rejected' => 0];
            $t[$u]['delivered'] += $this->milli($r['delivered_qty']);
            $t[$u]['accepted']  += $this->milli($r['accepted_qty']);
            $t[$u]['rejected']  += $this->milli($r['rejected_qty']);
            $cost += $this->cents($r['total_cost']);
        }

        return response()->json(['data' => [
            'report'         => 'supplier_receipts',
            'from'           => $range['from'],
            'to'             => $range['to'],
            'generated_at'   => now()->toIso8601String(),
            'rows'           => $rows,
            'totals_by_unit' => array_values(array_map(fn ($x) => [
                'unit'          => $x['unit'],
                'delivered_qty' => $this->fmt3($x['delivered']),
                'accepted_qty'  => $this->fmt3($x['accepted']),
                'rejected_qty'  => $this->fmt3($x['rejected']),
            ], $t)),
            'total_cost'     => $this->fmt2($cost),
        ]]);
    }

    // ---------- Reorder suggestions (advisory) ----------
    public function reorderSuggestions(Request $request)
    {
        if ($denied = $this->denied($request)) {
            return $denied;
        }

        $data = $request->validate([
            'include_all' => ['nullable', 'boolean'],
            'format'      => ['nullable', Rule::in(['json', 'csv'])],
        ]);

        $cond = Availability::sellableSql('b');

        $products = DB::select(
            "SELECT p.sku, p.name, p.base_unit AS unit, p.low_stock_threshold, p.reorder_target, p.reorder_lead_time_days,
                    COALESCE((SELECT SUM(CASE WHEN $cond THEN sp.physical_qty - sp.reserved_qty ELSE 0 END)
                                FROM stock_positions sp
                                JOIN batches b ON b.id = sp.batch_id
                               WHERE sp.product_id = p.id AND sp.tenant_id = p.tenant_id), 0) AS sellable_qty,
                    COALESCE((SELECT SUM(pol.ordered_qty - pol.received_qty - pol.cancelled_qty)
                                FROM purchase_order_lines pol
                                JOIN purchase_orders po ON po.id = pol.purchase_order_id
                               WHERE pol.product_id = p.id AND po.tenant_id = p.tenant_id
                                 AND po.status IN ('APPROVED', 'PARTIALLY_RECEIVED')
                                 AND pol.ordered_qty - pol.received_qty - pol.cancelled_qty > 0), 0) AS outstanding_qty
               FROM products p
              WHERE p.tenant_id = ? AND p.is_active = 1 AND p.reorder_target > 0
              ORDER BY p.name",
            array_merge(Availability::bindings(), [app('tenant_id')])
        );

        $includeAll = $request->boolean('include_all');
        $rows = [];

        foreach ($products as $p) {
            $target      = $this->milli($p->reorder_target);
            $sellable    = $this->milli($p->sellable_qty);
            $outstanding = $this->milli($p->outstanding_qty);
            $threshold   = $this->milli($p->low_stock_threshold);
            $suggested   = max(0, $target - $sellable - $outstanding);   // SRS 5.4

            if ($suggested === 0 && !$includeAll) {
                continue;
            }

            $rows[] = [
                'sku'                    => $p->sku,
                'name'                   => $p->name,
                'unit'                   => $p->unit,
                'sellable_qty'           => $this->fmt3($sellable),
                'outstanding_qty'        => $this->fmt3($outstanding),
                'reorder_target'         => $this->fmt3($target),
                'low_stock_threshold'    => $this->fmt3($threshold),
                'suggested_order_qty'    => $this->fmt3($suggested),
                'status'                 => ($threshold > 0 && $sellable <= $threshold) ? 'BELOW_THRESHOLD' : 'OK',
                'reorder_lead_time_days' => $p->reorder_lead_time_days,
            ];
        }

        if (($data['format'] ?? 'json') === 'csv') {
            return $this->csv($rows, 'reorder-suggestions.csv');
        }

        return response()->json(['data' => [
            'report'       => 'reorder_suggestions',
            'generated_at' => now()->toIso8601String(),
            'advisory'     => 'Suggestions are advisory only and need owner review. Seasonal demand is not considered.',
            'rows'         => $rows,
        ]]);
    }
}