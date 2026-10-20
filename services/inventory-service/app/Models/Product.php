<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id', 'version'];

    protected $casts = [
        'selling_price'       => 'decimal:2',
        'tax_rate'            => 'decimal:2',
        'low_stock_threshold' => 'decimal:3',
        'reorder_target'      => 'decimal:3',
        'is_active'           => 'boolean',
        'batch_tracked'       => 'boolean',
    ];
}