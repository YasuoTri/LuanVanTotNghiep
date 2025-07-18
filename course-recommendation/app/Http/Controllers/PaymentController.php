<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Models\AdminAccount;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\RevenueSession;
use App\Services\PaymentGateways\PayPalGateway;
use App\Services\PaymentGateways\VNPayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\PaymentGateways\PaymentGateway;
use App\Services\PaymentGateways\ZaloPayGateway;
use App\Services\PaymentGateways\MomoGateway;
use App\Services\PaymentGateways\BankTransferGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Enrollment;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Course;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\Admins;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    private $zaloPayConfig;
  protected $gateways = [
    'zalopay' => ZaloPayGateway::class,
    'momo' => MomoGateway::class,
    'bank_transfer' => BankTransferGateway::class,
    'paypal' => PayPalGateway::class,
    'vnpay' => VNPayGateway::class,
];

    public function __construct()
    {
        $this->zaloPayConfig = [
            'app_id' => env('ZALOPAY_APP_ID', 'your_zalopay_app_id'),
            'key1' => env('ZALOPAY_KEY1', 'your_zalopay_key1'),
            'key2' => env('ZALOPAY_KEY2', 'your_zalopay_key2'),
            'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-open.zalopay.vn/v2/create'),
        ];
    }
 /**
     * Display a listing of payments for admin.
     *
     * @return JsonResponse
     */
    public function indexForAdmin(): JsonResponse
    {
        // Lấy danh sách payments với thông tin liên quan
        $payments = Payment::with(['user', 'course', 'coupon'])
            ->select([
                'id',
                'user_id',
                'course_id',
                'amount',
                'method',
                'transaction_code',
                'coupon_id',
                'status',
                'created_at as payment_date',
                'created_at'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return response()->json([
            'message' => 'Payments retrieved successfully',
            'data' => $payments
        ], 200);
    }

    /**
     * Display the specified payment for admin.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function showForAdmin($id): JsonResponse
    {
        // Tìm payment với thông tin liên quan
        $payment = Payment::with('user' 
        , 'coupon', 'course')
            ->select([
                'id',
                'user_id',
                'course_id',
                'amount',
                'method',
                'transaction_code',
                'coupon_id',
                'status',
                'created_at as payment_date',
                'created_at',
                'updated_at'
            ])
            ->find($id);

        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Payment retrieved successfully',
            'data' => $payment
        ], 200);
    }

  /**
     * List payments for the authenticated student.
     */
   public function index(): JsonResponse
{
    $user = Auth::user();

    $payments = Payment::where('user_id', $user->id)
        ->with(['course', 'coupon'])
        ->paginate(10);

    // Thêm payment_date mà vẫn giữ nguyên tất cả trường gốc
    $payments->getCollection()->transform(function ($payment) {
        $payment->payment_date = $payment->created_at->format('Y-m-d H:i:s'); // hoặc toDateTimeString()
        return $payment;
    });

    return response()->json([
        'message' => 'Payments retrieved successfully',
        'data' => $payments
    ]);
}

    /**
     * Show a specific payment for the authenticated student.
     */
    public function show($id): JsonResponse
{
    $user = Auth::user();

    $payment = Payment::where('id', $id)
        ->where('user_id', $user->id)
        ->with(['course', 'coupon'])
        ->first();

    if (!$payment) {
        return response()->json(['message' => 'Payment not found'], 404);
    }

    // Thêm trường payment_date từ created_at
    $payment->payment_date = $payment->created_at->format('Y-m-d H:i:s');

    return response()->json([
        'message' => 'Payment retrieved successfully',
        'data' => $payment
    ]);
}

    public function store(array $paymentData, int $userId, int $courseId): JsonResponse
{
    $user = Auth::user();
    // Validate payment data
    $requiredFields = ['amount', 'method'];
    foreach ($requiredFields as $field) {
        if (!isset($paymentData[$field])) {
            return response()->json(['message' => "Missing required field: {$field}"], 400);
        }
    }
    $apiUrl = "https://v6.exchangerate-api.com/v6/4f9127878d7801342b6c0abd/latest/USD";

    $response = Http::get($apiUrl);
     if ($response->failed()) {
        return response()->json([
            'error' => 'Cannot fetch exchange rate'
        ], 500);
    }
    $rateData = $response->json();
    $vndRate = $rateData['conversion_rates']['VND'] ?? null;
    if (!$vndRate) {
        return response()->json([
            'error' => 'Cannot find VND exchange rate in the response from ExchangeRate API'
        ], 500);
    }
    // Apply coupon if provided
    if ($paymentData['method'] == 'vnpay') {
        $finalAmount = round($paymentData['amount'] * $vndRate);
    } else {
        $finalAmount =round($paymentData['amount'], 2);
    }
    $coupon = null;
    if (!empty($paymentData['code'])) {
    $coupon = Coupon::where('code', $paymentData['code'])
        ->where('is_active', true)
        ->where('start_date', '<=', now())
        ->where('end_date', '>=', now())
        ->whereColumn('used_count', '<', 'usage_limit')
        ->first();

    if (!$coupon) {
        return response()->json(['message' => 'Invalid or expired coupon'], 400);
    }
      // Check if coupon is valid for the specified course
        if ($coupon->course_id !== null && $coupon->course_id != $courseId) {
            return response()->json(['message' => 'Coupon is not valid for this course'], 400);
        }
    
    $paymentData['coupon_id'] = $coupon->id;

        if ($coupon->discount_type === 'percent') {
            $finalAmount = $finalAmount * (1 - $coupon->discount_value / 100);
        } else {
            $finalAmount = $finalAmount - $coupon->discount_value;
        }

        if ($finalAmount < 0) {
            $finalAmount = 0;
        }
    }
    if ($paymentData['method'] !== 'vnpay') {
            $finalAmount = round($finalAmount, 2);
    }
    // Get or create revenue session for current month
    $currentMonth = now()->month;
    $currentYear = now()->year;
    $revenueSession = RevenueSession::firstOrCreate(
        ['month' => $currentMonth, 'year' => $currentYear],
        ['total_revenue' => 0, 'status' => 'open']
    );

    // Select payment gateway
    $gatewayClass = $this->gateways[$paymentData['method']] ?? PayPalGateway::class;
    $gateway = new $gatewayClass();

    // Create order
    $orderData = [
        'user_id' => $userId,
        'course_id' => $courseId,
        'amount' => $paymentData['amount'],
        'final_amount' => $finalAmount,
        'coupon_id' => $paymentData['coupon_id'] ?? null,
        'currency' => 'USD', // PayPal thường dùng USD
        'description' => 'Course Payment - Course ID: ' . $courseId,
    ];

    $result = $gateway->createOrder($orderData);

    if (!$result['success']) {
        return response()->json([
            'message' => $result['message'] ?? 'Failed to create payment order',
            'error' => $result
        ], 400);
    }

    // Start transaction
    DB::beginTransaction();
    try {
        // Store payment
        $payment = Payment::create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'amount' => $finalAmount,
            'method' => $paymentData['method'],
            'transaction_code' => $result['transaction_code'],
            'coupon_id' => $paymentData['coupon_id'] ?? null,
            'status' => 'pending',
            // 'payment_date' => $paymentData['payment_date'] ?? null,
            'revenue_session_id' => $revenueSession->id,
        ]);
        
        // AuditLog::create([
        //     'payment_id' => $payment->id,
        //     'action' => 'created',
        //     'details' => 'Init payment',
        //     'user_id' => $payment->user_id, // Người dùng thực hiện thanh toán
        // ]);

        // Update coupon
        if ($coupon) {
            $coupon->increment('used_count');
        }

        // Update admin account balance when payment is completed
        if ($result['success'] && $finalAmount > 0) {
            // $adminAccount = AdminAccount::firstOrCreate(
            //     ['admin_id' => Admins::first()->id], // Giả sử có 1 admin chính
            //     ['balance' => 0]
            // );
            // $adminAccount->increment('balance', $finalAmount);
        }

        DB::commit();

        $responseData =[
            'message' => 'Payment initiated successfully',
            'data' => $payment,
            'order' => $result['data'],
            'payment_method' => $paymentData['method']
        ];
        if ($paymentData['method'] === 'paypal' && isset($result['data']['approval_url'])) {
            $responseData['approval_url'] = $result['data']['approval_url'];
            $responseData['redirect_required'] = true;
        } else {
            $responseData['order'] = $result['data'];
        }
        return response()->json($responseData, 201);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Payment creation failed', ['error' => $e->getMessage()]);
        return response()->json([
            'message' => 'Failed to create payment',
            'error' => $e->getMessage()
        ], 500);
    }
}
    public function update(UpdatePaymentRequest $request, $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
        $payment->fill($request->validated());
        if (!$payment->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 400);
        }
        $payment->update($request->validated());
        return response()->json(['message' => 'Payment updated successfully', 'data' => $payment]);
    }

    public function destroy($id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
        $payment->delete();
        return response()->json(['message' => 'Payment deleted successfully']);
    }

    public function handleZaloPayCallback(Request $request): JsonResponse
    {
        $data = $request->input('data');
        $mac = $request->input('mac');

        // Verify MAC
        $calculatedMac = hash_hmac('sha256', $data, $this->zaloPayConfig['key2']);
        if ($mac !== $calculatedMac) {
            return response()->json(['message' => 'Invalid MAC'], 400);
        }

        $orderData = json_decode($data, true);
        $appTransId = $orderData['app_trans_id'];

        // Update payment status
        $payment = Payment::where('transaction_code', $appTransId)->first();
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->update([
            'status' => $orderData['status'] === 1 ? 'completed' : 'failed',
            // 'payment_date' => now(),
        ]);
        if ($orderData['status'] === 1 && $payment->status === 'completed') {
    $embedData = json_decode($orderData['embed_data'], true);
    $courseId = $embedData['course_id'];

    // Create enrollment if not already exists
    $enrollment = Enrollment::firstOrCreate(
        [
            'user_id' => $payment->user_id,
            'course_id' => $courseId,
        ],
        [
            'enrolled_at' => now(),
        ]
    );
}
        return response()->json(['message' => 'Payment status updated']);
    }

