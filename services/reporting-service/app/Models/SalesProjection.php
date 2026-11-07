<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesProjection extends Model
{
    protected $table = 'sales_projection';

    protected $guarded = [];

    protected $casts = [
        'sale_date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_refunds' => 'decimal:2',
        'net_revenue' => 'decimal:2',
        'data_as_of' => 'datetime',
    ];
}
