<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    use HasFactory;

    protected $fillable = [ 'quiz_result_id', 'question_id', 'choice_id', 'answer_text', 'is_correct', 'points_earned',
    ];

    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }
    public function user()
    {
        // Truy vấn user thông qua quiz_result
        return $this->hasOneThrough(
            User::class, // Model đích
            QuizResult::class, // Model trung gian
            'id', // Khóa ngoại trên bảng trung gian (quiz_results.id)
            'id', // Khóa chính trên bảng đích (users.id)
            'quiz_result_id', // Khóa ngoại trên bảng hiện tại (user_answers.quiz_result_id)
            'user_id' // Khóa ngoại trên bảng trung gian liên kết với bảng đích (quiz_results.user_id)
        );
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