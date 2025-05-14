<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Models\Payment;
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
                'payment_date',
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
        $payment = Payment::with(['user' => function ($query) {
            $query->select('id', 'final_cc_cname_DI', 'email');
        }, 'course' => function ($query) {
            $query->select('id', 'course_name', 'price');
        }, 'coupon' => function ($query) {
            $query->select('id', 'code', 'discount_type', 'discount_value');
        }])
            ->select([
                'id',
                'user_id',
                'course_id',
                'amount',
                'method',
                'transaction_code',
                'coupon_id',
                'status',
                'payment_date',
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

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        $payments = Payment::where('user_id', $user->id)
                          ->with(['course', 'coupon'])
                          ->get();

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

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        $payment = Payment::where('id', $id)
                      ->where('user_id', $user->id)
                      ->with(['course', 'coupon'])
                      ->first();

    if (!$payment) {
        return response()->json(['message' => 'Payment not found'], 404);
    }
        return response()->json([
            'message' => 'Payment retrieved successfully',
            'data' => $payment
        ]);
    }

  /**
     * Create a new payment.
     */
    public function store(array $paymentData, int $userId, int $courseId): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'student' || $user->id !== $userId) {
            return response()->json(['message' => 'Unauthorized: Only students can make payments'], 403);
        }

        // Validate payment data (ensure required fields)
        $requiredFields = ['amount', 'method'];
        foreach ($requiredFields as $field) {
            if (!isset($paymentData[$field])) {
                return response()->json(['message' => "Missing required field: {$field}"], 400);
            }
        }
        // Apply coupon if provided
        $finalAmount = $paymentData['amount'];
        $coupon = null;
        if (!empty($paymentData['coupon_id'])) {
            $coupon = Coupon::where('id', $paymentData['coupon_id'])
                ->where('is_active', true)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->whereColumn('used_count', '<', 'usage_limit')
                ->first();

            if (!$coupon) {
                return response()->json(['message' => 'Invalid or expired coupon'], 400);
            }

            if ($coupon->discount_type === 'percent') {
                $finalAmount = $finalAmount * (1 - $coupon->discount_value / 100);
            } else {
                $finalAmount = $finalAmount - $coupon->discount_value;
            }

            if ($finalAmount < 0) {
                $finalAmount = 0;
            }
        }

        // Select payment gateway
        $gatewayClass = $this->gateways[$paymentData['method']] ?? ZaloPayGateway::class;
        $gateway = new $gatewayClass();

        // Create order
        $orderData = [
            'user_id' => $userId,
            'course_id' => $courseId,
            'amount' => $paymentData['amount'],
            'final_amount' => $finalAmount,
            'coupon_id' => $paymentData['coupon_id'] ?? null,
        ];

        $result = $gateway->createOrder($orderData);

if (!$result['success']) {
        // Không tăng used_count của coupon khi createOrder thất bại
        return response()->json([
            'message' => $result['message'] ?? 'Failed to create payment order',
            'error' => $result
        ], 400);
    }

        // Store payment
        $paymentData = [
            'user_id' => $userId,
            'course_id' => $courseId,
            'amount' => $paymentData['amount'],
            'method' => $paymentData['method'],
            'transaction_code' => $result['transaction_code'],
            'coupon_id' => $paymentData['coupon_id'] ?? null,
            'status' => 'pending',
            'payment_date' => $paymentData['payment_date'] ?? null,
        ];
        if($result['success']!= true)
         {
            return response()->json([
                'message' => 'Failed to create payment order',
                'error' => $result['data']
            ], 400);
        }
        $payment = Payment::create($paymentData);
