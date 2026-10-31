<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SaleReturnController;
use App\Http\Controllers\Api\ShiftController;


Route::get('/ping', fn () => response()->json(['status' => 'ok']));
Route::post('/sales', [SaleController::class, 'store']);
Route::get('/sales', [SaleController::class, 'index']);
Route::get('/sales/{id}', [SaleController::class, 'show']);

Route::post('/sales/{id}/returns', [SaleReturnController::class, 'store']);
Route::post('/shifts/open', [ShiftController::class, 'open']);
Route::post('/shifts/{id}/close', [ShiftController::class, 'close']);
Route::get('/shifts', [ShiftController::class, 'index']);