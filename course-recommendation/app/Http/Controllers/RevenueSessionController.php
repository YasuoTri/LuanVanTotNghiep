<?php

namespace App\Http\Controllers;

use App\Models\RevenueSession;
use App\Models\RevenueDistribution;
use App\Models\Payment;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\InstructorAccount;
use App\Models\AdminAccount;
use App\Services\PaymentGateways\VNPayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RevenueSessionController extends Controller
{
    /**
     * Tạo phiên doanh thu mới vào đầu tháng.
     */
    public function createMonthlySession(): JsonResponse
    {
        try {
            $currentMonth = now()->month;
            $currentYear = now()->year;

            // Kiểm tra xem phiên đã tồn tại chưa
            $existingSession = RevenueSession::where('month', $currentMonth)
                ->where('year', $currentYear)
                ->first();

            if ($existingSession) {
                return response()->json([
                    'message' => 'Revenue session for this month already exists',
                    'data' => $existingSession
                ], 409);
            }

            // Tạo phiên mới
            $session = RevenueSession::create([
                'month' => $currentMonth,
                'year' => $currentYear,
                'total_revenue' => 0,
                'admin_share' => 0,
                'instructor_share' => 0,
                'status' => 'open',
            ]);

            return response()->json([
                'message' => 'Revenue session created successfully',
                'data' => $session
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create revenue session', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to create revenue session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Phân chia doanh thu vào cuối tháng.
     */
    public function distributeRevenue($sessionId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $session = RevenueSession::where('id', $sessionId)
                ->where('status', 'open')
                ->firstOrFail();

            // Kiểm tra xem đã đến cuối tháng chưa
            $sessionDate = Carbon::create($session->year, $session->month, 1);
            if (!$sessionDate->endOfMonth()->isPast()) {
                return response()->json([
                    'message' => 'Cannot distribute revenue until the end of the month'
                ], 400);
            }

            // Tính tổng doanh thu từ payments
            $totalRevenue = Payment::where('revenue_session_id', $session->id)
                ->where('status', 'completed')
                ->sum('amount');

            if ($totalRevenue <= 0) {
                return response()->json([
                    'message' => 'No revenue to distribute'
                ], 400);
            }

            // Tính phần chia
            $adminShare = $totalRevenue * 0.3; // 30% cho admin
            $instructorShare = $totalRevenue * 0.7; // 70% cho instructor

            // Cập nhật phiên
            $session->update([
                'total_revenue' => $totalRevenue,
                'admin_share' => $adminShare,
                'instructor_share' => $instructorShare,
                'status' => 'closed',
            ]);

            // Phân chia doanh thu cho từng instructor
            $instructorsRevenue = Payment::where('revenue_session_id', $session->id)
                ->where('status', 'completed')
                ->groupBy('course_id')
                ->selectRaw('course_id, SUM(amount) as total_amount')
                ->get();

            foreach ($instructorsRevenue as $revenue) {
                $course = Course::find($revenue->course_id);
                $instructor = $course->instructors()->first(); // Giả sử 1 khóa học có 1 instructor

                if ($instructor) {
                    $instructorAmount = $revenue->total_amount * 0.7; // 70% cho instructor

                    // Tạo bản ghi phân chia
                    RevenueDistribution::create([
                        'revenue_session_id' => $session->id,
                        'instructor_id' => $instructor->id,
                        'course_id' => $revenue->course_id,
                        'revenue_amount' => $revenue->total_amount,
                        'instructor_share' => $instructorAmount,
                        'status' => 'pending',
                    ]);

                    // Cập nhật số dư tài khoản instructor
                    // $instructorAccount = InstructorAccount::firstOrCreate(
                    //     ['instructor_id' => $instructor->id],
                    //     ['balance' => 0]
                    // );
                    // $instructorAccount->increment('balance', $instructorAmount);
                }
            }

            // // Cập nhật số dư tài khoản admin
            // $adminAccount = AdminAccount::first();
            // if ($adminAccount) {
            //     $adminAccount->increment('balance', $adminShare);
            // }

            // Đánh dấu phiên đã phân phối
            $session->update(['status' => 'distributed']);

            DB::commit();

            // Gọi API thanh toán để chuyển tiền cho instructor (giả sử dùng VNPay)
            $this->transferToInstructors($session->id);

            return response()->json([
                'message' => 'Revenue distributed successfully',
                'data' => $session
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Revenue distribution failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to distribute revenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Chuyển tiền cho instructors qua API thanh toán.
     */
    protected function transferToInstructors($sessionId)
    {
        $distributions = RevenueDistribution::where('revenue_session_id', $sessionId)
            ->where('status', 'pending')
            ->get();

        foreach ($distributions as $distribution) {
            // $instructorAccount = InstructorAccount::where('instructor_id', $distribution->instructor_id)->first();
            // if (!$instructorAccount || !$instructorAccount->bank_account_number) {
            //     Log::warning('Instructor account not configured', ['instructor_id' => $distribution->instructor_id]);
            //     continue;
            // }

            // Giả sử tích hợp API VNPay để chuyển khoản
            try {
                $gateway = new VNPayGateway();
                $transferResult = $gateway->transfer([
                    'amount' => $distribution->instructor_share,
                    'description' => "Revenue share for session {$sessionId}",
                ]);

                if ($transferResult['success']) {
                    $distribution->update([
                        'status' => 'completed',
                        'transaction_code' => $transferResult['transaction_code'],
                        'distributed_at' => now(),
                    ]);
                } else {
                    $distribution->update(['status' => 'failed']);
                    Log::error('Transfer failed', ['instructor_id' => $distribution->instructor_id, 'error' => $transferResult['message']]);
                }
            } catch (\Exception $e) {
                $distribution->update(['status' => 'failed']);
                Log::error('Transfer failed', ['instructor_id' => $distribution->instructor_id, 'error' => $e->getMessage()]);
            }
        }
    }
}