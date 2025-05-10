<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'lesson_id', 'status', 'completed_at'];
    protected $casts = [
        'status' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }
}