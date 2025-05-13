<?php
namespace App\Http\Requests\Enrollment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaidEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:momo,zalopay,bank_transfer,paypal,vnpay',
            'coupon_id' => 'nullable|exists:coupons,id',
            'payment_date' => 'nullable|date',
        ];
    }
}