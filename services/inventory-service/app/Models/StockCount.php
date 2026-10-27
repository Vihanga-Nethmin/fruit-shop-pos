<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id', 'version'];

    protected $casts = [
        'submitted_at' => 'datetime',
        'decided_at'   => 'datetime',
    ];

    public function lines() { return $this->hasMany(StockCountLine::class); }
}