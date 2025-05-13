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
            'course_id' => 'required|exists:courses,id',
            'user_id' => 'nullable|exists:users,id',
            'completed_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ];
    }
}