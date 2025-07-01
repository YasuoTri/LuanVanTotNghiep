<?php

namespace App\Console\Commands;

use App\Services\PayPalService;
use Illuminate\Console\Command;

class TestPayPalPayout extends Command
{
    protected $signature = 'paypal:test-payout {email} {amount}';
    protected $description = 'Test PayPal payout with direct API calls';

    public function handle()
    {
        $email = $this->argument('email');
        $amount = floatval($this->argument('amount'));
        
        $this->info("🧪 Testing PayPal Payout (Direct API)");
        $this->info("📧 Recipient: {$email}");
        $this->info("💰 Amount: \${$amount} USD");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        try {
            $paypalService = new PayPalService();
            
            // Test connection first
            $this->info("🔗 Testing PayPal connection...");
            if (!$paypalService->testConnection()) {
                $this->error("❌ Cannot connect to PayPal. Check your credentials!");
                return;
            }
            $this->info("✅ PayPal connection successful!");
            
            $this->info("🚀 Sending payout to PayPal...");
            $this->info("⏳ Please wait...");
            
            $response = $paypalService->sendPayout(
                $email, 
                $amount, 
                'USD', 
                'Test payout from Laravel Course Platform'
            );
            
            $this->info('✅ <fg=green>PAYOUT SENT SUCCESSFULLY!</>');
            $this->info('📋 Batch ID: ' . $response->result->batch_header->payout_batch_id);
            $this->info('📊 Status: ' . $response->result->batch_header->batch_status);
            
            if (isset($response->result->items[0])) {
                $item = $response->result->items[0];
                $this->info('🎯 Item ID: ' . $item->payout_item_id);
                $this->info('💳 Transaction Status: ' . ($item->transaction_status ?? 'PENDING'));
            }
            
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info('🎯 <fg=yellow>Next Steps:</>');
            $this->info('1. Check the recipient PayPal Sandbox account');
            $this->info('2. Login to: https://www.sandbox.paypal.com/');
            $this->info('3. Use email: ' . $email);
            $this->info('4. Look for the payout notification!');
            
            // Check status after delay
            $this->info('🔍 Checking status in 15 seconds...');
            sleep(15);
            
            $statusResponse = $paypalService->getPayoutStatus($response->result->batch_header->payout_batch_id);
            $this->info('📈 Updated Status: ' . $statusResponse->result->batch_header->batch_status);
            
            if (isset($statusResponse->result->items[0])) {
                $item = $statusResponse->result->items[0];
                $this->info('💳 Transaction Status: ' . ($item->transaction_status ?? 'PENDING'));
            }
            
        } catch (\Exception $e) {
            $this->error('❌ <fg=red>PAYOUT FAILED!</>');
            $this->error('💥 Error: ' . $e->getMessage());
            
            // Helpful error messages
            if (str_contains($e->getMessage(), 'AUTHENTICATION_FAILURE')) {
                $this->error('🔑 Check your PayPal Client ID and Secret in .env');
            } elseif (str_contains($e->getMessage(), 'RECEIVER_UNREGISTERED')) {
                $this->error('📧 The recipient email is not registered with PayPal Sandbox');
                $this->error('💡 Create a Personal Sandbox account with this email');
            } elseif (str_contains($e->getMessage(), 'INSUFFICIENT_FUNDS')) {
                $this->error('💰 Business Sandbox account has insufficient funds');
            }
        }
    }
}
