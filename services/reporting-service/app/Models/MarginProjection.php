<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarginProjection extends Model
{
    protected $table = 'margin_projection';

    protected $guarded = [];

    protected $casts = [
        'report_date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'gross_margin_percentage' => 'decimal:2',
        'data_as_of' => 'datetime',
    ];
}
