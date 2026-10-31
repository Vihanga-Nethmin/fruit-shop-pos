<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    protected $guarded = [];
    public function lines() { return $this->hasMany(ReturnLine::class); }
    public function refund() { return $this->hasOne(RefundRecord::class); }
}