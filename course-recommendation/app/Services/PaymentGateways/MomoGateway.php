<?php
namespace App\Services\PaymentGateways;

class MomoGateway implements PaymentGateway
{
    public function createOrder(array $data): array
    {
        // Implement Momo payment logic here
        return [
            'success' => false,
            'data' => ['message' => 'Momo payment not implemented yet'],
            'transaction_code' => null,
        ];
    }
}