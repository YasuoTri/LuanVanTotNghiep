<?php
namespace App\Services\PaymentGateways;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

interface PaymentGateway
{
    public function createOrder(array $data): array;
    public function verifyCallback(array $data): array;
}

class VNPayGateway implements PaymentGateway
{
    protected $config;

    public function __construct()
    {
        $this->config = [
            'tmn_code' => 'JOEYIIOK',
            'hash_secret' => '4GJIAZXBR13JLKFJ6WCS8ZCX7DC352SK',
            'url' => env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
            'return_url' => env('VNP_RETURN_URL'),
        ];

        // Kiểm tra cấu hình
        foreach (['tmn_code', 'hash_secret', 'return_url'] as $key) {
            if (empty($this->config[$key])) {
                throw new \Exception("Missing VNPay configuration: {$key}");
            }
        }

        // Kiểm tra HTTPS trong production hoặc khi không phải localhost
        if (!app()->environment('local') && !str_starts_with($this->config['return_url'], 'https://')) {
            throw new \Exception('VNPay return URL must use HTTPS in non-local environments');
        }

        // Kiểm tra múi giờ
        if (config('app.timezone') !== 'Asia/Ho_Chi_Minh') {
            Log::warning('VNPay: Incorrect timezone detected', ['timezone' => config('app.timezone')]);
        }
    }

    public function getHashSecret(): string
    {
        return $this->config['hash_secret'];
    }

    public function createOrder(array $data): array
{
    // $vnp_TmnCode = "JOEYIIOK"; //Mã website tại VNPAY 
    //     $vnp_HashSecret = "4GJIAZXBR13JLKFJ6WCS8ZCX7DC352SK"; //Chuỗi bí mật
    //     $vnp_Url = "http://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
    //     $vnp_Returnurl = "http://localhost:8000/return-vnpay";
    //     $vnp_TxnRef = date("YmdHis"); //Mã đơn hàng. Trong thực tế Merchant cần insert đơn hàng vào DB và gửi mã này sang VNPAY
    //     $vnp_OrderInfo = "Thanh toán hóa đơn phí dich vụ";
    //     $vnp_OrderType = 'other';
    //     $vnp_Amount =100000* 100;
    //     $vnp_Locale = 'locale';
    //     $vnp_IpAddr = '1.55.200.158';

    //     $inputData = array(
    //         "vnp_Version" => "2.1.0",
    //         "vnp_TmnCode" => $vnp_TmnCode,
    //         "vnp_Amount" => $vnp_Amount,
    //         "vnp_Command" => "pay",
    //         "vnp_CreateDate" => date('YmdHis'),
    //         "vnp_CurrCode" => "VND",
    //         "vnp_IpAddr" => $vnp_IpAddr,
    //         "vnp_Locale" => $vnp_Locale,
    //         "vnp_OrderInfo" => $vnp_OrderInfo,
    //         "vnp_OrderType" => $vnp_OrderType,
    //         "vnp_ReturnUrl" => $vnp_Returnurl,
    //         "vnp_TxnRef" => $vnp_TxnRef,
    //         "vnp_BankCode" => "NCB",
    //     );

    //     ksort($inputData);
    //     $query = "";
    //     $i = 0;
    //     $hashdata = "";
    //       foreach ($inputData as $key => $value) {
    //     if ($i == 1) {
    //         $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
    //     } else {
    //         $hashdata .= urlencode($key) . "=" . urlencode($value);
    //         $i = 1;
    //     }
    //     $query .= urlencode($key) . "=" . urlencode($value) . '&';
    // }

    //     $vnp_Url = $vnp_Url . "?" . $query;
    //     if (isset($vnp_HashSecret)) {
    //           $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret);//  
    //          $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
    //     }
    //     $inputData['vnp_Url'] = $vnp_Url;
    //     return [$vnp_Url,'success' => true,'transaction_code' => $vnp_TxnRef,'data' => $inputData];
      $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
    $vnp_Returnurl = "http://127.0.0.1:8000/payments/vnpay/callback";
    $vnp_TmnCode = "JOEYIIOK";//Mã website tại VNPAY 
    $vnp_HashSecret = "4GJIAZXBR13JLKFJ6WCS8ZCX7DC352SK"; //Chuỗi bí mật 
    $vnp_TxnRef = date("YmdHis"); //Mã đơn hàng. Trong thực tế Merchant cần insert đơn hàng vào DB và gửi mã này sang VNPAY
    $vnp_OrderInfo = "Thanh toán đơn hàng test";
    $vnp_OrderType ="BarBer Shop";
    $vnp_Amount = 20000 * 100;
    $vnp_Locale = 'VN';
    $vnp_BankCode = 'NCB';
    $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
    //Add Params of 2.0.1 Version
    // $vnp_ExpireDate = $_POST['txtexpire'];
    //Billing
    $inputData = array(
        "vnp_Version" => "2.1.0",
        "vnp_TmnCode" => $vnp_TmnCode,
        "vnp_Amount" => $vnp_Amount,
        "vnp_Command" => "pay",
        "vnp_CreateDate" => date('YmdHis'),
        "vnp_CurrCode" => "VND",
        "vnp_IpAddr" => $vnp_IpAddr,
        "vnp_Locale" => $vnp_Locale,
        "vnp_OrderInfo" => $vnp_OrderInfo,
        "vnp_OrderType" => $vnp_OrderType,
        "vnp_ReturnUrl" => $vnp_Returnurl,
        "vnp_TxnRef" => $vnp_TxnRef
      
    );
    
    if (isset($vnp_BankCode) && $vnp_BankCode != "") {
        $inputData['vnp_BankCode'] = $vnp_BankCode;
    }
    if (isset($vnp_Bill_State) && $vnp_Bill_State != "") {
        $inputData['vnp_Bill_State'] = $vnp_Bill_State;
    }
    
    //var_dump($inputData);
    ksort($inputData);
    $query = "";
    $i = 0;
    $hashdata = "";
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashdata .= urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
        $query .= urlencode($key) . "=" . urlencode($value) . '&';
    }
    
