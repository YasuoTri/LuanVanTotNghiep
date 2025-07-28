<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Review extends Model
{
    protected $table = 'reviews';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['user_id', 'course_id', 'rating', 'comment','feedback_type', 'created_at', 'updated_at'];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public static function boot()
    {
        parent::boot();

        static::created(function ($review) {
            $review->updateCourseRating();
        });

        static::updated(function ($review) {
            $review->updateCourseRating();
        });

        static::deleted(function ($review) {
            $review->updateCourseRating();
        });
    }
protected function updateCourseRating()
{
    $course = $this->course;
    Log::info('Course ID: ' . $this->course_id);
    Log::info('Course Object: ' . json_encode($course));

    if ($course) {
        $averageRating = Review::where('course_id', $course->id)
            ->avg('rating') ?? 0;
        Log::info('Average Rating: ' . $averageRating);

        $course->update(['course_rating' => $averageRating]);
    } else {
        Log::error('Course not found for course_id: ' .$course->id);
    }
}
 
}