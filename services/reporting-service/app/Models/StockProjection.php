<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockProjection extends Model
{
    protected $table = 'stock_projection';

    protected $guarded = [];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'reorder_level' => 'decimal:3',
        'is_low_stock' => 'boolean',
        'is_out_of_stock' => 'boolean',
        'data_as_of' => 'datetime',
    ];
}
