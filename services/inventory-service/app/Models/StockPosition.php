<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockPosition extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id', 'version'];

    protected $casts = [
        'physical_qty' => 'decimal:3',
        'reserved_qty' => 'decimal:3',
    ];

    public function product()  { return $this->belongsTo(Product::class); }
    public function batch()    { return $this->belongsTo(Batch::class); }
    public function location() { return $this->belongsTo(Location::class); }
}