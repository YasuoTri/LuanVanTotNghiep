<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuizRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'lesson_id' => 'sometimes|exists:lessons,id',
            'title' => 'sometimes|string|max:255',
        ];
    }
}