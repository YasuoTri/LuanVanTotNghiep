<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstructorRequest extends FormRequest
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
                Rule::exists('users', 'id')->where('role', 'instructor'),
            ],
            'name' => ['sometimes', 'string', 'max:100'],
            'bio' => ['nullable', 'string'],
            'organization' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'The selected user must exist and have the role "instructor".',
            'name.max' => 'The instructor name cannot exceed 100 characters.',
            'organization.max' => 'The organization name cannot exceed 100 characters.',
        ];
    }
}