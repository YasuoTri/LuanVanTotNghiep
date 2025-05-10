<?php

namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'status' => 'required|in:active,completed',
            'completed_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ];
    }
}