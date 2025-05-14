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
    public function questions()
    {
        return $this->hasMany(Question::class, 'quiz_id');
    }
    public function getQuizResultsCountAttribute()
    {
        return $this->quizResults()->count();
    }
    public function getQuestionsCountAttribute()
    {
        return $this->questions()->count();
    }
    public function getQuizResults()
    {
        return $this->quizResults()->with('user')->get();
    }
    public function getQuestions()
    {
        return $this->questions()->with('answers')->get();
    }
    public function getQuizResultByUserId($userId)
    {
        return $this->quizResults()->where('user_id', $userId)->first();
    }
    public function getQuizResultById($quizResultId)
    {
        return $this->quizResults()->where('id', $quizResultId)->first();
    }
    public function getQuizResultByUserIdAndQuizId($userId, $quizId)
    {
        return $this->quizResults()->where('user_id', $userId)->where('quiz_id', $quizId)->first();
    }
    public function getQuizResultByIdAndQuizId($quizResultId, $quizId)
    {
        return $this->quizResults()->where('id', $quizResultId)->where('quiz_id', $quizId)->first();
    }
}