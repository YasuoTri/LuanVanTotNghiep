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
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;

class AuthController extends Controller
{
//    public function register(Request $request)
//     {
//         // Validation rules
//         $validatedData = $request->validate([
//             'email' => 'required|email|unique:users,email',
//             'password' => 'required|min:6|confirmed',
//             'final_cc_cname_DI' => 'nullable|string|max:100',
//             'LoE_DI' => 'nullable|string|max:50',
//             'YoB' => 'nullable|integer|min:1900|max:' . (date('Y') - 13), // Giới hạn năm sinh
//             'gender' => 'nullable|string|in:Male,Female,other',
//             'role' => 'required|in:student,instructor', // Loại bỏ admin để tránh đăng ký công khai
//             // Trường bổ sung cho student
//             'learning_goals' => 'nullable|string|required_if:role,student',
//             'interests' => 'nullable|string',
//             // Trường bổ sung cho instructor
//             'name' => 'nullable|string|max:100|required_if:role,instructor',
//             'bio' => 'nullable|string',
//             'organization' => 'nullable|string|max:100',
//         ]);

//         // Create a unique userid_DI
//         $validatedData['userid_DI'] = 'user_' . Str::random(10);
//         $validatedData['password'] = Hash::make($validatedData['password']);

//         // Tạo user
//         $user = User::create([
//             'email' => $validatedData['email'],
//             'password' => $validatedData['password'],
//             'userid_DI' => $validatedData['userid_DI'],
//             'final_cc_cname_DI' => $validatedData['final_cc_cname_DI'] ?? 'Unknown',
//             'LoE_DI' => $validatedData['LoE_DI'] ?? 'Unknown',
//             'YoB' => $validatedData['YoB'],
//             'gender' => $validatedData['gender'],
//             'role' => $validatedData['role'],
//         ]);

//         // Tạo bản ghi trong bảng students hoặc instructors dựa trên role
//         if ($validatedData['role'] === 'student') {
//             Student::create([
//                 'user_id' => $user->id,
//                 'learning_goals' => $validatedData['learning_goals'],
//                 'interests' => $validatedData['interests'],
//                 'total_courses_completed' => 0,
//             ]);
//         } elseif ($validatedData['role'] === 'instructor') {
//             Instructors::create([
//                 'user_id' => $user->id,
//                 'name' => $validatedData['name'],
//                 'bio' => $validatedData['bio'],
//                 'organization' => $validatedData['organization'],
//             ]);
//         }

//         // Generate JWT token
//         $token = JWTAuth::fromUser($user);

//         // Call FastAPI recommendation service for new user
//         try {
//             $response = Http::withHeaders([
//                 'Authorization' => 'Bearer ' . $token,
//             ])->post('http://localhost:8100/recommend-laravel', [
//                 'user_id' => $user->id,
//                 'course_name' => null,
//             ]);

//             if ($response->successful()) {
//                 return response()->json([
//                     'message' => 'Registration successful',
//                     'user' => $user,
//                     'token' => $token,
//                     'recommended_courses' => $response->json()['courses'],
//                 ], 201);
//             } else {
//                 return response()->json([
//                     'message' => 'Registration successful',
//                     'user' => $user,
//                     'token' => $token,
//                     'recommendation_error' => 'Failed to get initial recommendations',
//                 ], 201);
//             }
//         } catch (\Exception $e) {
//             return response()->json([
//                 'message' => 'Registration successful',
//                 'user' => $user,
//                 'token' => $token,
//                 'recommendation_error' => 'Recommendation service unavailable: ' . $e->getMessage(),
//             ], 201);
//         }
//     }


public function register(Request $request)
{
    // Validation rules
    $validatedData = $request->validate([
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:6|confirmed',
        'final_cc_cname_DI' => 'nullable|string|max:100',
        'LoE_DI' => 'nullable|string|max:50',
        'YoB' => 'nullable|integer|min:1900|max:' . (date('Y') - 13),
        'gender' => 'nullable|string|in:Male,Female,other',
        'role' => 'required|in:student,instructor',
        'learning_goals' => 'nullable|string|required_if:role,student',
        'interests' => 'nullable|string',
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

    // Tạo bản ghi trong bảng students hoặc instructors
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

    // Set cookie
    $cookie = cookie(
        'jwt_token',
        $token,
        60,
        '/',
        null,
        true,
        true,
        false,
        'SameSite=Strict'
    );

    // Call FastAPI recommendation service
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
            ], 201)->withCookie($cookie);
        } else {
            return response()->json([
                'message' => 'Registration successful',
                'user' => $user,
                'token' => $token,
                'recommendation_error' => 'Failed to get initial recommendations',
            ], 201)->withCookie($cookie);
        }
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token,
            'recommendation_error' => 'Recommendation service unavailable: ' . $e->getMessage(),
        ], 201)->withCookie($cookie);
    }
}
    // public function login(Request $request)
    // {
    //     $credentials = $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required',
    //     ]);
    
    //     if (Auth::attempt($credentials)) {
    //         $user = Auth::user();
    //         $token = JWTAuth::fromUser($user); // Tạo JWT token
    
    //         // // Gọi API FastAPI /recommend-laravel với token nếu không phải admin
    //         // $response = Http::withHeaders([
    //         //     'Authorization' => 'Bearer ' . $token,
    //         // ])->post('http://localhost:8100/recommend-laravel', [
    //         //     'user_id' => $user->id,
    //         //     'course_name' => null,
    //         // ]);
    
        
    //             return response()->json([
    //                 'message' => 'Login successful',
    //                 'token' => $token,
    //                 'user' => $user,
                 
    //             ]);
    //     }
    
    //     return response()->json([
    //         'error' => 'The provided credentials do not match our records.',
    //     ], 401);
    // }
    public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::attempt($credentials)) {
        $user = Auth::user();
        $token = JWTAuth::fromUser($user); // Tạo JWT token

        // Set cookie với các thuộc tính bảo mật
        $cookie = cookie(
            'jwt_token', // Tên cookie
            $token, // Giá trị token
            60, // Thời gian sống (phút, đồng bộ với JWT)
            '/', // Path
            null, // Domain (null để dùng domain hiện tại)
            true, // Secure (chỉ gửi qua HTTPS)
            true, // HttpOnly (ngăn JavaScript truy cập)
            false, // Raw
            'Strict' // SameSite
        );

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ])->withCookie($cookie);
    }

    return response()->json([
        'error' => 'The provided credentials do not match our records.',
    ], 401);
}
//     public function refresh()
// {
//     return response()->json([
//         'token' => JWTAuth::refresh(JWTAuth::getToken())
//     ], 200);
// }

