<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $table = 'courses';
    protected $primaryKey = 'id';
    protected $fillable = [
        'course_name', 'university', 'difficulty_level', 'course_rating',
        'course_url', 'course_description', 'skills','status',
    ];

    public function instructors()
    {
        return $this->belongsToMany(Instructors::class, 'course_instructors', 'course_id', 'instructor_id');
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'course_id');
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'course_id');
    }

    public function forumPosts()
    {
        return $this->hasMany(ForumPost::class, 'course_id');
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class, 'course_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class, 'course_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'course_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'course_id');
    }
    public function coursereview()
    {
        return $this->hasMany(CourseReview::class);
    }

    public function similarCourses()
    {
        return $this->belongsToMany(Course::class, 'similarity_matrix', 'course_id_1', 'course_id_2')
                    ->withPivot('similarity_score');
    }
}