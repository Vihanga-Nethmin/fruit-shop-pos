<?php

namespace App\Http\Controllers;

use App\Models\SalesProjection;
use App\Models\StockProjection;
use App\Models\StockAgeingProjection;
use App\Models\WastageProjection;
use App\Models\MarginProjection;
use App\Models\AuditEvent;
use App\Models\ExportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportingController extends Controller
{
    // Dashboard Report
    public function dashboard(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $sales = SalesProjection::where('tenant_id', $tenantId)
            ->selectRaw('
                SUM(total_sales) as total_sales,
                SUM(total_refunds) as total_refunds,
                SUM(net_revenue) as net_revenue
            ')
            ->first();

        $lowStockProducts = StockProjection::where('tenant_id', $tenantId)
            ->where('is_low_stock', true)
            ->count();

        return response()->json([
            'data' => [
                'total_sales' => (int) ($sales->total_sales ?? 0),
                'total_refunds' => (float) ($sales->total_refunds ?? 0),
                'net_revenue' => (float) ($sales->net_revenue ?? 0),
                'low_stock_products' => $lowStockProducts,
            ],
            'message' => 'Dashboard report retrieved successfully.',
        ]);
    }

    // Sales Report
    public function sales(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validated['date_from'] > $validated['date_to']) {
            return response()->json([
                'message' => 'date_from must be before or equal to date_to.',
            ], 422);
        }

        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $report = SalesProjection::where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [
                $validated['date_from'],
                $validated['date_to'],
            ])
            ->selectRaw('
                SUM(total_sales) as total_sales,
                SUM(total_revenue) as total_revenue,
                SUM(total_refunds) as total_refunds,
                SUM(net_revenue) as net_revenue
            ')
            ->first();

        return response()->json([
            'data' => [
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'total_sales' => (int) ($report->total_sales ?? 0),
                'total_revenue' => (float) ($report->total_revenue ?? 0),
                'total_refunds' => (float) ($report->total_refunds ?? 0),
                'net_revenue' => (float) ($report->net_revenue ?? 0),
            ],
            'message' => 'Sales report retrieved successfully.',
        ]);
    }

    // Stock Report
    public function stock(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $query = StockProjection::where('tenant_id', $tenantId);

        $totalProducts = (clone $query)->count();

        $lowStockProducts = (clone $query)
            ->where('is_low_stock', true)
            ->count();

        $outOfStockProducts = (clone $query)
            ->where('is_out_of_stock', true)
            ->count();

        $items = (clone $query)
            ->select([
                'product_id',
                'product_name',
                'category',
                'quantity_on_hand',
                'reorder_level',
                'is_low_stock',
                'is_out_of_stock',
            ])
            ->orderBy('product_name')
            ->get();

        return response()->json([
            'data' => [
                'total_products' => $totalProducts,
                'low_stock_products' => $lowStockProducts,
                'out_of_stock_products' => $outOfStockProducts,
                'items' => $items,
            ],
            'message' => 'Stock report retrieved successfully.',
        ]);
    }

    // Stock Ageing Report
    public function stockAgeing(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $query = StockAgeingProjection::where('tenant_id', $tenantId);

        $totalBatches = (clone $query)->count();

        $ageingBatches = (clone $query)
            ->where('age_in_days', '>=', 30)
            ->count();

        $nearExpiryBatches = (clone $query)
            ->where('is_near_expiry', true)
            ->count();

        $expiredBatches = (clone $query)
            ->where('is_expired', true)
            ->count();

        $items = (clone $query)
            ->select([
                'batch_id',
                'product_id',
                'product_name',
                'quantity',
                'received_date',
                'expiry_date',
                'age_in_days',
                'is_near_expiry',
                'is_expired',
            ])
            ->orderBy('expiry_date')
            ->get();

        return response()->json([
            'data' => [
                'total_batches' => $totalBatches,
                'ageing_batches' => $ageingBatches,
                'near_expiry_batches' => $nearExpiryBatches,
                'expired_batches' => $expiredBatches,
                'items' => $items,
            ],
            'message' => 'Stock ageing report retrieved successfully.',
        ]);
    }

    // Wastage Report
    public function wastage(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $query = WastageProjection::where('tenant_id', $tenantId);

        $totalRecords = (clone $query)->count();

        $totalWastedQuantity = (clone $query)
            ->sum('wasted_quantity');

        $totalWastageCost = (clone $query)
            ->sum('wastage_cost');

        $items = (clone $query)
            ->select([
                'product_id',
                'product_name',
                'wasted_quantity',
                'wastage_cost',
                'wastage_date',
                'reason',
            ])
            ->orderByDesc('wastage_date')
            ->get();

        return response()->json([
            'data' => [
                'total_records' => $totalRecords,
                'total_wasted_quantity' => (float) $totalWastedQuantity,
                'total_wastage_cost' => (float) $totalWastageCost,
                'items' => $items,
            ],
            'message' => 'Wastage report retrieved successfully.',
        ]);
    }

    // Profit Margin Report
    public function margins(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $query = MarginProjection::where('tenant_id', $tenantId);

        $report = (clone $query)
            ->selectRaw('
                SUM(total_revenue) as total_revenue,
                SUM(total_cost) as total_cost,
                SUM(gross_profit) as gross_profit
            ')
            ->first();

        $totalRevenue = (float) ($report->total_revenue ?? 0);
        $totalCost = (float) ($report->total_cost ?? 0);
        $grossProfit = (float) ($report->gross_profit ?? 0);

        $grossMarginPercentage = $totalRevenue > 0
            ? ($grossProfit / $totalRevenue) * 100
            : 0;

        $items = (clone $query)
            ->orderByDesc('report_date')
            ->get([
                'report_date',
                'total_revenue',
                'total_cost',
                'gross_profit',
                'gross_margin_percentage',
            ]);

        return response()->json([
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'gross_margin_percentage' => round($grossMarginPercentage, 2),
                'items' => $items,
            ],
            'message' => 'Profit margin report retrieved successfully.',
        ]);
    }

    // Audit Events
    public function auditEvents(Request $request)
    {
        $validated = $request->validate([
            'event_type' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (
            isset($validated['date_from'], $validated['date_to']) &&
            $validated['date_from'] > $validated['date_to']
        ) {
            return response()->json([
                'message' => 'date_from must be before or equal to date_to.',
            ], 422);
        }

        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $query = AuditEvent::where('tenant_id', $tenantId);

        if (!empty($validated['event_type'])) {
            $query->where('event_type', $validated['event_type']);
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('occurred_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('occurred_at', '<=', $validated['date_to']);
        }

        $items = $query->orderByDesc('occurred_at')
            ->limit(100)
            ->get([
                'event_id',
                'event_type',
                'actor_id',
                'actor_role',
                'event_data',
                'correlation_id',
                'occurred_at',
            ]);

        return response()->json([
            'data' => [
                'total_events' => $items->count(),
                'items' => $items,
            ],
            'message' => 'Audit events retrieved successfully.',
        ]);
    }

    // Create CSV Export
    public function createExport(Request $request)
    {
        $validated = $request->validate([
            'report_type' => ['nullable', 'in:sales'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (
            isset($validated['date_from'], $validated['date_to']) &&
            $validated['date_from'] > $validated['date_to']
        ) {
            return response()->json([
                'message' => 'date_from must be before or equal to date_to.',
            ], 422);
        }

        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $exportId = (string) Str::uuid();

        $job = ExportJob::create([
            'export_id' => $exportId,
            'tenant_id' => $tenantId,
            'report_type' => $validated['report_type'] ?? 'sales',
            'status' => 'pending',
        ]);

        try {
            $query = SalesProjection::where('tenant_id', $tenantId);

            if (!empty($validated['date_from'])) {
                $query->whereDate('sale_date', '>=', $validated['date_from']);
            }

            if (!empty($validated['date_to'])) {
                $query->whereDate('sale_date', '<=', $validated['date_to']);
            }

            $items = $query->orderBy('sale_date')->get([
                'sale_date',
                'total_sales',
                'total_refunds',
                'net_revenue',
            ]);

            $file = fopen('php://temp', 'r+');

            fputcsv($file, [
                'date',
                'total_sales',
                'total_refunds',
                'net_revenue',
            ]);

            foreach ($items as $item) {
                fputcsv($file, [
                    $item->sale_date,
                    $item->total_sales,
                    $item->total_refunds,
                    $item->net_revenue,
                ]);
            }

            rewind($file);
            $csvContent = stream_get_contents($file);
            fclose($file);

            $filename = 'exports/' . $exportId . '.csv';

            Storage::disk('local')->put($filename, $csvContent);

            $job->update([
                'status' => 'completed',
                'file_path' => $filename,
                'completed_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'export_id' => $exportId,
                    'status' => 'completed',
                    'download_url' => url(
                        '/api/v1/exports/' . $exportId . '/download'
                    ),
                ],
                'message' => 'CSV export created successfully.',
            ], 201);
        } catch (\Throwable $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // Check Export Status
    public function exportStatus(Request $request, string $exportId)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $job = ExportJob::where('export_id', $exportId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$job) {
            return response()->json([
                'message' => 'Export not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'export_id' => $job->export_id,
                'report_type' => $job->report_type,
                'status' => $job->status,
                'completed_at' => $job->completed_at,
                'download_url' => $job->status === 'completed'
                    ? url('/api/v1/exports/' . $job->export_id . '/download')
                    : null,
            ],
            'message' => 'Export status retrieved successfully.',
        ]);
    }

    // Download CSV Export
    public function downloadExport(Request $request, string $exportId)
    {
        $tenantId = $request->header('X-Tenant-ID');

        if (!$tenantId) {
            return response()->json([
                'message' => 'Tenant information is required.',
            ], 400);
        }

        $job = ExportJob::where('export_id', $exportId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->first();

        if (!$job || !$job->file_path) {
            return response()->json([
                'message' => 'Export file not found.',
            ], 404);
        }

        if (!Storage::disk('local')->exists($job->file_path)) {
            return response()->json([
                'message' => 'Export file not found.',
            ], 404);
        }

        return Storage::disk('local')->download(
            $job->file_path,
            'sales-report-' . $exportId . '.csv'
        );
    }
}
