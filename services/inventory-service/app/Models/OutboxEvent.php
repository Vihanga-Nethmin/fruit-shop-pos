<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'outbox';

    public const UPDATED_AT = null;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'payload'      => 'array',
        'occurred_at'  => 'datetime',
        'published_at' => 'datetime',
    ];
}