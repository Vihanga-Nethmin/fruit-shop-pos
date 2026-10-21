<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SaleController;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));
Route::post('/sales', [SaleController::class, 'store']);