    $vnp_Url = $vnp_Url . "?" . $query;
    if (isset($vnp_HashSecret)) {
        $vnpSecureHash =   hash_hmac('sha512', $hashdata, $vnp_HashSecret);//  
        $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
    }
    $returnData = array('code' => '00'
        , 'message' => 'success'
        , 'data' => $vnp_Url);
        if (isset($_POST['redirect'])) {
            header('Location: ' . $vnp_Url);
            die();
        } else {
            return ['success' => true, 'data' => $returnData, 'transaction_code' => $vnp_TxnRef];
        } 
        // vui lòng tham khảo thêm tại code demo
}

    public function verifyCallback(array $data): array
    {
        try {
            // Log dữ liệu callback
            Log::info('VNPay callback received', ['data' => $data]);

            // Kiểm tra chữ ký
            $vnp_SecureHash = $data['vnp_SecureHash'] ?? '';
            unset($data['vnp_SecureHash']);

            $vnpayData = $this->sortData($data);
            $queryString = http_build_query($vnpayData, '', '&', PHP_QUERY_RFC3986);
            $expectedHash = hash_hmac('sha512', $queryString, $this->getHashSecret());

            Log::info('VNPay callback checksum', [
                'query_string' => $queryString,
                'expected_hash' => $expectedHash,
                'received_hash' => $vnp_SecureHash,
            ]);

            if (!hash_equals($expectedHash, $vnp_SecureHash)) {
                Log::error('VNPay callback: Invalid signature', ['data' => $data]);
                return [
                    'success' => false,
                    'message' => 'Invalid signature',
                ];
            }

            // Kiểm tra trạng thái giao dịch
            if ($data['vnp_ResponseCode'] !== '00') {
                Log::error('VNPay callback: Transaction failed', [
                    'response_code' => $data['vnp_ResponseCode'],
                    'data' => $data,
                ]);
                return [
                    'success' => false,
                    'message' => 'Transaction failed: ' . ($data['vnp_ResponseCode'] ?? 'Unknown error'),
                ];
            }

            Log::info('VNPay callback verified', ['data' => $data]);

            return [
                'success' => true,
                'data' => $data,
                'transaction_code' => $data['vnp_TxnRef'],
            ];
        } catch (\Exception $e) {
            Log::error('VNPay callback verification failed', ['error' => $e->getMessage(), 'data' => $data]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function sortData(array $data): array
    {
        ksort($data);
        return $data;
    }
    public function transfer($sessionId)
    {
        // Chuyển tiền cho instructors qua API thanh toán
        // ...
        return [
            'success' => true,
            'message' => 'Transfer successful',
            'transaction_code' => Str::random(10), // Mã giao dịch giả lập
        ];
    }
}