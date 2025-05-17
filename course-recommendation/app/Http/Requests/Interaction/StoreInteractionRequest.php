<?php

namespace App\Http\Requests\Interaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInteractionRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
             'course_id' => [
                'required',
                'integer',
                Rule::unique('interactions', 'course_id'),
            ],
            'rating' => 'nullable|numeric|min:0|max:5',
            'viewed' => 'boolean',
            'explored' => 'boolean',
            'certified' => 'boolean',
            'start_time' => 'nullable|date',
            'last_event' => 'nullable|date',
            'nevents' => 'integer|min:0',
            'ndays_act' => 'integer|min:0',
            'nplay_video' => 'integer|min:0',
            'nchapters' => 'integer|min:0',
            'nforum_posts' => 'integer|min:0',
        ];
    }
}