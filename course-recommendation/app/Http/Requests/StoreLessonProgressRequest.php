<?php

namespace App\Http\Requests\LessonProgress;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonProgressRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'lesson_id' => 'required|exists:lessons,id',
            'status' => 'required|in:not_started,in_progress,completed',
            'completed_at' => 'nullable|date',
        ];
    }
}