<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id', 'version'];

    protected $casts = [
        'receipt_date' => 'date:Y-m-d',
        'posted_at'    => 'datetime',
    ];

    public function lines() { return $this->hasMany(GoodsReceiptLine::class); }
}