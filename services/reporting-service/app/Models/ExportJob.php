<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportJob extends Model
{
    protected $table = 'export_jobs';

    protected $guarded = [];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
