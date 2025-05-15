<?php

namespace Tests\Feature;

use App\Jobs\DistributeRevenueJob;
use App\Models\RevenueSession;
use App\Models\Payment;
use App\Models\AdminAccount;
use App\Models\InstructorAccount;
use App\Models\RevenueDistribution;
use App\Models\Course;
use App\Models\Instructors;
use App\Models\User;
use App\Models\Admins; // Chỉ dùng Admins
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DistributeRevenueJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributes_revenue_correctly(): void
    {
        // Arrange: Thiết lập ngày cuối tháng
        Carbon::setTestNow(Carbon::create(2025, 5, 31));

        // Tạo dữ liệu giả
        $session = RevenueSession::create([
            'month' => 5,
            'year' => 2025,
            'total_revenue' => 0,
            'admin_share' => 0,
            'instructor_share' => 0,
            'status' => 'open',
            'created_at' => Carbon::create(2025, 5, 1),
            'updated_at' => Carbon::create(2025, 5, 1),
        ]);

        $instructor = Instructors::factory()->create();
        $course = Course::factory()->create();
        $course->instructors()->attach($instructor->id);

        $user = User::factory()->create(['role' => 'student']);
        Payment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => 1000000,
            'method' => 'vnpay',
            'status' => 'completed',
            'revenue_session_id' => $session->id,
            'payment_date' => Carbon::create(2025, 5, 15),
            'created_at' => Carbon::create(2025, 5, 15),
            'updated_at' => Carbon::create(2025, 5, 15),
        ]);

        $admin = Admins::factory()->create();
        AdminAccount::create([
            'admin_id' => $admin->id,
            'balance' => 0,
            'created_at' => Carbon::create(2025, 5, 1),
            'updated_at' => Carbon::create(2025, 5, 1),
        ]);
        InstructorAccount::create([
            'instructor_id' => $instructor->id,
            'balance' => 0,
            'created_at' => Carbon::create(2025, 5, 1),
            'updated_at' => Carbon::create(2025, 5, 1),
        ]);

        // Debug: Kiểm tra dữ liệu trước khi chạy job
        Log::info('Payments before job: ' . Payment::where('revenue_session_id', $session->id)->count());
        Log::info('Session before job: ' . json_encode($session->toArray()));

        // Act: Chạy job
        $job = new DistributeRevenueJob();
        $job->handle();

        // Debug: Kiểm tra dữ liệu sau job
        $session->refresh();
        Log::info('Session after job: ' . json_encode($session->toArray()));

        // Assert: Kiểm tra revenue_sessions
        $this->assertEquals('distributed', $session->status);
        $this->assertEquals(1000000, $session->total_revenue);
        $this->assertEquals(300000, $session->admin_share);
        $this->assertEquals(700000, $session->instructor_share);

        // Assert: Kiểm tra revenue_distributions
        $distribution = RevenueDistribution::first();
        $this->assertNotNull($distribution);
        $this->assertEquals($instructor->id, $distribution->instructor_id);
        $this->assertEquals(1000000, $distribution->revenue_amount);
        $this->assertEquals(700000, $distribution->instructor_share);

        // Assert: Kiểm tra balances
        $adminAccount = AdminAccount::first();
        $this->assertEquals(300000, $adminAccount->balance);

        $instructorAccount = InstructorAccount::first();
        $this->assertEquals(700000, $instructorAccount->balance);
    }
}