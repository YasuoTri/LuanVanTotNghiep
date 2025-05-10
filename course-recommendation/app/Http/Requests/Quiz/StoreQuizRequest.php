<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'lesson_id' => 'required|exists:lessons,id',
            'title' => 'required|string|max:255',
        ];
    }
}