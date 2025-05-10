<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens,HasFactory, Notifiable;

    protected $fillable = [
        'userid_DI',
        'email',
        'password',
        'final_cc_cname_DI',
        'LoE_DI',
        'YoB',
        'gender',
        'role',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'YoB' => 'integer',
        'password' => 'hashed',
    ];

    public function admin()
    {
        return $this->hasOne(Admins::class, 'user_id');
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    public function instructor()
    {
        return $this->hasOne(Instructors::class, 'user_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'user_id');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'user_id');
    }

    public function forumPosts()
    {
        return $this->hasMany(ForumPost::class, 'user_id');
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class, 'user_id');
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'user_id');
    }

    public function quizResults()
    {
        return $this->hasMany(QuizResult::class, 'user_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'user_id');
    }

    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id');
    }
    // JWT required methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'email' => $this->email,
            'userid_DI' => $this->userid_DI,
            'final_cc_cname_DI' => $this->final_cc_cname_DI,
            'LoE_DI' => $this->LoE_DI,
            'YoB' => $this->YoB,
        ];
    }
}