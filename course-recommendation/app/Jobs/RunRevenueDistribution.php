<?php

namespace App\Console\Commands;

use App\Jobs\DistributeRevenueJob;
use Illuminate\Console\Command;

class RunRevenueDistribution extends Command
{
    protected $signature = 'revenue:distribute {--force : Force run even if not end of month}';
    protected $description = 'Run revenue distribution job manually';

    public function handle()
    {
        $this->info("🚀 Running Revenue Distribution Job...");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        try {
            // Dispatch job
            DistributeRevenueJob::dispatch();
            
            $this->info('✅ <fg=green>Revenue Distribution Job dispatched successfully!</>');
            $this->info('📋 Check the logs for detailed progress:');
            $this->info('   tail -f storage/logs/laravel.log');
            
        } catch (\Exception $e) {
            $this->error('❌ <fg=red>Failed to dispatch job!</>');
            $this->error('💥 Error: ' . $e->getMessage());
        }
    }
}
