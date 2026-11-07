<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WastageProjection extends Model
{
    protected $table = 'wastage_projection';

    protected $guarded = [];

    protected $casts = [
        'wasted_quantity' => 'decimal:3',
        'wastage_cost' => 'decimal:2',
        'wastage_date' => 'date',
        'data_as_of' => 'datetime',
    ];
}
