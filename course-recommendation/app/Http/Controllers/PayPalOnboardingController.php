<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalOnboardingController extends Controller
{
    private $paypalBaseUrl;
    private $clientId;
    private $clientSecret;
    private $partnerMerchantId;

    public function __construct()
    {
        $this->paypalBaseUrl = config('services.paypal.sandbox') 
            ? 'https://api-m.sandbox.paypal.com' 
            : 'https://api-m.paypal.com';
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->partnerMerchantId = config('services.paypal.partner_merchant_id');
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken()
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->post("{$this->paypalBaseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials'
                ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            Log::error('Failed to get PayPal access token', ['response' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::error('Error getting PayPal access token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate URL Onboarding link for seller
     */
    public function generateUrlOnboardingLink(Request $request)
    {
        $partnerClientId = config('services.paypal.client_id');
        $partnerId = config('services.paypal.partner_merchant_id');
        $returnUrl = config('services.paypal.return_url');
        $logoUrl = config('services.paypal.logo_url');

        $signupUrl = "https://www.sandbox.paypal.com/bizsignup/partner/entry?" . http_build_query([
            'partnerClientId' => $partnerClientId,
            'partnerId' => $partnerId,
            'partnerLogoUrl' => $logoUrl,
            'returnToPartnerUrl' => $returnUrl,
            'product' => 'ppcp',
            'integrationType' => 'TO',
            'features' => 'PAYMENT,REFUND',
            'showPermissions' => 'true'
        ]);

        return response()->json([
            'signup_url' => $signupUrl,
            'message' => 'Please open this URL in a browser and complete the signup process using a sandbox business account.'
        ]);
    }

    /**
     * Handle return from PayPal (for demo, simulate the return data)
     */
    public function handleReturn(Request $request)
    {
        // Because we're in local HTTP, PayPal won't redirect here.
        // For demo, we simulate the return data or get merchantIdInPayPal manually.
        $merchantIdInPayPal = $request->input('merchantIdInPayPal', 'YOUR_SELLER_MERCHANT_ID'); // Replace with actual seller merchant ID from PayPal Dashboard
        $permissionsGranted = $request->input('permissionsGranted', 'true');
        $trackingId = $request->input('merchantId', 'test_' . time()); // Simulate tracking ID

        if ($permissionsGranted !== 'true') {
            Log::error('Permissions not granted', ['merchantIdInPayPal' => $merchantIdInPayPal]);
            return response()->json(['status' => 'failed', 'message' => 'Permissions not granted']);
        }

        // Verify seller status
        $status = $this->checkSellerStatus($merchantIdInPayPal);

        return response()->json($status);
    }

    /**
     * Check seller status to confirm sub-merchant onboarding
     */
    private function checkSellerStatus($sellerMerchantId)
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return ['status' => 'failed', 'message' => 'Unable to authenticate with PayPal'];
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$accessToken}",
            ])->get("{$this->paypalBaseUrl}/v1/customer/partners/{$this->partnerMerchantId}/merchant-integrations/{$sellerMerchantId}");

            if ($response->successful()) {
                $data = $response->json();
                $isOnboarded = $data['payments_receivable'] === true && 
                              $data['primary_email_confirmed'] === true && 
                              !empty($data['oauth_integrations']);

                return [
                    'status' => $isOnboarded ? 'success' : 'failed',
                    'message' => $isOnboarded ? 'Seller successfully onboarded as sub-merchant' : 'Seller onboarding incomplete',
                    'merchant_id' => $data['merchant_id'],
                    'payments_receivable' => $data['payments_receivable'],
                    'primary_email_confirmed' => $data['primary_email_confirmed'],
                    'products' => $data['products'] ?? [],
                    'capabilities' => $data['capabilities'] ?? []
                ];
            }

            Log::error('Failed to check seller status', ['response' => $response->body()]);
            return ['status' => 'failed', 'message' => 'Failed to check seller status'];
        } catch (\Exception $e) {
            Log::error('Error checking seller status', ['error' => $e->getMessage()]);
            return ['status' => 'failed', 'message' => 'Internal server error'];
        }
    }

    /**
     * Simulate return for demo purposes
     */
    public function simulateReturn(Request $request)
    {
        // Simulate PayPal return data
        $fakeData = [
            'merchantIdInPayPal' => $request->input('merchantIdInPayPal', 'YOUR_SELLER_MERCHANT_ID'), // Replace with actual seller merchant ID
            'permissionsGranted' => 'true',
            'merchantId' => 'test_' . time(),
            'accountStatus' => 'BUSINESS_ACCOUNT',
            'consentStatus' => 'true',
            'isEmailConfirmed' => 'true'
        ];

        return $this->handleReturn(new Request($fakeData));
    }
}