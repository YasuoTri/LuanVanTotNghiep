<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'quiz_id' => 'required|exists:quizzes,id',
            'title' => 'required|string',
            'question_type' => 'required|in:multiple_choice,true_false,open_ended',
            'points' => 'required|numeric|min:0',
            'sort_order' => 'integer|min:0',
            'is_visible' => 'boolean',
        ];
    }
}