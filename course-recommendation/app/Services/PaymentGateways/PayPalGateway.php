<?php
namespace App\Services\PaymentGateways;

class PayPalGateway implements PaymentGateway
{
    public function createOrder(array $data): array
    {
        // Implement PayPal API integration
        return [
            'success' => true,
            'data' => ['order_id' => 'paypal_order_123'],
            'transaction_code' => 'paypal_123',
        ];
    }
}