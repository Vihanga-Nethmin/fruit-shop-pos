<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\OperationsReportController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationActionController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\SaleReturnReceiptController;
use App\Http\Controllers\SaleReturnReleaseController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WastageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SupplierReturnController;
use App\Http\Controllers\SellByReviewController;

// Catalogue & Inventory Service - public API (prefix /api/v1, see bootstrap/app.php)
// Agree the endpoints with the team before adding them (see README.md and /docs/api).

Route::get('/ping', fn () => response()->json(['data' => ['service' => 'inventory-service', 'status' => 'ok']]));

Route::middleware(['tenant', 'audit','idempotent'])->group(function () {

    // Master data
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::patch('/categories/{id}', [CategoryController::class, 'update']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::patch('/products/{id}', [ProductController::class, 'update']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::patch('/suppliers/{id}', [SupplierController::class, 'update']);

    Route::get('/locations', [LocationController::class, 'index']);
    Route::post('/locations', [LocationController::class, 'store']);
    Route::patch('/locations/{id}', [LocationController::class, 'update']);

    // Stock
    Route::post('/inventory/opening-balances', [OpeningStockController::class, 'store']);
    Route::get('/inventory/positions', [StockController::class, 'positions']);
    Route::get('/inventory/availability', [StockController::class, 'availability']);
    Route::get('/inventory/movements', [StockController::class, 'movements']);
    Route::post('/inventory/transfers', [TransferController::class, 'store']);

    // Purchasing and receiving
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::get('/purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    Route::post('/purchase-orders/{id}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('/purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel']);

    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index']);
    Route::get('/goods-receipts/{id}', [GoodsReceiptController::class, 'show']);
    Route::post('/goods-receipts', [GoodsReceiptController::class, 'store']);
    Route::post('/goods-receipts/{id}/post', [GoodsReceiptController::class, 'post']);

    // Reservations
    Route::post('/inventory/reservations', [ReservationController::class, 'store']);
    Route::get('/inventory/reservations/by-request/{requestId}', [ReservationController::class, 'showByRequest']);
    Route::post('/inventory/reservations/{id}/commit', [ReservationActionController::class, 'commit']);
    Route::post('/inventory/reservations/{id}/release', [ReservationActionController::class, 'release']);

    // Batches and customer returns
    Route::get('/batches', [BatchController::class, 'index']);
    Route::get('/batches/{id}', [BatchController::class, 'show']);
    Route::post('/batches/{id}/assessments', [BatchController::class, 'assess']);

    Route::post('/inventory/sale-return-receipts', [SaleReturnReceiptController::class, 'store']);
    Route::get('/inventory/sale-return-receipts/by-request/{requestId}', [SaleReturnReceiptController::class, 'showByRequest']);
    Route::post('/inventory/sale-return-receipt-lines/{lineId}/release', [SaleReturnReleaseController::class, 'release']);

    // Wastage
    Route::get('/wastage-records', [WastageController::class, 'index']);
    Route::get('/wastage-records/{id}', [WastageController::class, 'show']);
    Route::post('/wastage-records', [WastageController::class, 'store']);
    Route::post('/wastage-records/{id}/approve', [WastageController::class, 'approve']);
    Route::post('/wastage-records/{id}/reject', [WastageController::class, 'reject']);
    Route::post('/wastage-records/{id}/cancel', [WastageController::class, 'cancel']);

        // Supplier returns
    Route::get('/supplier-returns', [SupplierReturnController::class, 'index']);
    Route::get('/supplier-returns/{id}', [SupplierReturnController::class, 'show']);
    Route::post('/supplier-returns', [SupplierReturnController::class, 'store']);
    Route::post('/supplier-returns/{id}/approve', [SupplierReturnController::class, 'approve']);
    Route::post('/supplier-returns/{id}/reject', [SupplierReturnController::class, 'reject']);
    Route::post('/supplier-returns/{id}/cancel', [SupplierReturnController::class, 'cancel']);

    // Stock counts
    Route::get('/stock-counts', [StockCountController::class, 'index']);
    Route::get('/stock-counts/{id}', [StockCountController::class, 'show']);
    Route::post('/stock-counts', [StockCountController::class, 'store']);
    Route::post('/stock-counts/{id}/entries', [StockCountController::class, 'enter']);
    Route::post('/stock-counts/{id}/submit', [StockCountController::class, 'submit']);
    Route::post('/stock-counts/{id}/approve', [StockCountController::class, 'approve']);
    Route::post('/stock-counts/{id}/cancel', [StockCountController::class, 'cancel']);

    // Alerts, reports, audit
    Route::get('/inventory/alerts', [AlertController::class, 'index']);

    Route::get('/reports/stock', [ReportController::class, 'stock']);
    Route::get('/reports/ageing', [ReportController::class, 'ageing']);
    Route::get('/reports/wastage', [OperationsReportController::class, 'wastage']);
    Route::get('/reports/count-variance', [OperationsReportController::class, 'countVariance']);
    Route::get('/reports/supplier-receipts', [OperationsReportController::class, 'supplierReceipts']);
    Route::get('/reports/reorder-suggestions', [OperationsReportController::class, 'reorderSuggestions']);

    Route::get('/audit-events', [AuditController::class, 'index']);

        Route::get('/batches/{id}/sell-by-review', [SellByReviewController::class, 'show']);
    Route::post('/batches/{id}/sell-by-review', [SellByReviewController::class, 'store']);
});