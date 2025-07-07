<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
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
            'learning_goals' => ['nullable', 'string'],
            'total_courses_completed' => ['required', 'integer', 'min:0'],
            'LoE_DI' => ['nullable', 'string'],
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
            'LoE_DI.string' => 'The LoE_DI field must be a string.',
        ];
    }
}