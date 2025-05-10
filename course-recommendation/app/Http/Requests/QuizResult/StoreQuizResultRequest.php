<?php

namespace App\Http\Requests\QuizResult;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizResultRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'quiz_id' => 'required|exists:quizzes,id',
            'score' => 'required|numeric|min:0|max:100',
            'completed_at' => 'nullable|date',
        ];
    }
}