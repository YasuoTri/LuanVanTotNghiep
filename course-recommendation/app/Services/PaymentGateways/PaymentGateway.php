<?php
namespace App\Services\PaymentGateways;

interface PaymentGateway
{
    public function createOrder(array $data): array;
}