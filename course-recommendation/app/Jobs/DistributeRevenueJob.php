<?php
//
// namespace App\Jobs;

// use App\Http\Controllers\RevenueSessionController;
// use App\Models\RevenueSession;
// use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Bus\Dispatchable;
// use Illuminate\Queue\InteractsWithQueue;
// use Illuminate\Queue\SerializesModels;

// class DistributeRevenueJob implements ShouldQueue
// {
//     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

//     public function handle()
//     {
//         $controller = new RevenueSessionController();
//         $session = RevenueSession::where('status', 'open')
//             ->whereRaw('DATE_FORMAT(CONCAT(year, "-", month, "-01"), "%Y-%m-%d") < LAST_DAY(NOW())')
//             ->first();

//         if ($session) {
//             $controller->distributeRevenue($session->id);
//         }
//     }
// }

// namespace App\Jobs;

// use App\Models\RevenueSession;
// use App\Models\Payment;
// use App\Models\RevenueDistribution;
// use App\Models\AdminAccount;
// use App\Models\Course;
// use App\Models\InstructorAccount;
// use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Bus\Dispatchable;
// use Illuminate\Queue\InteractsWithQueue;
// use Illuminate\Queue\SerializesModels;
// use Illuminate\Support\Facades\Log;
// use Carbon\Carbon;

// class DistributeRevenueJob implements ShouldQueue
// {
//     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

//     public function handle()
//     {
//         // Tìm RevenueSession mở và thuộc tháng trước
//         $sessions = RevenueSession::where('status', 'open')
//             ->whereRaw('DATE_FORMAT(CONCAT(year, "-", month, "-01"), "%Y-%m-%d") < LAST_DAY(NOW())')
//             ->get();
//         Log::info('Found sessions: ' . $sessions->count());

//         foreach ($sessions as $session) {
//             // Kiểm tra cuối tháng
//             $sessionDate = Carbon::create($session->year, $session->month, 1);
//             if (!$sessionDate->endOfMonth()->isPast()) {
//                 Log::info("Session {$session->id} not yet at end of month");
//                 continue;
//             }

//             // Lấy Payment
//             $payments = Payment::where('revenue_session_id', $session->id)
//                 ->where('status', 'completed')
//                 ->get();
//             Log::info('Payments for session ' . $session->id . ': ' . $payments->count());

//             if ($payments->isEmpty()) {
//                 Log::info("No payments found for session {$session->id}");
//                 continue;
//             }

//             $totalRevenue = $payments->sum('amount');
//             $adminShare = $totalRevenue * 0.3;
//             $instructorShare = $totalRevenue * 0.7;

//             // Cập nhật RevenueSession
//             $session->update([
//                 'total_revenue' => $totalRevenue,
//                 'admin_share' => $adminShare,
//                 'instructor_share' => $instructorShare,
//                 'status' => 'distributed',
//             ]);

//             // Phân phối cho instructor
//             $instructorsRevenue = Payment::where('revenue_session_id', $session->id)
//                 ->where('status', 'completed')
//                 ->groupBy('course_id')
//                 ->selectRaw('course_id, SUM(amount) as total_amount')
//                 ->get();

//             foreach ($instructorsRevenue as $revenue) {
//                 $course = Course::find($revenue->course_id);
//                 $instructors = $course->instructors; // Lấy tất cả instructor

//                 foreach ($instructors as $instructor) {
//                     $instructorAmount = ($revenue->total_amount * 0.7) / $instructors->count(); // Chia đều nếu nhiều instructor

//                     RevenueDistribution::create([
//                         'revenue_session_id' => $session->id,
//                         'instructor_id' => $instructor->id,
//                         'course_id' => $revenue->course_id,
//                         'revenue_amount' => $revenue->total_amount,
//                         'instructor_share' => $instructorAmount,
//                         'status' => 'pending',
//                         'distributed_at' => now(),
//                         'created_at' => now(),
//                         'updated_at' => now(),
//                     ]);

