<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'string',
            'question_type' => 'in:multiple_choice,true_false,open_ended',
            'points' => 'numeric|min:0',
            'sort_order' => 'integer|min:0',
            'is_visible' => 'boolean',
        ];
    }
}