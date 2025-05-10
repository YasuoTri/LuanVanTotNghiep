<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCertificateRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'course_id' => 'sometimes|exists:courses,id',
            'enrollment_id' => 'sometimes|exists:enrollments,id',
            'certificate_code' => 'sometimes|string|max:50|unique:certificates,certificate_code,' . $this->id,
            'certificate_file' => 'sometimes|file|mimetypes:application/pdf|max:10240', // PDF tối đa 10MB
            'issued_at' => 'nullable|date',
        ];
    }
}