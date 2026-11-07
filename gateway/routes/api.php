<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportingController;

Route::get('/ping', fn () => response()->json([
    'data' => [
        'service' => 'gateway',
        'status' => 'ok'
    ]
]));

Route::get('/dashboard', [ReportingController::class, 'dashboard']);
Route::get('/reports/sales', [ReportingController::class, 'sales']);
Route::get('/reports/stock', [ReportingController::class, 'stock']);
Route::get('/reports/stock-ageing', [ReportingController::class, 'stockAgeing']);
Route::get('/reports/wastage', [ReportingController::class, 'wastage']);
Route::get('/reports/margins', [ReportingController::class, 'margins']);
Route::get('/reports/purchases', [ReportingController::class, 'purchases']);
Route::get('/reports/count-variances', [ReportingController::class, 'countVariances']);
Route::get('/audit-events', [ReportingController::class, 'auditEvents']);

Route::post('/exports', [ReportingController::class, 'createExport']);
Route::get('/exports/{exportId}', [ReportingController::class, 'exportStatus']);
Route::get('/exports/{exportId}/download', [ReportingController::class, 'downloadExport']);
