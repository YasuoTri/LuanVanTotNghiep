<?php
// Callback data
$data = [
    'vnp_Amount' => '10000000',
    'vnp_BankCode' => 'NCB',
    'vnp_CardType' => 'ATM',
    'vnp_OrderInfo' => 'Payment for course #1',
    'vnp_PayDate' => '20250512123500',
    'vnp_ResponseCode' => '00',
    'vnp_TmnCode' => '9J54CI4D',
    'vnp_TransactionNo' => '12345678',
    'vnp_TxnRef' => '20250512123456_abc123',
    'vnp_ExtraData' => '{"user_id":1,"course_id":1,"coupon_id":null}',
];

// Sort data by key
ksort($data);

// Create query string
$queryString = http_build_query($data);

// Use the hash secret from .env
$hashSecret = '82K9PCJW44ZUYZS33I14R2BFYM1WGGDY';

// Generate checksum
$vnp_SecureHash = hash_hmac('sha512', $queryString, $hashSecret);

echo "Query String: $queryString\n";
echo "vnp_SecureHash: $vnp_SecureHash\n";
?>