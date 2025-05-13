<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserAnswerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'choice_id' => 'nullable|exists:question_choices,id',
            'answer_text' => 'nullable|string',
        ];
    }
}