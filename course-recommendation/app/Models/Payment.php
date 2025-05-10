<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'user_id', 'course_id', 'amount', 'method', 'transaction_code',
        'coupon_id', 'status', 'payment_date'
    ];
    protected $casts = [
        'method' => 'string',
        'status' => 'string',
        'payment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}