public function handleVNPayCallback(Request $request)
{
    // Lấy tất cả tham số từ query string
    $data = $request->query();

    // Log dữ liệu nhận được
    Log::debug('VNPay callback received', ['data' => $data]);

    // Khởi tạo VNPayGateway để xác minh
    // $gateway = new VNPayGateway();
    // $result = $gateway->verifyCallback($data);
    $data['success'] = true; // Giả lập kết quả thành công
    $data['transaction_code'] = $data['vnp_TxnRef'] ?? null; // Giả lập mã giao dịch
    $data['message'] = 'Transaction successful'; // Giả lập thông báo thành công
    $data['data'] = $data; // Giả lập dữ liệu trả về
    $result = $data;

    if (!$result['success']) {
        Log::error('VNPay callback verification failed', [
            'message' => $result['message'],
            'data' => $data
        ]);
        return response()->json(['message' => $result['message']], 400);
    }

    // Lấy transaction_code từ kết quả xác minh
    $txnRef = $result['transaction_code'];
    $payment = Payment::where('transaction_code', $txnRef)->first();

    if (!$payment) {
        Log::error('VNPay callback: Payment not found', ['txnRef' => $txnRef]);
        return response()->json(['message' => 'Payment not found'], 404);
    }

    // Kiểm tra trạng thái thanh toán đã hoàn tất chưa
    if ($payment->status === 'completed') {
        Log::info('VNPay callback: Payment already completed', ['txnRef' => $txnRef]);
        return response()->json(['message' => 'Payment already completed'], 200);
    }

    // Kiểm tra số tiền
    // $expectedAmount = $payment->amount * 100; // VNPay sử dụng đơn vị là cent (VND * 100)
    // if ($result['data']['vnp_Amount'] != $expectedAmount) {
    //     Log::error('VNPay callback: Invalid amount', [
    //         'expected' => $expectedAmount,
    //         'received' => $result['data']['vnp_Amount'],
    //         'txnRef' => $txnRef
    //     ]);
    //     $payment->update(['status' => 'failed']);
    //     return response()->json(['message' => 'Invalid amount'], 400);
    // }

    // Xác định trạng thái thanh toán
    $status = ($result['data']['vnp_ResponseCode'] === '00') ? 'completed' : 'failed';
try{
    // Bắt đầu giao dịch cơ sở dữ liệu
    DB::beginTransaction();
        // Cập nhật trạng thái thanh toán
        $payment->update([
            'status' => $status,
            // 'payment_date' => now(),
        ]);

        if ($status === 'completed') {
            // Tạo enrollment
            Enrollment::firstOrCreate(
                [
                    'user_id' => $payment->user_id,
                    'course_id' => $payment->course_id,
                ],
                [
                    'enrolled_at' => now(),
                ]
            );

            // Cập nhật mã giảm giá
            if ($payment->coupon_id) {
                $coupon = Coupon::find($payment->coupon_id);
                $coupon?->increment('used_count');
            }

            // Cập nhật doanh thu
            $revenueSession = RevenueSession::find($payment->revenue_session_id);
            if ($revenueSession) {
                $revenueSession->increment('total_revenue', $payment->amount);
            }

            // Cập nhật số dư admin
            // $adminAccount = AdminAccount::first();
            // if ($adminAccount && $payment->amount > 0) {
            //     $adminAccount->increment('balance', $payment->amount);
            // }
            // Cập nhật lịch sử thanh toán
            // AuditLog::create([
            //     'payment_id' => $payment->id,
            //     'action' => 'payment_completed',
            //     'details' => 'Payment completed successfully',
            //     'user_id' => $payment->user_id, // Người dùng thực hiện thanh toán
            // ]);
        }

        DB::commit();
        Log::info('VNPay callback processed', [
            'payment_id' => $payment->id,
            'status' => $status,
            'txnRef' => $txnRef
        ]);
        return redirect()->away('http://localhost:4200');
    }catch (\Exception $e) {
        DB::rollBack();
        Log::error('VNPay callback processing failed', [
            'error' => $e->getMessage(),
            'txnRef' => $txnRef
        ]);
        // return response()->json(['message' => 'Failed to process callback'], 500);
       return redirect()->away('http://localhost:4200');

    }
}

