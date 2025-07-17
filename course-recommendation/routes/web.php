<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Laravel\Socialite\Facades\Socialite;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::get('/auth/facebook', [AuthController::class, 'redirectToFacebook']);
Route::get('/auth/facebook/callback', [AuthController::class, 'handleFacebookCallback']);

Route::get('/test-socialite', function() {
    try {
        $user = Socialite::driver('google')->user();
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName()
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'class' => get_class($e)
        ]);
    }
});

Route::get('/payment/success', [PaymentController::class, 'showSuccess'])
     ->name('payment.success');

Route::get('/payment/failed', [PaymentController::class, 'showFailure'])
     ->name('payment.failed');
