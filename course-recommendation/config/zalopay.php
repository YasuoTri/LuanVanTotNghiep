<?php
return [
    'app_id' => env('ZALOPAY_APP_ID'),
    'key1' => env('ZALOPAY_KEY1'),
    'key2' => env('ZALOPAY_KEY2'),
    'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-open.zalopay.vn/v2/create'),
    'callback_url' => env('ZALOPAY_CALLBACK_URL', 'https://your-api.com/api/payments/callback'),
];