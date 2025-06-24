<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuizRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'lesson_id' => 'sometimes|exists:lessons,id',
            'title' => 'sometimes|string|max:255',
            'max_attempts' => 'sometimes|integer|min:1',
            'time_limit' => 'sometimes|integer|min:1',
            'is_visible' => 'sometimes|boolean',
            
        ];
    }
    public function messages()
    {
        return [
            'lesson_id.exists' => 'Bài học không tồn tại.',
            'title.string' => 'Tiêu đề phải là chuỗi ký tự.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'max_attempts.integer' => 'Số lần thử tối đa phải là số nguyên.',
            'max_attempts.min' => 'Số lần thử tối đa phải lớn hơn hoặc bằng 1.',
            'time_limit.integer' => 'Thời gian giới hạn phải là số nguyên.',
            'time_limit.min' => 'Thời gian giới hạn phải lớn hơn hoặc bằng 1 phút.',
            'is_visible.boolean' => 'Trạng thái hiển thị phải là giá trị boolean.',
        ];
    }
}