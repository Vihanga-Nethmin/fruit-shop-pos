<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReservationLine extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'qty'       => 'decimal:3',
        'unit_cost' => 'decimal:6',
    ];
}