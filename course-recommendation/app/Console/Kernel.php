<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
     protected $commands = [
        Commands\TestPayPalPayout::class,
        Commands\TestPayPalConnection::class,
        Commands\RunRevenueDistribution::class,
    ];
    protected function schedule(Schedule $schedule): void
    {
        // // Lên lịch cho CreateRevenueSessionJob vào ngày 1 hàng tháng
        // $schedule->job(new \App\Jobs\CreateRevenueSessionJob)->monthlyOn(1, '00:00');
        // //   $schedule->job(new \App\Jobs\CreateRevenueSessionJob)->everyMinute();

        // // Lên lịch cho DistributeRevenueJob vào ngày cuối tháng
        // $schedule->job(new \App\Jobs\DistributeRevenueJob)->monthlyOn(28, '23:59')->when(function () {
        //     return now()->endOfMonth()->isToday();
        // });
        // $schedule->command('certificates:auto-issue')->dailyAt('01:00');
           $schedule->job(new \App\Jobs\CreateRevenueSessionJob)->monthlyOn(1, '00:00');
    
            // Chạy vào ngày cuối tháng (tự động tính)
            $schedule->job(new \App\Jobs\DistributeRevenueJob)->lastDayOfMonth('23:59');
            
            $schedule->command('certificates:auto-issue')->dailyAt('01:00');

                    // Chạy revenue distribution vào ngày 1 hàng tháng lúc 2:00 AM
            $schedule->job(new \App\Jobs\DistributeRevenueJob)
                 ->monthlyOn(1, '02:00')
                 ->withoutOverlapping()
                 ->runInBackground();
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