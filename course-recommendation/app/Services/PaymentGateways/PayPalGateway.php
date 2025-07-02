<?php
namespace App\Services\PaymentGateways;

use App\Services\PayPalService;
use Illuminate\Support\Facades\Log;

class PayPalGateway implements PaymentGateway
{
    private $paypalService;

    public function __construct()
    {
        $this->paypalService = new PayPalService();
    }

    public function createOrder(array $data): array
    {
        try {
            // Tạo unique transaction code
            $transactionCode = 'PAYPAL_' . uniqid() . '_' . time();
            
            // Convert VND to USD (giả sử tỷ giá 1 USD = 24000 VND)
            $amountUSD = number_format($data['final_amount'] / 24000, 2, '.', '');
            
            // Sử dụng PayPalService để tạo order
            $result = $this->paypalService->createPayment([
                'amount' => $amountUSD,
                'currency' => 'USD',
                'description' => $data['description'] ?? "Course Payment - Course ID: " . $data['course_id'],
                'return_url' => url('/api/payment/paypal/success'),
                'cancel_url' => url('/api/payment/paypal/cancel'),
                'custom_data' => json_encode([
                    'user_id' => $data['user_id'],
                    'course_id' => $data['course_id'],
                    'coupon_id' => $data['coupon_id'] ?? null,
                    'transaction_code' => $transactionCode
                ])
            ]);

            if ($result && isset($result['approval_url'])) {
                Log::info('✅ PayPal Order Created Successfully', [
                    'transaction_code' => $transactionCode,
                    'amount_usd' => $amountUSD,
                    'approval_url' => $result['approval_url']
                ]);

                return [
                    'success' => true,
                    'transaction_code' => $transactionCode,
                    'data' => [
                        'order_id' => $result['payment_id'] ?? $transactionCode,
                        'approval_url' => $result['approval_url'],
                        'amount_usd' => $amountUSD
                    ],
                    'message' => 'PayPal order created successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create PayPal payment'
                ];
            }

        } catch (\Exception $e) {
            Log::error('❌ PayPal Order Creation Error', [
                'error' => $e->getMessage(),
                'course_id' => $data['course_id'] ?? null
            ]);
            
            return [
                'success' => false,
                'message' => 'PayPal order creation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Execute PayPal payment sau khi user approve
     */
    public function executePayment($paymentId, $payerId): array
    {
        try {
            $result = $this->paypalService->executePayment($paymentId, $payerId);
            
            if ($result) {
                return [
                    'success' => true,
                    'data' => $result,
                    'message' => 'Payment executed successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Payment execution failed'
                ];
            }
        } catch (\Exception $e) {
            Log::error('❌ PayPal Execute Error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Payment execution error: ' . $e->getMessage()
            ];
        }
    }

    public function verifyCallback(array $data): array
    {
        return [
            'success' => true,
            'data' => $data
        ];
    }
}
