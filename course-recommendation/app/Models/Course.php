<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'courses';
    protected $primaryKey = 'id';
    protected $fillable = [
        'course_name', 'difficulty_level', 'course_rating',
        'course_url','image', 'course_description', 'price','skills','status','origin_id', 'version'
    ];
    
    protected $dates = ['deleted_at'];

    // Automatically generate slug from course_name
    public function setCourseNameAttribute($value)
    {
        $this->attributes['course_name'] = $value;
        $this->attributes['course_url'] = Str::slug($value);
    }
    public function origin()
    {
        return $this->belongsTo(Course::class, 'origin_id');
    }
    public function derivedCourses()
    {
        return $this->hasMany(Course::class, 'origin_id');
    }
        // Accessor for full URL
    public function getFullCourseUrlAttribute()
    {
        return url("/courses/{$this->course_url}");
    }
public function categories()
    {
        return $this->belongsToMany(Category::class, 'course_category', 'course_id', 'category_id');
    }
   public function instructors()
    {
        return $this->hasOneThrough(
            Instructors::class,
            Course_Instructors::class,
            'course_id', // Foreign key on course_instructors
            'id',        // Foreign key on instructors
            'id',        // Local key on courses
            'instructor_id' // Local key on course_instructors
        );
    }
    public function category()
    {
        return $this->belongsToMany(Category::class, 'course_category', 'course_id', 'category_id');
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

        public function Course_Instructorss()
    {
        return $this->hasOne(Course_Instructors::class);
    }
    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}