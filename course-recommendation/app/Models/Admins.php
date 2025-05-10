<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admins extends Model
{
    protected $table = 'admins';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'admin_level', 'activity_log'];
    protected $casts = [
        'admin_level' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}