<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use Ramsey\Uuid\Uuid;

class PayPalService
{
    private $clientId;
    private $clientSecret;
    private $baseUrl;
    private $accessToken;
    protected $client;
    public function __construct()
    {
        $this->clientId = config('paypal.client_id');
        $this->clientSecret = config('paypal.client_secret');
        
        if (!$this->clientId || !$this->clientSecret) {
            throw new \Exception('PayPal credentials not configured in .env file');
        }
        
        $this->baseUrl = config('paypal.mode') === 'sandbox' 
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
        $env = new SandboxEnvironment($this->clientId, $this->clientSecret);
        $this->client = new PayPalHttpClient($env);
    }

    /**
     * Lấy Access Token từ PayPal
     */
    private function getAccessToken()
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        Log::info('🔑 Getting PayPal Access Token...');

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];
                
                Log::info('✅ PayPal Access Token obtained successfully');
                return $this->accessToken;
            } else {
                throw new \Exception('Failed to get PayPal access token: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('❌ PayPal Access Token Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Gửi tiền qua PayPal Payouts API
     */
    public function sendPayout($recipientEmail, $amount, $currency = 'USD', $note = 'Revenue Distribution')
    {
        try {
            $accessToken = $this->getAccessToken();
            $batchId = 'Payouts_' . uniqid() . '_' . time();
            
            $payload = [
                "sender_batch_header" => [
                    "sender_batch_id" => $batchId,
                    "email_subject" => "🎉 You have received a payout!",
                    "email_message" => "Congratulations! You have received a revenue distribution payout from our course platform."
                ],
                "items" => [
                    [
                        "recipient_type" => "EMAIL",
                        "amount" => [
                            "value" => number_format($amount, 2, '.', ''),
                            "currency" => $currency
                        ],
                        "receiver" => $recipientEmail,
                        "note" => $note,
                        "sender_item_id" => "item_" . uniqid() . '_' . time()
                    ]
                ]
            ];

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'PayPal-Request-Id' => $batchId
                ])
                ->post($this->baseUrl . '/v1/payments/payouts', $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('✅ PayPal Payout Success', [
                    'batch_id' => $result['batch_header']['payout_batch_id'],
                    'status' => $result['batch_header']['batch_status'],
                    'recipient' => $recipientEmail,
                    'amount' => $amount,
                    'full'=> $result
                ]);

                // Trả về object giống như SDK
                return (object) [
                    'result' => (object) [
                        'success' => true,
                        'batch_header' => (object) $result['batch_header'],
                        'items' => array_map(function($item) {
                            return (object) $item;
                        }, $result['items'] ?? [])
                    ]
                ];
            } else {
                $error = $response->json();
                $errorMessage = $error['message'] ?? 'Unknown PayPal API error';
                
                Log::error('❌ PayPal API Error', [
                    'status' => $response->status(),
                    'error' => $errorMessage,
                    'response' => $response->body()
                ]);
                
                throw new \Exception('PayPal API Error: ' . $errorMessage);
            }

        } catch (\Exception $e) {
            Log::error('❌ PayPal Payout Error', [
                'error' => $e->getMessage(),
                'recipient' => $recipientEmail,
                'amount' => $amount
            ]);
            throw $e;
        }
    }

    /**
     * Kiểm tra trạng thái payout
     */
    public function getPayoutStatus($batchId)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            Log::info('🔍 Checking PayPal Payout Status', ['batch_id' => $batchId]);
            
            $response = Http::withToken($accessToken)
                ->get($this->baseUrl . '/v1/payments/payouts/' . $batchId);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('📊 PayPal Status Retrieved', [
                    'batch_id' => $batchId,
                    'status' => $result['batch_header']['batch_status']
                ]);

                return (object) [
                    'result' => (object) [
                        'batch_header' => (object) $result['batch_header'],
                        'items' => array_map(function($item) {
                            return (object) $item;
                        }, $result['items'] ?? [])
                    ]
                ];
            } else {
                throw new \Exception('PayPal Status API Error: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('❌ PayPal Status Check Error', [
                'error' => $e->getMessage(),
                'batch_id' => $batchId
            ]);
            throw $e;
        }
    }

    /**
     * Lấy thông tin chi tiết về một payout item
     */
    public function getPayoutItem($payoutItemId)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withToken($accessToken)
                ->get($this->baseUrl . '/v1/payments/payouts-item/' . $payoutItemId);

            if ($response->successful()) {
                return (object) ['result' => (object) $response->json()];
            } else {
                throw new \Exception('PayPal Item API Error: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('❌ PayPal Item Check Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    public function executePayment($orderId, $payerId = null)
{
    try {
        $accessToken = $this->getAccessToken();
        
        Log::info('💰 Executing PayPal Payment', [
            'order_id' => $orderId,
            'payer_id' => $payerId,
            'base_url' => $this->baseUrl
        ]);

        // Verify order state before capturing
        $orderResponse = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json']) // Unique request ID to avoid duplication
            ->get($this->baseUrl . '/v2/checkout/orders/' . $orderId);

        if ($orderResponse->successful()) {
            $orderDetails = $orderResponse->json();
            Log::info('Order Details', [
                'order_id' => $orderId,
                'status' => $orderDetails['status']
            ]);
            if ($orderDetails['status'] !== 'APPROVED') {
                throw new \Exception('Order is not in APPROVED state: ' . $orderDetails['status']);
            }
        } else {
            throw new \Exception('Failed to retrieve order details: ' . $orderResponse->body());
        }
        $accessToken = $this->getAccessToken();
        $payment = Payment::where('transaction_code', $orderId)->first();
        if ($payment && $payment->coupon_id) {
            $coupon = Coupon::find($payment->coupon_id);

            if (!$coupon || !$coupon->is_active) {
                throw new \Exception('❌ Coupon is inactive or not found');
            }

            if ($coupon->expiry_date && now()->gt($coupon->expiry_date)) {
                throw new \Exception('❌ Coupon has expired');
            }

            if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
                throw new \Exception('❌ Coupon usage limit exceeded');
            }
            Log::info('✅ Coupon is valid for use', ['coupon_id' => $coupon->id]);
        }
        // Capture the payment with an empty JSON object
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'PayPal-Request-Id' => Uuid::uuid4()->toString() // Use a unique UUID
            ])
            ->post($this->baseUrl . '/v2/checkout/orders/' . $orderId . '/capture', (object)[]);

        Log::info('Capture Request Details', [
            'order_id' => $orderId,
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        if ($response->successful()) {
            $result = $response->json();
            $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? null;
            if (!$capture) {
                throw new \Exception('No capture details found in response');
            }

            $platformFee = $result['purchase_units'][0]['platform_fees'][0]['amount']['value'] ?? 0;
            Log::info('✅ PayPal Payment Executed Successfully', [
                'order_id' => $orderId,
                'capture_id' => $capture['id'],
                'amount' => $capture['amount']['value'],
                'currency' => $capture['amount']['currency_code'],
                'platform_fee' => $platformFee
            ]);
            $payment = Payment::where('transaction_code', $orderId)->first();
            if ($payment) {
                $payment->transaction_code = $capture['id'];  // Lưu capture id
                $payment->save();
            }
            Log::info('✅ PayPal Payment Executed Successfully', [
                'order_id' => $orderId,
                'status' => $result['status'],
                'response' => $result
            ]);

            // Extract capture details
            $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? null;
            if (!$capture) {
                throw new \Exception('No capture details found in response');
            }

            return [
                'status' => $result['status'],
                'capture_id' => $capture['id'],
                'amount' => $capture['amount']['value'],
                'currency' => $capture['amount']['currency_code']
            ];
        } else {
            $errorBody = $response->body();
            Log::error('❌ PayPal Execute API Error', [
                'status' => $response->status(),
                'body' => $errorBody,
                'order_id' => $orderId
            ]);
            throw new \Exception('PayPal Execute Error: ' . $errorBody);
        }

    } catch (\Exception $e) {
        Log::error('❌ PayPal Execute Payment Error: ' . $e->getMessage(), [
            'order_id' => $orderId,
            'payer_id' => $payerId
        ]);
        throw $e;
    }
}
  public function createPayment(array $data)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $payload = [
                "intent" => "CAPTURE",
                "purchase_units" => [
                    [
                        "invoice_id" => 'INV-' . uniqid(),
                        "amount" => [
                            "currency_code" => $data['currency'] ?? 'USD',
                            "value" => $data['amount']
                        ],
                        "description" => $data['description'] ?? 'Course Payment',
                        "custom_id" => $data['custom_data'] ?? null
                    ]
                ],
                "application_context" => [
                    "return_url" => $data['return_url'],
                    "cancel_url" => $data['cancel_url'],
                    "user_action" => "PAY_NOW"
                ],
            ];
        //     $payload = [
        //     "intent" => "CAPTURE",
        //     "purchase_units" => [
        //         [
        //             "invoice_id" => 'INV-' . uniqid(),
        //             "amount" => [
        //                 "currency_code" => $data['currency'] ?? 'USD',
        //                 "value" => $data['amount']
        //             ],
        //             "description" => $data['description'] ?? 'Course Payment',
        //             "custom_id" => $data['custom_data'] ?? null,
        //             "payee" => [
        //                 "email_address" => "sb-hwwip44224479@business.example.com" // Tài khoản Business phụ
        //             ],
        //             "platform_fees" => [
        //                 [
        //                     "amount" => [
        //                         "currency_code" => "USD",
        //                         "value" => "5.00" // Phí hoa hồng của nền tảng
        //                     ],
        //                     "payee" => [
        //                         "merchant_id" => "FLMD9YM3H3J4U" // Merchant ID của platform
        //                     ]
        //                 ]
        //             ]
        //         ]
        //     ],
        //     "application_context" => [
        //         "return_url" => $data['return_url'],
        //         "cancel_url" => $data['cancel_url'],
        //         "user_action" => "PAY_NOW"
        //     ],
        // ];

            Log::info('🚀 Creating PayPal Payment', [
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD'
            ]);

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->baseUrl . '/v2/checkout/orders', $payload);
            Log::info('🚀 Creating PayPal Payment from create Payment', [
                '$request:' =>$response->body(),
            ]);
            if ($response->successful()) {
                $result = $response->json();
                
                // Tìm approval URL
                $approvalUrl = null;
                foreach ($result['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }

                Log::info('✅ PayPal Payment Created', [
                    'order_id' => $result['id'],
                    'approval_url' => $approvalUrl
                ]);

                return [
                    'payment_id' => $result['id'],
                    'approval_url' => $approvalUrl,
                    'status' => $result['status']
                ];
            } else {
                throw new \Exception('PayPal API Error: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('❌ PayPal Create Payment Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test connection với PayPal
     */
    public function testConnection()
    {
        try {
            $this->getAccessToken();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
      public function refundTransaction($captureId, $amount)
    {
        $request = new CapturesRefundRequest($captureId);
        $request->body = [
            'amount' => [
                'value' => $amount,
                'currency_code' => 'USD'
            ]
        ];

        try {
            $response = $this->client->execute($request);
            if ($response->statusCode === 201) {
                return $response->result;
            } else {
                throw new \Exception('Refund failed');
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
