<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedEvent extends Model
{
    protected $table = 'processed_events';

    protected $guarded = [];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
