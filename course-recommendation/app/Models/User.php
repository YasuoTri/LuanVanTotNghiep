<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Str;
class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens,HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'id',
        'username',
        'fullname', // Updated from name to fullname
        'email',
        'password',
        'avatar',
        'birthdate', // Updated from YoB to birthdate
        'gender',
        'role',
        'provider',
        'provider_id',
        'suspended_until',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'deleted_at' => 'datetime',
        'birthdate' => 'date', // Ensure birthdate is cast to date
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

    // public function certificates()
    // {
    //     return $this->hasMany(Certificate::class, 'user_id');
    // }

    // public function forumPosts()
    // {
    //     return $this->hasMany(ForumPost::class, 'user_id');
    // }

    // public function interactions()
    // {
    //     return $this->hasMany(Interaction::class, 'user_id');
    // }

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
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\CustomResetPassword($token));
    }
    // JWT required methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'email' => $this->email,

        ];
    }
    public function violations()
{
    return $this->hasMany(Violation::class);
}
    public function isSuspended()
    {
        return $this->suspended_until && $this->suspended_until->isFuture();
    }

}