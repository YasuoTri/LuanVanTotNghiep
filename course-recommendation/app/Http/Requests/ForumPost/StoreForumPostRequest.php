<?php

namespace App\Http\Requests\ForumPost;

use Illuminate\Foundation\Http\FormRequest;

class StoreForumPostRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ];
    }
}