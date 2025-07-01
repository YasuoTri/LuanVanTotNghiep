<?php

return [
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'client_secret' => env('PAYPAL_CLIENT_SECRET'),
    'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox hoặc live
    
    // Webhook settings (nếu cần)
    'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
];