public function handleVNPayIPN(Request $request): JsonResponse
{
    $gateway = new VNPayGateway();
    $result = $gateway->verifyCallback($request->query());

    if (!$result['success']) {
        return response()->json([
            'RspCode' => '97',
            'Message' => $result['message']
        ], 400);
    }

    $txnRef = $result['transaction_code'];
    $payment = Payment::where('transaction_code', $txnRef)->first();

    if (!$payment) {
        return response()->json([
            'RspCode' => '01',
            'Message' => 'Order not found'
        ], 404);
    }

    if ($payment->final_amount * 100 != $result['data']['vnp_Amount']) {
        return response()->json([
            'RspCode' => '04',
            'Message' => 'Invalid amount'
        ], 400);
    }

    if ($payment->status === 'completed') {
        return response()->json([
            'RspCode' => '02',
            'Message' => 'Order already confirmed'
        ]);
    }

    $status = ($result['data']['vnp_ResponseCode'] ?? '99') === '00' ? 'completed' : 'failed';
    $payment->update([
        'status' => $status,
        // 'payment_date' => now(),
    ]);

    if ($status === 'completed') {
        $courseId = $result['data']['course_id'] ?? $payment->course_id;
        if ($courseId) {
            Enrollment::firstOrCreate(
                [
                    'user_id' => $payment->user_id,
                    'course_id' => $courseId,
                ],
                [
                    'enrolled_at' => now(),
                ]
            );
        }

        if ($payment->coupon_id) {
            $coupon = Coupon::find($payment->coupon_id);
            $coupon?->increment('used_count');
        }
    }

    return response()->json([
        'RspCode' => '00',
        'Message' => 'Confirm Success'
    ]);
}
/**
     * Kiểm tra trạng thái thanh toán của một payment
     *
     * @param int $payment_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPaymentStatus($payment_id)
    {
        try {
            // Tìm payment theo ID và đảm bảo thuộc về student đang đăng nhập
            $payment = Payment::where('id', $payment_id)
                ->where('user_id', Auth::id())
                ->first();

            // Kiểm tra xem payment có tồn tại không
            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment not found or you do not have permission to view this payment.'
                ], 404);
            }

            // Trả về thông tin trạng thái thanh toán
            return response()->json([
                'status' => 'success',
                'data' => [
                    'payment_id' => $payment->id,
                    'course_id' => $payment->course_id,
                    'amount' => $payment->amount,
                    'method' => $payment->method,
                    'status' => $payment->status,
                    'payment_date' => $payment->created_at,
                    'transaction_code' => $payment->transaction_code,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while checking payment status.'
            ], 500);
        }
    }
     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments =Payment::onlyTrashed()->paginate(10);
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment =Payment::onlyTrashed()->findOrFail($id);
        $enrollment->restore();
        return response()->json(['message' => 'Payment restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        $enrollment =Payment::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        return response()->json(['message' => 'Payment permanently deleted'], 200);
    }

    public function search(Request $request)
{
    $query = Payment::query();

    if ($request->filled('method')) {
        $query->where('method', $request->method);
    }

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('min_amount')) {
        $query->where('amount', '>=', $request->min_amount);
    }

    if ($request->filled('max_amount')) {
        $query->where('amount', '<=', $request->max_amount);
    }

    return response()->json($query->paginate(10));
}

/**
 * Handle PayPal payment success callback
 */

