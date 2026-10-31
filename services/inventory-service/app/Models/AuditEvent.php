<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'payload'     => 'array',
        'occurred_at' => 'datetime',
    ];

    // Audit records edit/delete karanna bari (business users ho support screens walin)
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Audit records cannot be deleted.'));
    }
}