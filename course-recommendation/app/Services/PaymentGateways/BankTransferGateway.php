<?php
namespace App\Services\PaymentGateways;

class BankTransferGateway implements PaymentGateway
{
    public function createOrder(array $data): array
    {
        // Implement bank transfer logic here
        return [
            'success' => false,
            'data' => ['message' => 'Bank transfer not implemented yet'],
            'transaction_code' => null,
        ];
    }
}