public function refresh()
{
    try {
        $newToken = JWTAuth::refresh(JWTAuth::getToken());

        // Set cookie mới
        $cookie = cookie(
            'jwt_token',
            $newToken,
            60,
            '/',
            null,
            true,
            true,
            false,
            'Strict'
        );

        return response()->json([
            'token' => $newToken
        ])->withCookie($cookie);
    } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
        return response()->json(['error' => 'Invalid token'], 401);
    }
}

    // public function logout(Request $request)
    // {
    //     Auth::logout();
    //     $request->session()->invalidate();
    //     $request->session()->regenerateToken();

    //     return redirect('/');
    // }
    // public function logout(Request $request)
    // {
    //     try {
    //         // Vô hiệu hóa token JWT
    //         JWTAuth::invalidate(JWTAuth::getToken());
            
    //         return response()->json([
    //             'message' => 'Successfully logged out'
    //         ], 200);
    //     } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
    //         return response()->json([
    //             'message' => 'Invalid token'
    //         ], 401);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Logout failed',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function logout(Request $request)
{
    try {
        // Vô hiệu hóa token JWT
        JWTAuth::invalidate(JWTAuth::getToken());

        // Xóa cookie
        $cookie = cookie()->forget('jwt_token');

        return response()->json([
            'message' => 'Successfully logged out'
        ])->withCookie($cookie);
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
    // Redirect to Google login
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    // Handle Google callback
    public function handleGoogleCallback()
    {
        try {
            $socialUser = Socialite::driver('google')->user();
            return $this->handleSocialLogin($socialUser, 'google');
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Google login failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Redirect to Facebook login
    public function redirectToFacebook()
    {
       return Socialite::driver('facebook')->redirect();
    }

    // Handle Facebook callback
    public function handleFacebookCallback()
    {
        try {
            $socialUser = Socialite::driver('facebook')->user();
            return $this->handleSocialLogin($socialUser, 'facebook');
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Facebook login failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Handle social login logic
    // protected function handleSocialLogin($socialUser, $provider)
    // {
    //     // Find or create the user
    //     $user = User::where('provider_id', $socialUser->getId())
    //         ->where('provider', $provider)
    //         ->first();

    //     if (!$user) {
    //         // Check if email already exists
    //         $existingUser = User::where('email', $socialUser->getEmail())->first();
    //         if ($existingUser) {
    //             return response()->json([
    //                 'error' => 'Email already registered with another account.',
    //             ], 400);
    //         }

    //         // Create new user
    //         $user = User::create([
    //             'userid_DI' => 'user_' . Str::random(10),
    //             'email' => $socialUser->getEmail()??$socialUser->getId().'@gmail.com',
    //             'final_cc_cname_DI' => $socialUser->getName() ?? 'Unknown',
    //             'role' => 'student', // Default to student
    //             'password' => Hash::make(Str::random(16)), // Random password
    //             'provider' => $provider,
    //             'provider_id' => $socialUser->getId(),
    //         ]);

    //         // Create student record
    //         Student::create([
    //             'user_id' => $user->id,
    //             'learning_goals' => null,
    //             'interests' => null,
    //             'total_courses_completed' => 0,
    //         ]);
    //     }

    //     // Generate JWT token
    //     $token = JWTAuth::fromUser($user);

    //     // Call FastAPI recommendation service
    //     try {
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $token,
    //         ])->post('http://localhost:8100/recommend-laravel', [
    //             'user_id' => $user->id,
    //             'course_name' => null,
    //         ]);

    //         $recommendedCourses = $response->successful()
    //             ? $response->json()['courses']
    //             : null;

    //         return response()->json([
    //             'message' => 'Social login successful',
    //             'user' => $user,
    //             'token' => $token,
    //             'recommended_courses' => $recommendedCourses,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Social login successful',
    //             'user' => $user,
    //             'token' => $token,
    //             'recommendation_error' => 'Recommendation service unavailable: ' . $e->getMessage(),
    //         ], 200);
    //     }
    // }

    protected function handleSocialLogin($socialUser, $provider)
{
    // Find or create the user
    $user = User::where('provider_id', $socialUser->getId())
        ->where('provider', $provider)
        ->first();

    if (!$user) {
        // Check if email already exists
        $existingUser = User::where('email', $socialUser->getEmail())->first();
        if ($existingUser) {
            return response()->json([
                'error' => 'Email already registered with another account.',
            ], 400);
        }

        // Create new user
        $user = User::create([
            'userid_DI' => 'user_' . Str::random(10),
            'email' => $socialUser->getEmail() ?? $socialUser->getId() . '@gmail.com',
            'final_cc_cname_DI' => $socialUser->getName() ?? 'Unknown',
            'role' => 'student', // Default to student
            'password' => Hash::make(Str::random(16)), // Random password
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
        ]);

        // Create student record
        Student::create([
            'user_id' => $user->id,
            'learning_goals' => null,
            'interests' => null,
            'total_courses_completed' => 0,
        ]);
    }

    // Generate JWT token
    $token = JWTAuth::fromUser($user);

    // Set cookie
    $cookie = cookie(
        'jwt_token',
        $token,
        60,
        '/',
        null,
        true,
        true,
        false,
        'Strict'
    );

    // Call FastAPI recommendation.ConcurrentModificationException
    try {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->post('http://localhost:8100/recommend-laravel', [
            'user_id' => $user->id,
            'course_name' => null,
        ]);

        $recommendedCourses = $response->successful()
            ? $response->json()['courses']
            : null;

        return response()->json([
            'message' => 'Social login successful',
            'user' => $user,
            'token' => $token,
            'recommended_courses' => $recommendedCourses,
        ])->withCookie($cookie);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Social login successful',
            'user' => $user,
            'token' => $token,
            'recommendation_error' => 'Recommendation service unavailable: ' . $e->getMessage(),
        ])->withCookie($cookie);
    }
}

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Gửi link reset password
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)], 200)
            : response()->json(['error' => __($status)], 400);
    }

    /**
     * Xử lý reset password
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        // Reset mật khẩu
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)], 200)
            : response()->json(['error' => __($status)], 400);
    }
}