<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'userid_DI',
        'email',
        'password',
        'final_cc_cname_DI',
        'LoE_DI',
        'YoB',
        'gender',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'YoB' => 'integer',
        'password' => 'hashed',
    ];

    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }

    // JWT required methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'sub' => $this->userid_DI, // Thêm userid_DI vào sub
        ];
    }
}