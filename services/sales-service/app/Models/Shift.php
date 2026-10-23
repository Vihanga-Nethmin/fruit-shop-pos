<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'opening_float',
        'closing_cash',
        'expected_cash',
        'variance',
        'status',
        'opened_at',
        'closed_at',
    ];
}