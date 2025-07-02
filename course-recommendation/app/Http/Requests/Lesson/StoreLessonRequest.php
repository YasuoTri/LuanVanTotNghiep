<?php

namespace App\Http\Requests\Lesson;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'origin_id' => 'nullable|string|max:255',
            'version' => 'nullable|string|max:255',
            'course_id' => 'nullable|exists:courses,id',
            'title' => 'required|string|max:255',
            'video' => 'required|file|max:102400', // Video tối đa 100MB
            'is_preview' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }
}