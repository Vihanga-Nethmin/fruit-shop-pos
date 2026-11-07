<?php

use App\Http\Controllers\ReportingController;
use Illuminate\Support\Facades\Route;

// Ping
Route::get('/ping', function () {
    return response()->json([
        'data' => [
            'service' => 'reporting-service',
            'status' => 'ok',
        ],
    ]);
});

// Dashboard
Route::get('/dashboard', [ReportingController::class, 'dashboard']);

// Sales Report
Route::get('/reports/sales', [ReportingController::class, 'sales']);

// Stock Report
Route::get('/reports/stock', [ReportingController::class, 'stock']);

// Stock Ageing Report
Route::get('/reports/stock-ageing', [ReportingController::class, 'stockAgeing']);

// Wastage Report
Route::get('/reports/wastage', [ReportingController::class, 'wastage']);

// Profit Margin Report
Route::get('/reports/margins', [ReportingController::class, 'margins']);

// Purchase Report - placeholder until purchase events are integrated
Route::get('/reports/purchases', function () {
    return response()->json([
        'data' => [
            'total_purchases' => 0,
            'total_purchase_cost' => 0,
            'items' => [],
        ],
        'message' => 'Purchase report retrieved successfully.',
    ]);
});

// Stock Count Variance Report - placeholder until count events are integrated
Route::get('/reports/count-variances', function () {
    return response()->json([
        'data' => [
            'total_count_sessions' => 0,
            'total_variances' => 0,
            'items' => [],
        ],
        'message' => 'Stock count variance report retrieved successfully.',
    ]);
});

// Create CSV Export
Route::post('/exports', [ReportingController::class, 'createExport']);

// Check Export Status
Route::get('/exports/{exportId}', [ReportingController::class, 'exportStatus']);

// Download CSV Export
Route::get('/exports/{exportId}/download', [ReportingController::class, 'downloadExport']);

// Audit Events
Route::get('/audit-events', [ReportingController::class, 'auditEvents']);
