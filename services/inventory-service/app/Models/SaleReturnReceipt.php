<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SaleReturnReceipt extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id', 'version'];

    public function lines() { return $this->hasMany(SaleReturnReceiptLine::class); }
}