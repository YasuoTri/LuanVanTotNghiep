<?php

namespace App\Http\Requests\Lesson;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLessonRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'course_id' => 'sometimes|exists:courses,id',
            'title' => 'sometimes|string|max:255',
            'video' => 'sometimes|file|mimetypes:video/mp4,video/avi,video/mov|max:102400', // Video tối đa 100MB
            'duration' => 'nullable|integer|min:0',
            'is_preview' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }
}