public function handlePayPalSuccess(Request $request)
{
    try {
        $token = $request->get('token'); // PayPal order ID
        $payerId = $request->get('PayerID');
        Log::info('PayPal Success Callback', [
            'token' => $token,
            'payerId' => $payerId,
            'all_params' => $request->all()
        ]);

        if (!$token || !$payerId) {
            Log::error('PayPal Success: Missing parameters', $request->all());
            return redirect()->away('http://localhost:4200/payment/failed');
        }

        // Tìm payment record
        $payment = Payment::where('transaction_code', $token)->first();

        if (!$payment) {
            Log::error('PayPal Success: Payment not found', ['token' => $token]);
            return redirect()->away('http://localhost:4200/payment/failed');
        }

        // Execute PayPal payment
        $gateway = new PayPalGateway();
        $result = $gateway->executePayment($token, $payerId);

        if (!$result['success']) {
            $payment->update(['status' => 'failed']);
            Log::error('PayPal Execute Failed', ['result' => $result, 'payment_id' => $payment->id]);
            return redirect()->away('http://localhost:4200/payment/failed');
        }

        DB::beginTransaction();
        try {
            // Update payment status
            $payment->update([
                'status' => 'completed',
                // 'payment_date' => now(),
            ]);

            // Create enrollment
            Enrollment::firstOrCreate(
                [
                    'user_id' => $payment->user_id,
                    'course_id' => $payment->course_id,
                ],
                [
                    'enrolled_at' => now(),
                ]
            );

            // Update revenue session
            $revenueSession = RevenueSession::find($payment->revenue_session_id);
            if ($revenueSession) {
                $revenueSession->increment('total_revenue', $payment->amount);
            }

            // Log audit
            // AuditLog::create([
            //     'payment_id' => $payment->id,
            //     'action' => 'payment_completed',
            //     'details' => 'PayPal payment completed - Money received in PayPal business account',
            //     'user_id' => $payment->user_id,
            // ]);

            DB::commit();

            Log::info('✅ PayPal payment completed - Money in business account', [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
                'course_id' => $payment->course_id,
                'amount' => $payment->amount,
                'transaction_code' => $payment->transaction_code
            ]);

            return redirect()->away('http://localhost:4200/my-course');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayPal payment completion failed', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id
            ]);
            return redirect()->away('http://localhost:4200/payment/failed');
        }

    } catch (\Exception $e) {
        Log::error('PayPal success handler error', [
            'error' => $e->getMessage(),
            'request' => $request->all()
        ]);
        return redirect()->away('http://localhost:4200/payment/failed');
    }
}
/**
 * Handle PayPal payment cancellation
 */
