<?php

namespace App\Http\Requests;

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
            'university' => 'nullable|string|max:255',
            'difficulty_level' => 'nullable|string|max:50',
            'course_rating' => 'nullable|numeric|min:0|max:5',
            'course_url' => 'nullable|url',
            'course_description' => 'nullable|string',
            'skills' => 'nullable|string',
        ];
    }
}