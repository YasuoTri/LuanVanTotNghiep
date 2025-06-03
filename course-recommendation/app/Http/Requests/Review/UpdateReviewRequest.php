<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'course_id' => 'sometimes|exists:courses,id',
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'feedback_type' => 'nullable|in:content_quality,instructor,platform_issue,not_interested',
        ];
    }
}