public function handlePayPalCancel(Request $request)
{
    try {
        $token = $request->get('token');
        
        Log::info('PayPal payment cancelled', ['token' => $token]);
        
        if ($token) {
            $payment = Payment::where('transaction_code', 'like', '%' . $token . '%')
                ->orWhere('transaction_code', $token)
                ->first();
                
            if ($payment) {
                $payment->update(['status' => 'failed']);
                
                // AuditLog::create([
                //     'payment_id' => $payment->id,
                //     'action' => 'payment_cancelled',
                //     'details' => 'PayPal payment cancelled by user',
                //     'user_id' => $payment->user_id,
                // ]);
            }
        }

        return redirect()->away('http://localhost:4200/payment/cancelled');

    } catch (\Exception $e) {
        Log::error('PayPal cancel handler error', [
            'error' => $e->getMessage(),
            'request' => $request->all()
        ]);
        return redirect()->away('http://localhost:4200/payment/failed');
    }
}

/**
 * Handle PayPal webhook notifications
 */
public function handlePayPalWebhook(Request $request): JsonResponse
{
    try {
        $payload = $request->getContent();
        $headers = $request->headers->all();
        
        Log::info('PayPal Webhook received', [
            'headers' => $headers,
            'payload' => $payload
        ]);

        $data = json_decode($payload, true);
        
        if (!$data || !isset($data['event_type'])) {
            return response()->json(['status' => 'error'], 400);
        }

        // Xử lý các event types khác nhau
        switch ($data['event_type']) {
            case 'CHECKOUT.ORDER.APPROVED':
                Log::info('PayPal Order Approved', $data);
                break;
                
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handlePaymentCaptureCompleted($data);
                break;
                
            case 'PAYMENT.CAPTURE.DENIED':
                $this->handlePaymentCaptureDenied($data);
                break;
                
            default:
                Log::info('Unhandled PayPal webhook event', ['event_type' => $data['event_type']]);
        }

        return response()->json(['status' => 'success'], 200);

    } catch (\Exception $e) {
        Log::error('PayPal webhook error', [
            'error' => $e->getMessage(),
            'payload' => $request->getContent()
        ]);
        return response()->json(['status' => 'error'], 500);
    }
}

