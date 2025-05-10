<?php

namespace App\Http\Controllers;

use App\Models\Instructors;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
   public function register(Request $request)
    {
        // Validation rules
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'final_cc_cname_DI' => 'nullable|string|max:100',
            'LoE_DI' => 'nullable|string|max:50',
            'YoB' => 'nullable|integer|min:1900|max:' . (date('Y') - 13), // Giới hạn năm sinh
            'gender' => 'nullable|string|in:Male,Female,other',
            'role' => 'required|in:student,instructor', // Loại bỏ admin để tránh đăng ký công khai
            // Trường bổ sung cho student
            'learning_goals' => 'nullable|string|required_if:role,student',
            'interests' => 'nullable|string',
            // Trường bổ sung cho instructor
            'name' => 'nullable|string|max:100|required_if:role,instructor',
            'bio' => 'nullable|string',
            'organization' => 'nullable|string|max:100',
        ]);

        // Create a unique userid_DI
        $validatedData['userid_DI'] = 'user_' . Str::random(10);
        $validatedData['password'] = Hash::make($validatedData['password']);

        // Tạo user
        $user = User::create([
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
            'userid_DI' => $validatedData['userid_DI'],
            'final_cc_cname_DI' => $validatedData['final_cc_cname_DI'] ?? 'Unknown',
            'LoE_DI' => $validatedData['LoE_DI'] ?? 'Unknown',
            'YoB' => $validatedData['YoB'],
            'gender' => $validatedData['gender'],
            'role' => $validatedData['role'],
        ]);

        // Tạo bản ghi trong bảng students hoặc instructors dựa trên role
        if ($validatedData['role'] === 'student') {
            Student::create([
                'user_id' => $user->id,
                'learning_goals' => $validatedData['learning_goals'],
                'interests' => $validatedData['interests'],
                'total_courses_completed' => 0,
            ]);
        } elseif ($validatedData['role'] === 'instructor') {
            Instructors::create([
                'user_id' => $user->id,
                'name' => $validatedData['name'],
                'bio' => $validatedData['bio'],
                'organization' => $validatedData['organization'],
            ]);
        }

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        // Call FastAPI recommendation service for new user
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->post('http://localhost:8100/recommend-laravel', [
                'user_id' => $user->id,
                'course_name' => null,
            ]);

            if ($response->successful()) {
                return response()->json([
                    'message' => 'Registration successful',
                    'user' => $user,
                    'token' => $token,
                    'recommended_courses' => $response->json()['courses'],
                ], 201);
            } else {
                return response()->json([
                    'message' => 'Registration successful',
                    'user' => $user,
                    'token' => $token,
                    'recommendation_error' => 'Failed to get initial recommendations',
                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $token,
                'recommendation_error' => 'Recommendation service unavailable: ' . $e->getMessage(),
            ], 201);
        }
    }
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
    
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = JWTAuth::fromUser($user); // Tạo JWT token
    
            // // Gọi API FastAPI /recommend-laravel với token nếu không phải admin
            // $response = Http::withHeaders([
            //     'Authorization' => 'Bearer ' . $token,
            // ])->post('http://localhost:8100/recommend-laravel', [
            //     'user_id' => $user->id,
            //     'course_name' => null,
            // ]);
    
        
                return response()->json([
                    'message' => 'Login successful',
                    'token' => $token,
                    'user' => $user,
                 
                ]);
        }
    
        return response()->json([
            'error' => 'The provided credentials do not match our records.',
        ], 401);
    }
    public function refresh()
{
    return response()->json([
        'token' => JWTAuth::refresh(JWTAuth::getToken())
    ], 200);
}

    // public function logout(Request $request)
    // {
    //     Auth::logout();
    //     $request->session()->invalidate();
    //     $request->session()->regenerateToken();

    //     return redirect('/');
    // }
    public function logout(Request $request)
    {
        try {
            // Vô hiệu hóa token JWT
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return response()->json([
                'message' => 'Successfully logged out'
            ], 200);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'message' => 'Invalid token'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}