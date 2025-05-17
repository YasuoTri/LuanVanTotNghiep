<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Có thể thêm logic phân quyền
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'sometimes',
                'integer',
                Rule::exists('users', 'id')->where('role', 'student'),
            ],
            'learning_goals' => ['nullable', 'string'],
            'interests' => ['nullable', 'string'],
            'total_courses_completed' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'The selected user must exist and have the role "student".',
            'total_courses_completed.min' => 'The total courses completed cannot be negative.',
        ];
    }
}