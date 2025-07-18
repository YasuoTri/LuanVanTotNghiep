<?php

namespace App\Http\Controllers;
   
   use Illuminate\Http\Request;
   class VNPay extends Controller
   {
       public function createPayment(Request $request)
{
    // Lấy thông tin config: 
    $vnp_TmnCode = config('vnpay.vnp_TmnCode'); // Mã website của bạn tại VNPAY 
    $vnp_HashSecret = config('vnpay.vnp_HashSecret'); // Chuỗi bí mật
    $vnp_Url = config('vnpay.vnp_Url'); // URL thanh toán của VNPAY
    $vnp_ReturnUrl = config('vnpay.vnp_Returnurl'); // URL nhận kết quả trả về

    // Lấy thông tin từ request
    $orderId = $request->order_id ?? 'ORDER' . rand(100000, 999999);
    $amount = $request->amount ?? 100000;
    $bankCode = $request->bank_code ?? 'NCB';
    $orderType = $request->order_type ?? "billpayment";
    $orderInfo = $request->order_desc ?? "Thanh toán đơn hàng";
    
    // Thêm thông tin khóa học và user vào embed_data
    $embedData = json_encode([
        'course_id' => $request->course_id,
        'user_id' => $request->user_id,
        'payment_id' => $request->payment_id
    ]);

    // Thông tin đơn hàng, thanh toán
    $vnp_TxnRef = $orderId;
    $vnp_OrderInfo = $orderInfo;
    $vnp_OrderType = $orderType;
    $vnp_Amount = $amount * 100; 
    $vnp_Locale = $request->language ?? 'vn';
    $vnp_BankCode = $bankCode;
    $vnp_IpAddr = $request->ip();

    // Tạo input data để gửi sang VNPay server
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
        "vnp_ReturnUrl" => $vnp_ReturnUrl,
        "vnp_TxnRef" => $vnp_TxnRef,
        "vnp_ExpireDate" => date('YmdHis', strtotime('+15 minutes')),
    );
    
    // Thêm embed_data để lưu thông tin khóa học và user
    $inputData['vnp_Bill_Mobile'] = $request->user_id ?? '';
    $inputData['vnp_Inv_Email'] = $request->email ?? '';
    
    // Kiểm tra nếu mã ngân hàng đã được thiết lập và không rỗng
    if (isset($vnp_BankCode) && $vnp_BankCode != "") {
        $inputData['vnp_BankCode'] = $vnp_BankCode;
    }

    // Sắp xếp mảng dữ liệu input theo thứ tự bảng chữ cái của key
    ksort($inputData);
    
    $query = ""; // Biến lưu trữ chuỗi truy vấn (query string)
    $i = 0; // Biến đếm để kiểm tra lần đầu tiên
    $hashdata = ""; // Biến lưu trữ dữ liệu để tạo mã băm (hash data)

    // Duyệt qua từng phần tử trong mảng dữ liệu input
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            // Nếu không phải lần đầu tiên, thêm ký tự '&' trước mỗi cặp key=value
            $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            // Nếu là lần đầu tiên, không thêm ký tự '&'
            $hashdata .= urlencode($key) . "=" . urlencode($value);
            $i = 1; // Đánh dấu đã qua lần đầu tiên
        }
        // Xây dựng chuỗi truy vấn
        $query .= urlencode($key) . "=" . urlencode($value) . '&';
    }
        
    // Gán chuỗi truy vấn vào URL của VNPay
    $vnp_Url = $vnp_Url . "?" . $query;

    // Kiểm tra nếu chuỗi bí mật hash secret đã được thiết lập
    if (isset($vnp_HashSecret)) {
        // Tạo mã băm bảo mật (Secure Hash) bằng cách sử dụng thuật toán SHA-512 với hash secret
        $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        // Thêm mã băm bảo mật vào URL để đảm bảo tính toàn vẹn của dữ liệu
        $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
    }
    
    // Trả về URL thay vì redirect
    return $vnp_Url;
}


        public function vnpayReturn(Request $request)
       {
           $vnp_SecureHash = $request->vnp_SecureHash;
           $inputData = $request->all();
   
           unset($inputData['vnp_SecureHash']);
           ksort($inputData);
           $hashData = "";
           foreach ($inputData as $key => $value) {
               $hashData .= urlencode($key) . "=" . urlencode($value) . '&';
           }
           $hashData = rtrim($hashData, '&');
   
           $secureHash = hash_hmac('sha512', $hashData, config('vnpay.vnp_HashSecret'));
   
           if ($secureHash === $vnp_SecureHash) {
               if ($request->vnp_ResponseCode == '00') {
                   // Thanh toán thành công
                    return view('payment.success');
               } else {
                   // Thanh toán không thành công
                       return view('payment.failed');
               }
           } else {
               // Dữ liệu không hợp lệ
                   return view('payment.failed');
           }
       }
   }


