<?php

namespace App\Http\Requests\LessonProgress;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonProgressRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'lesson_id' => 'sometimes|exists:lessons,id',
            'status' => 'sometimes|in:not_started,in_progress,completed',
            'completed_at' => 'nullable|date',
        ];
    }
}