//                     // $account = InstructorAccount::firstOrCreate(
//                     //     ['instructor_id' => $instructor->id],
//                     //     ['balance' => 0]
//                     // );
//                     // $account->increment('balance', $instructorAmount);
//                 }
//             }

//             // Cập nhật AdminAccount
//             // $adminAccount = AdminAccount::first();
//             // if ($adminAccount) {
//             //     $adminAccount->update(['balance' => $adminAccount->balance + $adminShare]);
//             // }
//         }
//     }
// }
//

namespace App\Jobs;

use App\Models\RevenueSession;
use App\Models\Payment;
use App\Models\RevenueDistribution;
use App\Models\Course;
use App\Services\PayPalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DistributeRevenueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $paypalService;

    public function __construct()
    {
        $this->paypalService = new PayPalService();
    }

    public function handle()
    {
        Log::info('🚀 Starting Revenue Distribution Job with PayPal Direct API');

        try {
            // Test PayPal connection first
            if (!$this->paypalService->testConnection()) {
                Log::error('❌ PayPal connection failed. Aborting revenue distribution.');
                return;
            }

            // Tìm RevenueSession mở và thuộc tháng trước
            $sessions = RevenueSession::where('status', 'open')
                ->whereRaw('DATE_FORMAT(CONCAT(year, "-", month, "-01"), "%Y-%m-%d") < LAST_DAY(NOW())')
                ->get();
            
            Log::info('📊 Found sessions: ' . $sessions->count());

            foreach ($sessions as $session) {
                $this->processSession($session);
            }

            Log::info('🎉 Revenue Distribution Job Completed Successfully');

        } catch (\Exception $e) {
            Log::error('💥 Revenue Distribution Job Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function processSession($session)
    {
        Log::info("🔄 Processing session {$session->id} for {$session->month}/{$session->year}");

        // Kiểm tra cuối tháng
        $sessionDate = Carbon::create($session->year, $session->month, 1);
        if (!$sessionDate->endOfMonth()->isPast()) {
            Log::info("⏰ Session {$session->id} not yet at end of month");
            return;
        }

        // Lấy Payment
        $payments = Payment::where('revenue_session_id', $session->id)
            ->where('status', 'completed')
            ->get();
        
        Log::info("💰 Payments for session {$session->id}: " . $payments->count());

        if ($payments->isEmpty()) {
            Log::info("❌ No payments found for session {$session->id}");
            return;
        }

        $totalRevenue = $payments->sum('amount');
        $adminShare = $totalRevenue * 0.3;
        $instructorShare = $totalRevenue * 0.7;

        // Log::info("💵 Revenue breakdown for session {$session->id}:", [
        //     'total' => number_format($totalRevenue, 2) . ' VND',
        //     'admin_share' => number_format($adminShare, 2) . ' VND',
        //     'instructor_share' => number_format($instructorShare, 2) . ' VND'
        // ]);

        // Cập nhật RevenueSession
        $session->update([
            'total_revenue' => $totalRevenue,
            'status' => 'distributed',
        ]);

        // Phân phối cho instructor
        $this->distributeToInstructors($session);

        // Gửi tiền cho Admin
        $this->sendAdminPayout($adminShare, $session);
    }

    private function distributeToInstructors($session)
    {
        $instructorsRevenue = Payment::where('revenue_session_id', $session->id)
            ->where('status', 'completed')
            ->groupBy('course_id')
            ->selectRaw('course_id, SUM(amount) as total_amount')
            ->get();

        $successCount = 0;
        $failCount = 0;

        foreach ($instructorsRevenue as $revenue) {
            $course = Course::find($revenue->course_id);
            if (!$course) {
                Log::warning("⚠️ Course not found: {$revenue->course_id}");
                continue;
            }

            $instructors = $course->instructors;
            Log::info("👨‍🏫 Course '{$course->title}' has " . $instructors->count() . " instructors");

            foreach ($instructors as $instructor) {
                $instructorAmount = ($revenue->total_amount * 0.7) / $instructors->count();

                $revenueDistribution = RevenueDistribution::create([
                    'revenue_session_id' => $session->id,
                    'instructor_id' => $instructor->id,
                    'course_id' => $revenue->course_id,
                    'instructor_share' => $instructorAmount,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Gửi tiền qua PayPal
                if ($this->sendPayPalPayout($instructor, $instructorAmount, $revenueDistribution)) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
        }

        Log::info("📊 Instructor payouts for session {$session->id}:", [
            'success' => $successCount,
            'failed' => $failCount,
            'total' => $successCount + $failCount
        ]);
    }

    private function sendPayPalPayout($instructor, $amount, $revenueDistribution)
    {
        try {
            // Lấy PayPal email
            $paypalEmail = $instructor->paypal_email ?? 
                          $instructor->user->paypal_email ?? 
                          $instructor->user->email;
            
            if (!$paypalEmail) {
                Log::error("❌ No PayPal email found for instructor {$instructor->id}");
                $revenueDistribution->update([
                    'status' => 'failed', 
                    'error_message' => 'No PayPal email found'
                ]);
                return false;
            }

            // Chuyển đổi từ VND sang USD (tỷ giá 1 USD = 24000 VND)
            $exchangeRate = 24000;
            $amountUSD = round($amount / $exchangeRate, 2);
            
            // Kiểm tra số tiền tối thiểu (PayPal yêu cầu tối thiểu $1.00)
            if ($amountUSD < 1.00) {
                Log::warning("⚠️ Amount too small for PayPal: \${$amountUSD} for instructor {$instructor->id}");
                $revenueDistribution->update([
                    'status' => 'failed',
                    'error_message' => "Amount too small: \${$amountUSD} (minimum \$1.00)"
                ]);
                return false;
            }

            Log::info("💸 Sending payout to instructor {$instructor->id}: {$amount} VND = \${$amountUSD} USD");
            
            $response = $this->paypalService->sendPayout(
                $paypalEmail,
                $amountUSD,
                'USD',
                "Revenue distribution for course: " . ($revenueDistribution->course->title ?? 'Unknown Course')
            );

            $revenueDistribution->update([
                'status' => 'completed',
                'paypal_batch_id' => $response->result->batch_header->payout_batch_id ?? null,
                'paypal_response' => json_encode($response->result)
            ]);

            Log::info("✅ PayPal payout SUCCESS for instructor {$instructor->id}: \${$amountUSD} to {$paypalEmail}");
            return true;

        } catch (\Exception $e) {
            Log::error("❌ PayPal payout FAILED for instructor {$instructor->id}: " . $e->getMessage());
            
            $revenueDistribution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    private function sendAdminPayout($adminShare, $session)
    {
        try {
            $adminEmail = config('app.admin_paypal_email');
            if (!$adminEmail) {
                Log::warning("⚠️ No admin PayPal email configured");
                return false;
            }

            $exchangeRate = 24000;
            $amountUSD = round($adminShare / $exchangeRate, 2);
            
            if ($amountUSD < 1.00) {
                Log::warning("⚠️ Admin amount too small: \${$amountUSD}");
                return false;
            }

            Log::info("💸 Sending admin payout: {$adminShare} VND = \${$amountUSD} USD");
            
            $response = $this->paypalService->sendPayout(
                $adminEmail,
                $amountUSD,
                'USD',
                "Admin revenue share for session {$session->id} ({$session->month}/{$session->year})"
            );

            Log::info("✅ Admin PayPal payout SUCCESS: \${$amountUSD} to {$adminEmail}");
            return true;

        } catch (\Exception $e) {
            Log::error("❌ Admin PayPal payout FAILED: " . $e->getMessage());
            return false;
        }
    }
}
