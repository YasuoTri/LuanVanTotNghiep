<?php

namespace App\Console\Commands;

use App\Services\PayPalService;
use Illuminate\Console\Command;

class TestPayPalConnection extends Command
{
    protected $signature = 'paypal:test-connection';
    protected $description = 'Test PayPal API connection';

    public function handle()
    {
        $this->info("🔗 Testing PayPal API Connection...");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        try {
            $paypalService = new PayPalService();
            
            $this->info("🔑 Getting access token...");
            if ($paypalService->testConnection()) {
                $this->info('✅ <fg=green>PayPal connection successful!</>');
                $this->info('🎯 Your PayPal credentials are working correctly');
                $this->info('🌐 Mode: ' . config('paypal.mode'));
                $this->info('🆔 Client ID: ' . substr(config('paypal.client_id'), 0, 10) . '...');
            } else {
                $this->error('❌ <fg=red>PayPal connection failed!</>');
            }
            
        } catch (\Exception $e) {
            $this->error('❌ <fg=red>Connection Error!</>');
            $this->error('💥 ' . $e->getMessage());
            
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->error('🔧 <fg=yellow>Troubleshooting:</>');
            $this->error('1. Check PAYPAL_CLIENT_ID in .env');
            $this->error('2. Check PAYPAL_CLIENT_SECRET in .env');
            $this->error('3. Make sure PAYPAL_MODE=sandbox in .env');
            $this->error('4. Verify credentials at https://developer.paypal.com/');
        }
    }
}
