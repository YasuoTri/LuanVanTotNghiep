<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    protected $table = 'quizzes';
    protected $primaryKey = 'id';
    protected $fillable = ['lesson_id', 'title'];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    public function quizResults()
    {
        return $this->hasMany(QuizResult::class, 'quiz_id');
    }
}