<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'sale_number',
        'status',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'request_id',
    ];

    // මේ බිලට අදාළ භාණ්ඩ ටික (One to Many)
    public function lines()
    {
        return $this->hasMany(SaleLine::class);
    }

    // මේ බිලට අදාළ ගෙවීම් (One to Many)
    public function payments()
    {
        return $this->hasMany(RetailPayment::class);
    }
}