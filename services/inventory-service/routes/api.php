<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TransferController;



Route::get('/ping', fn () => response()->json(['data' => ['service' => 'inventory-service', 'status' => 'ok']]));

Route::middleware('tenant')->group(function () {
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

        Route::post('/inventory/opening-balances', [OpeningStockController::class, 'store']);
    Route::get('/inventory/positions', [StockController::class, 'positions']);
    Route::get('/inventory/movements', [StockController::class, 'movements']);

        Route::post('/inventory/transfers', [TransferController::class, 'store']);

});