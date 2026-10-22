<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class QualityAssessment extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null; 

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'before_values' => 'array',
        'after_values'  => 'array',
    ];
}