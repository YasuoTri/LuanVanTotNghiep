<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
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
            'amount' => 'required|integer|min:0',
            'method' => 'required|in:momo,zalopay,bank_transfer',
            'transaction_code' => 'nullable|string|max:50',
            'coupon_id' => 'nullable|exists:coupons,id',
            'status' => 'required|in:pending,completed,failed',
            'payment_date' => 'nullable|date',
        ];
    }
}