// Tăng used_count của coupon khi tạo payment thành công
    if ($coupon) {
        $coupon->increment('used_count');
    }
        return response()->json([
            'message' => 'Payment initiated successfully',
            'data' => $payment,
            'order' => $result['data']
        ], 201);
    }
 /**
     * Create a new payment for admin.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeAdmin(Request $request): JsonResponse
    {
        // Kiểm tra vai trò admin
        $admin = Auth::user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized: Only admins can create payments'
            ], 403);
        }

        // Validate request
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'course_id' => 'required|exists:courses,id',
            'amount' => 'required|integer|min:0',
            'method' => 'required|in:momo,zalopay,bank_transfer,vnpay,paypal',
            'transaction_code' => 'nullable|string|max:50|unique:payments,transaction_code',
            'coupon_id' => 'nullable|exists:coupons,id',
            'status' => 'required|in:pending,completed,failed',
            'payment_date' => 'nullable|date|before_or_equal:now'
        ]);

        // Kiểm tra user là student
        $user = User::find($validated['user_id']);
        if ($user->role !== 'student') {
            return response()->json([
                'message' => 'Only students can have payments created'
            ], 422);
        }

        // Kiểm tra enrollment
        $enrollment = Enrollment::where('user_id', $validated['user_id'])
            ->where('course_id', $validated['course_id'])
            ->where('status', 'active')
            ->first();
        if (!$enrollment) {
            return response()->json([
                'message' => 'The student is not enrolled in this course'
            ], 422);
        }

        // Kiểm tra coupon (nếu có)
        if (isset($validated['coupon_id'])) {
            $coupon = Coupon::find($validated['coupon_id']);
            if (!$coupon->is_active || ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit)) {
                return response()->json([
                    'message' => 'Coupon is invalid or has reached usage limit'
                ], 422);
            }
        }

        // Tạo payment
        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'user_id' => $validated['user_id'],
                'course_id' => $validated['course_id'],
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'transaction_code' => $validated['transaction_code'],
                'coupon_id' => $validated['coupon_id'],
                'status' => $validated['status'],
                'payment_date' => $validated['payment_date'] ?? now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Cập nhật used_count của coupon nếu có
            if (isset($validated['coupon_id']) && $validated['status'] === 'completed') {
                $coupon->increment('used_count');
            }

            // Ghi log hoạt động admin
            Admins::where('user_id', $admin->id)->update([
                'activity_log' => DB::raw("CONCAT(COALESCE(activity_log, ''), '\n', 'Created payment ID {$payment->id} for user {$validated['user_id']} at ', NOW())")
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment created successfully',
                'data' => $payment->load(['user', 'course', 'coupon'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function update(UpdatePaymentRequest $request, $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
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
            'payment_date' => now(),
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
            'status' => 'active',
        ]
    );
}
        return response()->json(['message' => 'Payment status updated']);
    }

public function handleVNPayCallback(Request $request): JsonResponse
{
    // $gateway = new VNPayGateway();
    // $result = $gateway->verifyCallback($request->query());

    // if (!$result['success']) {
    //     Log::error('VNPay callback: Verification failed', ['error' => $result['message']]);
    //     return response()->json(['message' => $result['message']], 400);
    // }
    $result = $request->all();
    $txnRef = $result['transaction_code'];
    $payment = Payment::where('transaction_code', $txnRef)->first();

    if (!$payment) {
        Log::error('VNPay callback: Payment not found', ['txnRef' => $txnRef]);
        return response()->json(['message' => 'Payment not found'], 404);
    }

    // $status = ($result['data']['vnp_ResponseCode'] ?? '99') === '00' ? 'completed' : 'failed';
    $status= $result['transaction_code'] ? 'completed' : 'failed';
    if ($payment->status === 'completed') {
        Log::info('VNPay callback: Payment already completed', ['txnRef' => $txnRef]);
        return response()->json(['message' => 'Payment already completed']);
    }
        $payment->update([
        'status' => $status,
        'payment_date' => now(),
    ]);

    if ($status === 'completed' && $payment->status === 'completed') {
        $courseId = $result['data']['course_id'] ?? $payment->course_id; // Lấy từ payment thay vì vnp_ExtraData
        if ($courseId) {
            Enrollment::firstOrCreate(
                [
                    'user_id' => $payment->user_id,
                    'course_id' => $courseId,
                ],
                [
                    'enrolled_at' => now(),
                    'status' => 'active',
                ]
            );
        }

        if ($payment->coupon_id) {
            $coupon = Coupon::find($payment->coupon_id);
            $coupon?->increment('used_count');
        }
    }

    Log::info('VNPay callback processed', ['payment_id' => $payment->id, 'status' => $status]);
    return response()->json(['message' => 'Payment status updated']);
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
        'payment_date' => now(),
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
                    'status' => 'active',
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
                    'payment_date' => $payment->payment_date,
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
}