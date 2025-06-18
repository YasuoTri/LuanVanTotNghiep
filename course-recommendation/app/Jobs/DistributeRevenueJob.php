<?php

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

namespace App\Jobs;

use App\Models\RevenueSession;
use App\Models\Payment;
use App\Models\RevenueDistribution;
use App\Models\AdminAccount;
use App\Models\Course;
use App\Models\InstructorAccount;
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

    public function handle()
    {
        // Tìm RevenueSession mở và thuộc tháng trước
        $sessions = RevenueSession::where('status', 'open')
            ->whereRaw('DATE_FORMAT(CONCAT(year, "-", month, "-01"), "%Y-%m-%d") < LAST_DAY(NOW())')
            ->get();
        Log::info('Found sessions: ' . $sessions->count());

        foreach ($sessions as $session) {
            // Kiểm tra cuối tháng
            $sessionDate = Carbon::create($session->year, $session->month, 1);
            if (!$sessionDate->endOfMonth()->isPast()) {
                Log::info("Session {$session->id} not yet at end of month");
                continue;
            }

            // Lấy Payment
            $payments = Payment::where('revenue_session_id', $session->id)
                ->where('status', 'completed')
                ->get();
            Log::info('Payments for session ' . $session->id . ': ' . $payments->count());

            if ($payments->isEmpty()) {
                Log::info("No payments found for session {$session->id}");
                continue;
            }

            $totalRevenue = $payments->sum('amount');
            $adminShare = $totalRevenue * 0.3;
            $instructorShare = $totalRevenue * 0.7;

            // Cập nhật RevenueSession
            $session->update([
                'total_revenue' => $totalRevenue,
                'admin_share' => $adminShare,
                'instructor_share' => $instructorShare,
                'status' => 'distributed',
            ]);

            // Phân phối cho instructor
            $instructorsRevenue = Payment::where('revenue_session_id', $session->id)
                ->where('status', 'completed')
                ->groupBy('course_id')
                ->selectRaw('course_id, SUM(amount) as total_amount')
                ->get();

            foreach ($instructorsRevenue as $revenue) {
                $course = Course::find($revenue->course_id);
                $instructors = $course->instructors; // Lấy tất cả instructor

                foreach ($instructors as $instructor) {
                    $instructorAmount = ($revenue->total_amount * 0.7) / $instructors->count(); // Chia đều nếu nhiều instructor

                    RevenueDistribution::create([
                        'revenue_session_id' => $session->id,
                        'instructor_id' => $instructor->id,
                        'course_id' => $revenue->course_id,
                        'revenue_amount' => $revenue->total_amount,
                        'instructor_share' => $instructorAmount,
                        'status' => 'pending',
                        'distributed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // $account = InstructorAccount::firstOrCreate(
                    //     ['instructor_id' => $instructor->id],
                    //     ['balance' => 0]
                    // );
                    // $account->increment('balance', $instructorAmount);
                }
            }

            // Cập nhật AdminAccount
            // $adminAccount = AdminAccount::first();
            // if ($adminAccount) {
            //     $adminAccount->update(['balance' => $adminAccount->balance + $adminShare]);
            // }
        }
    }
}