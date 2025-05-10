<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnrollmentRequest extends FormRequest
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
            'status' => 'sometimes|in:active,completed',
            'completed_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ];
    }
}