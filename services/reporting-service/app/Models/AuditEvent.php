<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $table = 'audit_events';

    protected $guarded = [];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];
}
