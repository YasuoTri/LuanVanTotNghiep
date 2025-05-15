<?php
namespace Tests\Feature;

use App\Jobs\CreateRevenueSessionJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_schedule_runs_create_revenue_session_job(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 5, 1, 0, 0, 0));
        Artisan::call('schedule:run');

        $output = Artisan::output();
        $this->assertStringContainsString('Running scheduled command: CreateRevenueSessionJob', $output);
    }
}