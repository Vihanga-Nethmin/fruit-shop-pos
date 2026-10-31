<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SupplierReturn extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id', 'version'];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'unit_cost'   => 'decimal:6',
        'return_date' => 'date:Y-m-d',
        'decided_at'  => 'datetime',
    ];
}