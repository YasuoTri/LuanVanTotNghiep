<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_name',
        'university',
        'difficulty_level',
        'course_rating',
        'course_url',
        'course_description',
        'skills',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'course_rating' => 'float',
    ];

    /**
     * Get the interactions for the course.
     */
    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }
}
