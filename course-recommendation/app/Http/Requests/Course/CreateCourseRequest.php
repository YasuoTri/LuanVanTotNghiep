<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class CreateCourseRequest extends FormRequest
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
            'difficulty_level' => 'required|string|max:50',
            'course_description' => 'required|string',
            'course_rating' => 'nullable|numeric|min:0|max:5',
            'price' => 'nullable|numeric|min:0',
            'skills' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category_ids' => 'required|array|exists:categories,id',
            'instructor_id' => 'nullable|exists:instructors,id',
            'is_certificate_enabled' => 'boolean',
        ];
    }
}