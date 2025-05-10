<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'coupons';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'code', 'discount_type', 'discount_value', 'min_order', 'start_date',
        'end_date', 'usage_limit', 'used_count', 'is_active'
    ];
    protected $casts = [
        'discount_type' => 'string',
        'is_active' => 'boolean',
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class, 'coupon_id');
    }
}