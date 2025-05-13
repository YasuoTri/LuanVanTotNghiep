<?php

namespace App\Http\Requests\Quiz;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'lesson_id' => 'required|exists:lessons,id',
            'title' => 'required|string|max:255',
            'max_attempts' => 'nullable|integer|min:1',
            'time_limit' => 'nullable|integer|min:1', // Thời gian giới hạn (phút)
            'is_visible' => 'boolean',
        ];
    }
    public function messages()
    {
        return [
            'lesson_id.required' => 'Vui lòng chọn bài học liên quan.',
            'lesson_id.exists' => 'Bài học không tồn tại.',
            'title.required' => 'Tiêu đề bài kiểm tra là bắt buộc.',
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