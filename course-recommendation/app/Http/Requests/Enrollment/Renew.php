<?php

namespace App\Http\Requests\Enrollment;

use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;

class Renew extends FormRequest
{
    public function authorize()
    {
        return true; // Bỏ qua kiểm tra quyền
    }

    public function rules()
    {
        return [
            'payment_method' => 'required|in:momo,zalopay,bank_transfer,vnpay,paypal',
        ];
    }

    public function messages()
    {
        return [
            'payment_method.required' => 'The payment method is required.',
            'payment_method.in' => 'The payment method is invalid.',
        ];
    }
}