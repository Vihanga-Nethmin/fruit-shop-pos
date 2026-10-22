<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'delivered_qty' => 'decimal:3',
        'accepted_qty'  => 'decimal:3',
        'rejected_qty'  => 'decimal:3',
        'gross_weight'  => 'decimal:3',
        'tare_weight'   => 'decimal:3',
        'net_weight'    => 'decimal:3',
        'line_cost'     => 'decimal:2',
    ];
}