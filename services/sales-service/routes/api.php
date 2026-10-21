<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SaleController;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));
Route::post('/sales', [SaleController::class, 'store']);
Route::get('/sales', [SaleController::class, 'index']);
Route::get('/sales/{id}', [SaleController::class, 'show']);