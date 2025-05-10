<?php

namespace App\Http\Requests\ForumPost;

use Illuminate\Foundation\Http\FormRequest;

class UpdateForumPostRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'course_id' => 'sometimes|exists:courses,id',
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
        ];
    }
}