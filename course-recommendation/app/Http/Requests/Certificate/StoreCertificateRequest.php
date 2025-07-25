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
        // return [
        //     'enrollment_id' => 'required|exists:enrollments,id',
        //     'instructor_id' => 'nullable|exists:instructors,id',
        //     'certificate_code' => 'required|string|max:50|unique:certificates,certificate_code',
        //     'download_url'=> 'nullable|string',
        //     'issued_at' => 'nullable|date',
        // ];
        return [
            'enrollment_id' => 'required|exists:enrollments,id',
            'instructor_id' => 'required|exists:instructors,id',
            'certificate_code' => 'required|string|max:50|unique:certificates,certificate_code',
            'download_url'=> 'required|string',
            'issued_at' => 'required|date',
        ];
    }
}