/**
 * Handle payment capture completed webhook
 */
private function handlePaymentCaptureCompleted(array $data)
{
    try {
        $orderId = $data['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
        
        if (!$orderId) {
            Log::error('PayPal webhook: No order ID found', $data);
            return;
        }

        $payment = Payment::where('transaction_code', 'like', '%' . $orderId . '%')->first();
        
        if (!$payment) {
            Log::error('PayPal webhook: Payment not found', ['order_id' => $orderId]);
            return;
        }

        if ($payment->status === 'completed') {
            Log::info('PayPal webhook: Payment already completed', ['payment_id' => $payment->id]);
            return;
        }

        // Update payment status via webhook
        $payment->update([
            'status' => 'completed',
            // 'payment_date' => now(),
        ]);

        Log::info('PayPal webhook: Payment updated to completed', [
            'payment_id' => $payment->id,
            'order_id' => $orderId
        ]);

    } catch (\Exception $e) {
        Log::error('PayPal webhook capture completed error', [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
    }
}

/**
 * Handle payment capture denied webhook
 */
private function handlePaymentCaptureDenied(array $data)
{
    try {
        $orderId = $data['resource']['supplementary_data']['related_ids']['order_id'] ?? null;
        
        if (!$orderId) {
            return;
        }

        $payment = Payment::where('transaction_code', 'like', '%' . $orderId . '%')->first();
        
        if ($payment) {
            $payment->update(['status' => 'failed']);
            
            // AuditLog::create([
            //     'payment_id' => $payment->id,
            //     'action' => 'payment_denied',
            //     'details' => 'PayPal payment capture denied',
            //     'user_id' => $payment->user_id,
            // ]);
        }

    } catch (\Exception $e) {
        Log::error('PayPal webhook capture denied error', [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
    }
}
  public function showSuccess()
    {
        return view('payment.success');
    }

    /**
     * Trang hiển thị khi thanh toán thất bại
     */
    public function showFailure()
    {
        return view('payment.failed');
    }
}