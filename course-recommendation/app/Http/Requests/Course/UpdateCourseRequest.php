<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    public function authorize()
    {
        // return auth()->user()->is_admin; // Assumes admin check
        return true;
    }

    public function rules()
    {
        return [
            'course_name' => 'required|string|max:255',
            'university' => 'nullable|string|max:255',
            'difficulty_level' => 'nullable|string|max:50',
            'course_description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'skills' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}