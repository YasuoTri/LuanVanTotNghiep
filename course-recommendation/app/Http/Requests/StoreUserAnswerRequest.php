<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserAnswerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'quiz_result_id' => 'required|exists:quiz_results,id',
            'question_id' => 'required|exists:questions,id',
            'choice_id' => 'nullable|exists:question_choices,id',
        ];
    }
}