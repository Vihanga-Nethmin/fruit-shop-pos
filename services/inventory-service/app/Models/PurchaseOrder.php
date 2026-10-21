<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id', 'version'];

    protected $casts = [
        'order_date'    => 'date:Y-m-d',
        'expected_date' => 'date:Y-m-d',
    ];

    public function lines() { return $this->hasMany(PurchaseOrderLine::class); }
}