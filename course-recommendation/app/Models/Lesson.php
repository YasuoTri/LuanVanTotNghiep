<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $table = 'lessons';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'course_id', 'title', 'video_url', 'duration', 'is_preview', 'sort_order'
    ];
    protected $casts = [
        'is_preview' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class, 'lesson_id');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'lesson_id');
    }
}