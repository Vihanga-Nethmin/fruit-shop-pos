<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockCountLine extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'snapshot_qty' => 'decimal:3',
        'counted_qty'  => 'decimal:3',
        'variance'     => 'decimal:3',
        'unit_cost'    => 'decimal:6',
        'counted_at'   => 'datetime',
    ];

    public function product()  { return $this->belongsTo(Product::class); }
    public function batch()    { return $this->belongsTo(Batch::class); }
    public function location() { return $this->belongsTo(Location::class); }
}