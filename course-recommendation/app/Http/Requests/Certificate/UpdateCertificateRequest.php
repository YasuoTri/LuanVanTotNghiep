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
            'enrollment_id' => 'sometimes|exists:enrollments,id',
            'instructor_id' => 'nullable|exists:instructors,id',
            'certificate_code' => 'sometimes|string|max:50|unique:certificates,certificate_code,' . $this->id,
            'download_url'=> 'nullable|string',
            'issued_at' => 'nullable|date',
        ];
    }
}