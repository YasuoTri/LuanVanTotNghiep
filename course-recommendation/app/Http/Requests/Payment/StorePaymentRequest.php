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
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:momo,zalopay,bank_transfer,paypal,vnpay',
            'coupon_id' => 'nullable|exists:coupons,id',
            'payment_date' => 'nullable|date',
        ];
    }
}