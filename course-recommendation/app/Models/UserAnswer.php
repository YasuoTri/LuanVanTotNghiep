<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'quiz_result_id', 'question_id', 'choice_id', 'answer_text', 'is_correct', 'points_earned',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quizResult()
    {
        return $this->belongsTo(QuizResult::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function choice()
    {
        return $this->belongsTo(QuestionChoice::class);
    }
}