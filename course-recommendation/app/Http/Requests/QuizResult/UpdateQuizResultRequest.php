<?php

namespace App\Http\Requests\QuizResult;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuizResultRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'quiz_id' => 'sometimes|exists:quizzes,id',
            'score' => 'sometimes|numeric|min:0|max:100',
            'completed_at' => 'nullable|date',
        ];
    }
}