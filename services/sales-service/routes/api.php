<?php

use Illuminate\Support\Facades\Route;

// Sales Service - public API (prefix /api/v1, see bootstrap/app.php)
// Agree the endpoints with the team before adding them (see README.md and /docs/api).

Route::get('/ping', fn () => response()->json(['data' => ['service' => 'sales-service', 'status' => 'ok']]));
