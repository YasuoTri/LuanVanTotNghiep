<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Admins extends Model
{
    use HasFactory;
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
    public function account(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AdminAccount::class, 'admin_id');
    }
}