<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
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
            'amount' => 'sometimes|integer|min:0',
            'method' => 'sometimes|in:momo,zalopay,bank_transfer',
            'transaction_code' => 'nullable|string|max:50',
            'coupon_id' => 'nullable|exists:coupons,id',
            'status' => 'sometimes|in:pending,completed,failed',
            'payment_date' => 'nullable|date',
        ];
    }
}