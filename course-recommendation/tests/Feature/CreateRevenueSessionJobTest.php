<?php
namespace Tests\Feature;

use App\Jobs\CreateRevenueSessionJob;
use App\Models\RevenueSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRevenueSessionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_revenue_session_for_current_month(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 5, 1));
        $job = new CreateRevenueSessionJob();
        $job->handle();

        $session = RevenueSession::first();
        $this->assertNotNull($session);
        $this->assertEquals(5, $session->month);
        $this->assertEquals(2025, $session->year);
        $this->assertEquals('open', $session->status);
        $this->assertEquals(0, $session->total_revenue);
    }

    public function test_does_not_create_duplicate_session(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 5, 1));
        RevenueSession::create([
            'month' => 5,
            'year' => 2025,
            'total_revenue' => 0,
            'admin_share' => 0,
            'instructor_share' => 0,
            'status' => 'open',
        ]);

        $job = new CreateRevenueSessionJob();
        $job->handle();

        $this->assertEquals(1, RevenueSession::count());
    }
}