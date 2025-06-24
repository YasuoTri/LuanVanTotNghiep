<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionChoiceRequest extends FormRequest
{
    public function authorize()
    {
     return true;
    }

    public function rules()
    {
        return [
            'question_id' => 'required|exists:questions,id',
            'content' => 'required|string',
            'is_correct' => 'boolean'
        ];
    }
}