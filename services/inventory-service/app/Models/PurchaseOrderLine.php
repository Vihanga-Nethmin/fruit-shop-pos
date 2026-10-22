<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'ordered_qty'      => 'decimal:3',
        'received_qty'     => 'decimal:3',
        'cancelled_qty'    => 'decimal:3',
        'agreed_unit_cost' => 'decimal:6',
    ];
}