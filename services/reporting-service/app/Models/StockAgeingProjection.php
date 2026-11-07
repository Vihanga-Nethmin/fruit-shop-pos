<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAgeingProjection extends Model
{
    protected $table = 'stock_ageing_projection';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'decimal:3',
        'received_date' => 'date',
        'expiry_date' => 'date',
        'age_in_days' => 'integer',
        'is_near_expiry' => 'boolean',
        'is_expired' => 'boolean',
        'data_as_of' => 'datetime',
    ];
}
