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
    protected $fillable = ['id','instructor_id',
        'course_name', 'difficulty_level', 'course_rating',
        'course_url','image', 'price','skills','course_description','status','is_certificate_enabled'
    ];
    protected $casts = [
        'course_rating' => 'float',
        'is_certificate_enabled' => 'boolean',
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
        return $this->belongsTo(Instructors::class, 'instructor_id');
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
        return $this->hasMany(Report::class, 'course_id');
    }
    public function getHasPendingReportAttribute()
{
    return $this->reports->firstWhere('status', 'pending') ? true : false;
}
public function questions()
{
    return $this->hasMany(Question::class, 'quiz_id');
}
public function choices()
{
    return $this->hasMany(QuestionChoice::class, 'question_id');
}
public function coupons()
{
    return $this->hasMany(Coupon::class);
}

}