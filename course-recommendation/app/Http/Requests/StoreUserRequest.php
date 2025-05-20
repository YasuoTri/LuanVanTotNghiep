<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Chỉ admin được phép tạo user
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'userid_DI' => 'required|string|max:255|unique:users,userid_DI',
            'email' => 'nullable|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255',
            'avatar' => ['nullable', 'string', 'max:255'],
            'final_cc_cname_DI' => 'required|string|max:100',
            'LoE_DI' => 'required|string|max:50',
            'YoB' => 'nullable|integer|min:1900|max:' . date('Y'),
            'gender' => 'nullable|string|max:20',
            'role' => 'required|in:student,instructor,admin',
            'provider' => 'nullable|string|max:50',
            'provider_id' => 'nullable|string|max:255',
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