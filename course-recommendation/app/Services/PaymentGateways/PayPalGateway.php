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
            $transactionCode = 'PAYPAL_' . uniqid() . '_' . time();
            
            $amountUSD = number_format($data['final_amount'] / 24000, 2, '.', '');
            
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
                ])
            ]);

            if ($result && isset($result['approval_url'])) {
                $transactionCode = $result['payment_id'];
                Log::info('✅ PayPal Order Created Successfully', [
                    'transaction_code' => $transactionCode,
                    'amount_usd' => $amountUSD,
                    'approval_url' => $result['approval_url']
                ]);

                return [
                    'success' => true,
                    'transaction_code' => $transactionCode,
                    'data' => [
                        'order_id' => $result['payment_id'],
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

    public function executePayment($paymentId, $payerId): array
    {
        try {
            $result = $this->paypalService->executePayment($paymentId, $payerId);
            
            if ($result['status'] === 'COMPLETED') {
                Log::info('✅ PayPal Payment Execution Successful', [
                    'order_id' => $paymentId,
                    'capture_id' => $result['capture_id'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency']
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'order_id' => $paymentId,
                        'capture_id' => $result['capture_id'],
                        'amount' => $result['amount'],
                        'currency' => $result['currency']
                    ],
                    'message' => 'Payment executed successfully'
                ];
            } else {
                Log::error('❌ PayPal Payment Execution Failed', [
                    'order_id' => $paymentId,
                    'status' => $result['status']
                ]);
                return [
                    'success' => false,
                    'message' => 'Payment execution failed: ' . ($result['status'] ?? 'Unknown status')
                ];
            }
        } catch (\Exception $e) {
            Log::error('❌ PayPal Execute Error', [
                'error' => $e->getMessage(),
                'order_id' => $paymentId
            ]);
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