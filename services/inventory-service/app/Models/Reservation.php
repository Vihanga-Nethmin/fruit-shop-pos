<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id', 'version'];

    protected $casts = [
        'expires_at'   => 'datetime',
        'committed_at' => 'datetime',
        'released_at'  => 'datetime',
    ];

    public function lines() { return $this->hasMany(ReservationLine::class); }
}