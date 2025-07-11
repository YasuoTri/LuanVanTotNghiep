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
            'email' => 'nullable|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255',
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