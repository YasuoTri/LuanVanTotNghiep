<?php

namespace App\Http\Controllers;

use App\Models\RevenueSession;
use App\Models\Payment;
use App\Models\RevenueDistribution;
use App\Models\Course;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RevenueDistributePaypal extends Controller
{
    protected $paypalService;

    public function __construct()
    {
        $this->paypalService = new PayPalService();
    }

    public function distributeRevenue($sessionId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $session = RevenueSession::where('id', $sessionId)
                ->where('status', 'open')
                ->firstOrFail();

            // Kiểm tra xem đã đến cuối tháng chưa
            $sessionDate = Carbon::create($session->year, $session->month, 1);
            // if (!$sessionDate->endOfMonth()->isPast()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Cannot distribute revenue until the end of the month'
            //     ], 400);
            // }

            // Test PayPal connection trước
            if (!$this->paypalService->testConnection()) {
                return response()->json([
                    'success' => false,
                    'message' => 'PayPal connection failed. Cannot proceed with revenue distribution.'
                ], 500);
            }

            // Tính tổng doanh thu từ payments
            $totalRevenue = Payment::where('revenue_session_id', $session->id)
                ->where('status', 'completed')
                ->sum('amount');

            if ($totalRevenue <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No revenue to distribute'
                ], 400);
            }

            // Tính phần chia
            $adminShare = $totalRevenue * 0.3; // 30% cho admin
            $instructorShare = $totalRevenue * 0.7; // 70% cho instructor

            Log::info("💰 Starting revenue distribution for session {$sessionId}", [
                'total_revenue' => number_format($totalRevenue, 2) . ' VND',
                'admin_share' => number_format($adminShare, 2) . ' VND',
                'instructor_share' => number_format($instructorShare, 2) . ' VND'
            ]);

            // Cập nhật phiên
            // $session->update([
            //     'total_revenue' => $totalRevenue,
            //     'admin_share' => $adminShare,
            //     'instructor_share' => $instructorShare,
            //     'status' => 'processing', // Đang xử lý
            // ]);

            // Phân chia doanh thu cho từng instructor
            $instructorsRevenue = Payment::where('revenue_session_id', $session->id)
                ->where('status', 'completed')
                ->groupBy('course_id')
                ->selectRaw('course_id, SUM(amount) as total_amount')
                ->get();

                $distributionResults = [];
                $successCount = 0;
                $failCount = 0;

            foreach ($instructorsRevenue as $revenue) {
                $course = Course::with('instructors.user')->find($revenue->course_id);
                
                if (!$course) {
                    Log::warning("⚠️ Course not found: {$revenue->course_id}");
                    continue;
                }

                $instructors = $course->instructors;
                
                if ($instructors) {
                    Log::warning("⚠️ No instructors found for course: {$course->title}");
                    continue;
                }

                Log::info("👨‍🏫 Processing course '{$course->title}' with " . $instructors->count() . " instructors");

                foreach ($instructors as $instructor) {
                    // Chia đều cho các instructor của khóa học
                    $instructorAmount = ($revenue->total_amount * 0.7) / $instructors->count();

                    // Tạo bản ghi phân chia
                    $revenueDistribution = RevenueDistribution::create([
                        'revenue_session_id' => $session->id,
                        'instructor_id' => $instructor->id,
                        'course_id' => $revenue->course_id,
                        'revenue_amount' => $revenue->total_amount,
                        'instructor_share' => $instructorAmount,
                        'status' => 'pending',
                        'distributed_at' => now(),
                    ]);

                    // Gửi tiền qua PayPal
                    $payoutResult = $this->sendPayPalPayoutToInstructor($instructor, $instructorAmount, $revenueDistribution);
                    
                    $distributionResults[] = [
                        'instructor_id' => $instructor->id,
                        'instructor_name' => $instructor->name,
                        'course_title' => $course->title,
                        'amount_vnd' => $instructorAmount,
                        'amount_usd' => round($instructorAmount / 24000, 2),
                        'paypal_email' => "sb-iqclf44276453@personal.example.com",
                        'success' => $payoutResult['success'],
                        'message' => $payoutResult['message'],
                        'batch_id' => $payoutResult['batch_id'] ?? null
                    ];

                    if ($payoutResult['success']) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                }
            }

            // Gửi tiền cho Admin
            $adminPayoutResult = $this->sendPayPalPayoutToAdmin($adminShare, $session);

            // Cập nhật trạng thái session
            $finalStatus = ($failCount === 0) ? 'distributed' : 'partially_distributed';
            $session->update(['status' => $finalStatus]);

            DB::commit();

            Log::info("✅ Revenue distribution completed for session {$sessionId}", [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'admin_payout' => $adminPayoutResult['success'] ? 'Success' : 'Failed'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Revenue distribution completed',
                'data' => [
                    'session' => $session->fresh(),
                    'distribution_summary' => [
                        'total_instructors' => $successCount + $failCount,
                        'successful_payouts' => $successCount,
                        'failed_payouts' => $failCount,
                        'admin_payout' => $adminPayoutResult['success'] ? 'Success' : 'Failed'
                    ],
                    'distribution_details' => $distributionResults,
                    'admin_payout' => [
                        'amount_vnd' => $adminShare,
                        'amount_usd' => round($adminShare / 24000, 2),
                        'success' => $adminPayoutResult['success'],
                        'message' => $adminPayoutResult['message']
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('💥 Revenue distribution failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to distribute revenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gửi tiền PayPal cho instructor
     */
    private function sendPayPalPayoutToInstructor($instructor, $amount, $revenueDistribution): array
    {
        try {
            // Lấy PayPal email
            $paypalEmail ="sb-iqclf44276453@personal.example.com";
            
            if (!$paypalEmail) {
                $error = "No PayPal email found for instructor {$instructor->id}";
                Log::error("❌ " . $error);
                
                $revenueDistribution->update([
                    'status' => 'failed',
                    'error_message' => $error
                ]);
                
                return [
                    'success' => false,
                    'message' => $error
                ];
            }

            // Chuyển đổi VND sang USD
            $exchangeRate = 24000; // 1 USD = 24,000 VND
            $amountUSD = round($amount / $exchangeRate, 2);
            
            // Kiểm tra số tiền tối thiểu PayPal
            if ($amountUSD < 1.00) {
                $error = "Amount too small: \${$amountUSD} (minimum \$1.00)";
                Log::warning("⚠️ " . $error . " for instructor {$instructor->id}");
                
                $revenueDistribution->update([
                    'status' => 'failed',
                    'error_message' => $error
                ]);
                
                return [
                    'success' => false,
                    'message' => $error
                ];
            }

            Log::info("💸 Sending PayPal payout to instructor {$instructor->id}: {$amount} VND = \${$amountUSD} USD");
            
            // Gửi payout qua PayPal
            $response = $this->paypalService->sendPayout(
                $paypalEmail,
                $amountUSD,
                'USD',
                "Revenue distribution for course: " . ($revenueDistribution->course->title ?? 'Course')
            );

            // Cập nhật thành công
            $revenueDistribution->update([
                'status' => 'completed',
                'paypal_batch_id' => $response->result->batch_header->payout_batch_id ?? null,
                'paypal_response' => json_encode($response->result)
            ]);

            Log::info("✅ PayPal payout SUCCESS for instructor {$instructor->id}: \${$amountUSD} to {$paypalEmail}");
            
            return [
                'success' => true,
                'message' => "Payout sent successfully: \${$amountUSD}",
                'batch_id' => $response->result->batch_header->payout_batch_id ?? null
            ];

        } catch (\Exception $e) {
            $error = "PayPal payout failed: " . $e->getMessage();
            Log::error("❌ {$error} for instructor {$instructor->id}");
            
            $revenueDistribution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $error
            ];
        }
    }

    /**
     * Gửi tiền PayPal cho Admin
     */
    private function sendPayPalPayoutToAdmin($adminShare, $session): array
    {
        try {
            $adminEmail = "sb-tj2x544276417@business.example.com";
            
            if (!$adminEmail) {
                Log::warning("⚠️ No admin PayPal email configured");
                return [
                    'success' => false,
                    'message' => 'No admin PayPal email configured'
                ];
            }

            $exchangeRate = 24000;
            $amountUSD = round($adminShare / $exchangeRate, 2);
            
            if ($amountUSD < 1.00) {
                Log::warning("⚠️ Admin amount too small: \${$amountUSD}");
                return [
                    'success' => false,
                    'message' => "Admin amount too small: \${$amountUSD}"
                ];
            }

            Log::info("💸 Sending admin PayPal payout: {$adminShare} VND = \${$amountUSD} USD");
            
            $response = $this->paypalService->sendPayout(
                $adminEmail,
                $amountUSD,
                'USD',
                "Admin revenue share for session {$session->id} ({$session->month}/{$session->year})"
            );

            Log::info("✅ Admin PayPal payout SUCCESS: \${$amountUSD} to {$adminEmail}");
            
            return [
                'success' => true,
                'message' => "Admin payout sent: \${$amountUSD}",
                'batch_id' => $response->result->batch_header->payout_batch_id ?? null
            ];

        } catch (\Exception $e) {
            Log::error("❌ Admin PayPal payout FAILED: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Admin payout failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lấy danh sách revenue sessions
     */
    public function index()
    {
        try {
            $sessions = RevenueSession::with(['revenueDistributions.instructor', 'revenueDistributions.course'])
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $sessions,
                'message' => 'Revenue sessions retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching revenue sessions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch revenue sessions'
            ], 500);
        }
    }

    /**
     * Tạo session cho tháng hiện tại
     */
    public function createMonthlySession(Request $request)
    {
        try {
            $year = $request->input('year', now()->year);
            $month = $request->input('month', now()->month);

            // Kiểm tra session đã tồn tại chưa
            $existingSession = RevenueSession::where('year', $year)
                ->where('month', $month)
                ->first();

            if ($existingSession) {
                return response()->json([
                    'success' => false,
                    'message' => "Revenue session for {$month}/{$year} already exists",
                    'data' => $existingSession
                ], 409);
            }

            // Tạo session mới
            $session = RevenueSession::create([
                'year' => $year,
                'month' => $month,
                'status' => 'open',
                'total_revenue' => 0,
                'admin_share' => 0,
                'instructor_share' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Gán payments của tháng này vào session
                        // Gán payments của tháng này vào session
            $paymentsUpdated = Payment::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->where('status', 'completed')
                ->whereNull('revenue_session_id')
                ->update(['revenue_session_id' => $session->id]);

            Log::info("✅ Created revenue session {$session->id} for {$month}/{$year} with {$paymentsUpdated} payments");

            return response()->json([
                'success' => true,
                'data' => $session,
                'payments_assigned' => $paymentsUpdated,
                'message' => "Revenue session created for {$month}/{$year}"
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating revenue session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create revenue session'
            ], 500);
        }
    }

    /**
     * Xem chi tiết session
     */
    public function show($id)
    {
        try {
            $session = RevenueSession::with([
                'revenueDistributions.instructor.user',
                'revenueDistributions.course',
                'payments'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $session,
                'message' => 'Revenue session details retrieved'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching revenue session: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Revenue session not found'
            ], 404);
        }
    }

    /**
     * Lấy thống kê revenue
     */
    public function getStats()
    {
        try {
            $stats = [
                'total_sessions' => RevenueSession::count(),
                'open_sessions' => RevenueSession::where('status', 'open')->count(),
                'distributed_sessions' => RevenueSession::where('status', 'distributed')->count(),
                'processing_sessions' => RevenueSession::where('status', 'processing')->count(),
                'partially_distributed_sessions' => RevenueSession::where('status', 'partially_distributed')->count(),
                'total_revenue' => RevenueSession::sum('total_revenue'),
                'total_admin_share' => RevenueSession::sum('admin_share'),
                'total_instructor_share' => RevenueSession::sum('instructor_share'),
                'current_month_session' => RevenueSession::where('year', now()->year)
                    ->where('month', now()->month)
                    ->first(),
                'recent_sessions' => RevenueSession::orderBy('year', 'desc')
                    ->orderBy('month', 'desc')
                    ->limit(5)
                    ->get(),
                'paypal_connection' => $this->paypalService->testConnection()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Revenue statistics retrieved'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching revenue stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch revenue statistics'
            ], 500);
        }
    }

    /**
     * Retry failed payouts
     */
    public function retryFailedPayouts($sessionId)
    {
        try {
            $session = RevenueSession::findOrFail($sessionId);
            
            // Lấy các distribution bị failed
            $failedDistributions = RevenueDistribution::where('revenue_session_id', $sessionId)
                ->where('status', 'failed')
                ->with(['instructor.user', 'course'])
                ->get();

            if ($failedDistributions->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No failed payouts found for this session'
                ], 400);
            }

            $retryResults = [];
            $successCount = 0;
            $failCount = 0;

            foreach ($failedDistributions as $distribution) {
                // Reset status
                $distribution->update(['status' => 'pending']);
                
                // Retry payout
                $payoutResult = $this->sendPayPalPayoutToInstructor(
                    $distribution->instructor, 
                    $distribution->instructor_share, 
                    $distribution
                );
                
                $retryResults[] = [
                    'distribution_id' => $distribution->id,
                    'instructor_name' => $distribution->instructor->name,
                    'amount' => $distribution->instructor_share,
                    'success' => $payoutResult['success'],
                    'message' => $payoutResult['message']
                ];

                if ($payoutResult['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }

            // Cập nhật status session nếu tất cả thành công
            if ($failCount === 0) {
                $session->update(['status' => 'distributed']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Retry completed',
                'data' => [
                    'total_retried' => count($retryResults),
                    'successful' => $successCount,
                    'failed' => $failCount,
                    'details' => $retryResults
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrying failed payouts: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry payouts'
            ], 500);
        }
    }

    /**
     * Test PayPal connection
     */
    public function testPayPalConnection()
    {
        try {
            $connected = $this->paypalService->testConnection();
            
            return response()->json([
                'success' => $connected,
                'message' => $connected ? 'PayPal connection successful' : 'PayPal connection failed',
                'config' => [
                    'mode' => config('paypal.mode'),
                    'client_id_preview' => substr(config('paypal.client_id'), 0, 10) . '...',
                    'admin_email' => "sb-tj2x544276417@business.example.com"
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PayPal connection error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force distribute (cho testing)
     */
    public function forceDistribute($id)
    {
        try {
            $session = RevenueSession::findOrFail($id);

            // Reset status để có thể distribute lại
            $session->update(['status' => 'open']);

            // Reset tất cả distributions về pending
            RevenueDistribution::where('revenue_session_id', $id)
                ->update(['status' => 'pending']);

            // Gọi lại distribute
            return $this->distributeRevenue($id);

        } catch (\Exception $e) {
            Log::error('Error force distributing revenue: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to force distribute revenue'
            ], 500);
        }
    }
}

