<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PriceHistory extends Model
{
    use BelongsToTenant;

    protected $table = 'price_history';

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'old_price'    => 'decimal:2',
        'new_price'    => 'decimal:2',
        'effective_at' => 'datetime',
    ];
}