<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    private $zaloPayConfig;

    public function __construct()
    {
        $this->zaloPayConfig = [
            'app_id' => env('ZALOPAY_APP_ID', 'your_zalopay_app_id'),
            'key1' => env('ZALOPAY_KEY1', 'your_zalopay_key1'),
            'key2' => env('ZALOPAY_KEY2', 'your_zalopay_key2'),
            'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-open.zalopay.vn/v2/create'),
        ];
    }

    public function index(): JsonResponse
    {
        $payments = Payment::all();
        return response()->json(['data' => $payments]);
    }

    public function show($id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
        return response()->json(['data' => $payment]);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Prepare ZaloPay order
        $order = [
            'app_id' => $this->zaloPayConfig['app_id'],
            'app_user' => 'user_' . $data['user_id'],
            'app_time' => now()->timestamp * 1000,
            'amount' => $data['amount'],
            'app_trans_id' => now()->format('ymd') . '_' . Str::random(6),
            'embed_data' => json_encode(['course_id' => $data['course_id'], 'coupon_id' => $data['coupon_id'] ?? null]),
            'item' => json_encode([['course_id' => $data['course_id'], 'name' => 'Course Payment', 'amount' => $data['amount']]]),
            'bank_code' => '',
            'description' => 'Payment for course #' . $data['course_id'],
            'callback_url' => env('ZALOPAY_CALLBACK_URL', 'https://your-api.com/api/payments/callback'),
        ];

        // Generate MAC
        $order['mac'] = hash_hmac('sha256', implode('|', [
            $order['app_id'], $order['app_trans_id'], $order['app_user'], $order['amount'],
            $order['app_time'], $order['embed_data'], $order['item']
        ]), $this->zaloPayConfig['key1']);

        // Call ZaloPay API
        $response = Http::post($this->zaloPayConfig['endpoint'], $order);
        $result = $response->json();

        if ($result['return_code'] !== 1) {
            return response()->json(['message' => 'Failed to create ZaloPay order', 'error' => $result], 400);
        }

        // Store payment
        $paymentData = [
            'user_id' => $data['user_id'],
            'course_id' => $data['course_id'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'transaction_code' => $order['app_trans_id'],
            'coupon_id' => $data['coupon_id'] ?? null,
            'status' => 'pending',
            'payment_date' => $data['payment_date'] ?? null,
        ];
        $payment = Payment::create($paymentData);

        return response()->json([
            'message' => 'Payment initiated successfully',
            'data' => $payment,
            'zalopay_order' => $result
        ], 201);
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

        return response()->json(['message' => 'Payment status updated']);
    }
}