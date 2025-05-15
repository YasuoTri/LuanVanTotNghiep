<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Lên lịch cho CreateRevenueSessionJob vào ngày 1 hàng tháng
        $schedule->job(new \App\Jobs\CreateRevenueSessionJob)->monthlyOn(1, '00:00');
        //   $schedule->job(new \App\Jobs\CreateRevenueSessionJob)->everyMinute();

        // Lên lịch cho DistributeRevenueJob vào ngày cuối tháng
        $schedule->job(new \App\Jobs\DistributeRevenueJob)->monthlyOn(28, '23:59')->when(function () {
            return now()->endOfMonth()->isToday();
        });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}