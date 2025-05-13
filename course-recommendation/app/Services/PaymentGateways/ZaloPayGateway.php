<?php
namespace App\Services\PaymentGateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ZaloPayGateway implements PaymentGateway
{
    protected $config;

    public function __construct()
    {
        $this->config = [
        'app_id' => env('ZALOPAY_APP_ID'),
        'key1' => env('ZALOPAY_KEY1'),
        'key2' => env('ZALOPAY_KEY2'),
        'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-open.zalopay.vn/v2/create'),
        'callback_url' => env('ZALOPAY_CALLBACK_URL'),
    ];
        foreach (['app_id', 'key1', 'key2', 'callback_url'] as $key) {
        if (empty($this->config[$key])) {
            throw new \Exception("Missing ZaloPay configuration: {$key}");
        }
    }
    }

    public function createOrder(array $data): array
    {
        $order = [
            'app_id' => $this->config['app_id'],
            'app_user' => 'user_' . $data['user_id'],
            'app_time' => now()->timestamp * 1000,
            'amount' => $data['amount'],
            'app_trans_id' => now()->format('ymd') . '_' . Str::random(6),
            'embed_data' => json_encode(['course_id' => $data['course_id'], 'coupon_id' => $data['coupon_id'] ?? null]),
            'item' => json_encode([['course_id' => $data['course_id'], 'name' => 'Course Payment', 'amount' => $data['final_amount']]]),
            'bank_code' => '',
            'description' => 'Payment for course #' . $data['course_id'],
            'callback_url' => $this->config['callback_url'],
        ];

        // Generate MAC
        $order['mac'] = hash_hmac('sha256', implode('|', [
            $order['app_id'], $order['app_trans_id'], $order['app_user'], $order['amount'],
            $order['app_time'], $order['embed_data'], $order['item']
        ]), $this->config['key1']);
        try {
        // Call ZaloPay API
        $response = Http::post($this->config['endpoint'], $order);
        $result = $response->json();
} catch (\Illuminate\Http\Client\ConnectionException $e) {
    Log::error('ZaloPay API request failed: ' . $e->getMessage());
        return [
            'success' => false,
            'data' => ['message' => 'Unable to connect to ZaloPay: ' . $e->getMessage()],
            'transaction_code' => $order['app_trans_id'],
        ];
    }
        return [
            'success' => $result['return_code'] === 1,
            'data' => $result,
            'transaction_code' => $order['app_trans_id'],
        ];
    }
}