<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Chỉ admin được phép cập nhật user
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Lấy user ID từ route hoặc request
        $userId = $this->route('id') ?? $this->input('id');

        return [
            'userid_DI' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('users', 'userid_DI')->ignore($userId)
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'password' => 'sometimes|string|min:8|max:255',
            'avatar' => ['nullable', 'string', 'max:255'],
            'final_cc_cname_DI' => 'sometimes|string|max:100',
            'LoE_DI' => 'sometimes|string|max:50',
            'YoB' => 'sometimes|integer|min:1900|max:' . date('Y'),
            'gender' => 'sometimes|nullable|string|max:20',
            'role' => 'sometimes|in:student,instructor,admin',
            'admin_level' => [
                'required_if:role,admin',
                Rule::in(['organization', 'program'])
            ]
        ];
    }

    /**
     * Get custom error messages for validation.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'userid_DI.unique' => 'The user ID is already taken.',
            'email.unique' => 'The email address is already registered.',
            'password.min' => 'The password must be at least 8 characters.',
            'avatar.max' => 'The avatar URL cannot exceed 255 characters.',
            'YoB.min' => 'The year of birth must be at least 1900.',
            'YoB.max' => 'The year of birth cannot be in the future.',
            'admin_level.required_if' => 'The admin level is required when role is admin.'
        ];
    }
}