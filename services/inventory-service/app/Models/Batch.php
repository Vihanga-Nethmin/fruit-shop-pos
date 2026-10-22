<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id', 'version'];

    protected $casts = [
        'accepted_qty' => 'decimal:3',
        'unit_cost'    => 'decimal:6',
        'received_at'  => 'datetime',
        'harvest_date' => 'date:Y-m-d',
        'sell_by_date' => 'date:Y-m-d',
        'use_by_date'  => 'date:Y-m-d',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}