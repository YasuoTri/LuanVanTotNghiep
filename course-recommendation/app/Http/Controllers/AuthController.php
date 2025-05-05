<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = JWTAuth::fromUser($user); // Tạo JWT token

            // Gọi API FastAPI /recommend-laravel với token
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token, // Sử dụng $token thay vì JWT_SECRET
            ])->post('http://localhost:8100/recommend-laravel', [
                'user_id' => $user->id,
                'course_name' => null,
            ]);

            if ($response->successful()) {
                return response()->json([
                    'message' => 'Login successful',
                    'token' => $token,
                    'recommended_courses' => $response->json()['courses'],
                ]);
            } else {
                return response()->json([
                    'error' => 'Failed to get recommendations',
                    'details' => $response->json(),
                ], 500);
            }
        }

        return response()->json([
            'error' => 'The provided credentials do not match our records.',
        ], 401);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}