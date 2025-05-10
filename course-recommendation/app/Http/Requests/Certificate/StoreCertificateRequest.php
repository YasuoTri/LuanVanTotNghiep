<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateRequest extends FormRequest
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
            'enrollment_id' => 'required|exists:enrollments,id',
            'certificate_code' => 'required|string|max:50|unique:certificates,certificate_code',
            'certificate_file' => 'required|file|mimetypes:application/pdf|max:10240', // PDF tối đa 10MB
            'issued_at' => 'nullable|date',
        ];
    }
}