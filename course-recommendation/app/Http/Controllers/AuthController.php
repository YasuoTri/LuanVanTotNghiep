<?php

namespace App\Http\Controllers;

use App\Models\Instructors;
use App\Models\Student;
use App\Models\InstructorRequest;
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
use App\Services\CloudinaryService;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
      protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function register(Request $request)
    {
        // Validation rules
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'final_cc_cname_DI' => 'nullable|string|max:100',
            'LoE_DI' => 'nullable|string|max:50',
            'YoB' => 'nullable|integer|min:1900|max:' . (date('Y') - 13),
            'gender' => 'nullable|string|in:Male,Female,other',
            'learning_goals' => 'nullable|string',
            'interests' => 'nullable|string',
        ]);

        // Create a unique userid_DI
        $validatedData['userid_DI'] = 'user_' . Str::random(10);
        $validatedData['password'] = Hash::make($validatedData['password']);

        // Upload avatar to Cloudinary if provided
        $avatarUrl = null;
        if ($request->hasFile('avatar')) {
           try {
        Log::info('Uploading avatar to Cloudinary');
        $avatarUrl = $this->cloudinaryService->uploadImage($request->file('avatar'));
        Log::info('Avatar URL: ' . $avatarUrl);
    } catch (\Exception $e) {
        Log::error('Avatar upload error: ' . $e->getMessage());
        }

        // Tạo user với role mặc định là student
        $user = User::create([
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
            'userid_DI' => $validatedData['userid_DI'],
            'final_cc_cname_DI' => $validatedData['final_cc_cname_DI'] ?? 'Unknown',
            'LoE_DI' => $validatedData['LoE_DI'] ?? 'Unknown',
            'YoB' => $validatedData['YoB'],
            'gender' => $validatedData['gender'],
            'role' => 'student',
            'avatar' => $avatarUrl, // Save Cloudinary URL
        ]);

        // Tạo bản ghi trong bảng students
        Student::create([
            'user_id' => $user->id,
            'learning_goals' => $validatedData['learning_goals'],
            'interests' => $validatedData['interests'],
            'total_courses_completed' => 0,
        ]);

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

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

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

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

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

            // Lưu avatar từ social provider vào Cloudinary
            $avatarUrl = null;
            if ($socialUser->getAvatar()) {
                try {
                    // Tải avatar từ URL của social provider
                    $tempImage = tempnam(sys_get_temp_dir(), 'avatar');
                    file_put_contents($tempImage, file_get_contents($socialUser->getAvatar()));
                    
                    // Tạo UploadedFile từ file tạm
                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $tempImage,
                        'avatar.jpg',
                        'image/jpeg',
                        null,
                        true
                    );
                    
                    // Upload lên Cloudinary
                    $avatarUrl = $this->cloudinaryService->uploadImage($uploadedFile, 'user_avatars');
                    
                    // Xóa file tạm
                    @unlink($tempImage);
                } catch (\Exception $e) {
                    // Nếu có lỗi, sử dụng URL gốc từ social provider
                    $avatarUrl = $socialUser->getAvatar();
                }
            }

            // Create new user
            $user = User::create([
                'userid_DI' => 'user_' . Str::random(10),
                'email' => $socialUser->getEmail() ?? $socialUser->getId() . '@gmail.com',
                'password' => Hash::make('password'), // Random password
                'avatar' => $avatarUrl,
                'final_cc_cname_DI' => $socialUser->getName() ?? 'Unknown',
                'role' => 'student', // Default to student
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

        // Call FastAPI recommendation
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

    public function requestInstructorRole(Request $request)
    {
        $user = Auth::user();

        // Kiểm tra xem user đã là instructor chưa
        if ($user->role === 'instructor') {
            return response()->json([
                'error' => 'You are already an instructor.',
            ], 400);
        }

        // Kiểm tra xem đã có request đang chờ xử lý chưa
        $existingRequest = InstructorRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();
        if ($existingRequest) {
            return response()->json([
                'error' => 'You already have a pending instructor role request.',
            ], 400);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20|regex:/^[+]?[0-9]{8,15}$/',
            'professional_links' => 'nullable|string',
            'bio' => 'required|string|min:50|max:1000|regex:/^[A-Za-z0-9\s.,!@#$%^&*()_+\-=\[\]{};:"\\\',.<>?\/]*$/',
            'organization' => 'nullable|string|max:100',
            'qualifications' => 'required|string|min:50|max:2000',
            'teaching_experience' => 'nullable|string|max:2000',
            'expertise' => 'nullable|string|max:500',
            'course_proposal' => 'nullable|string|max:2000',
            'motivation' => 'nullable|string|max:1000',
            'document_urls' => 'nullable|string',
        ]);

        // Tạo request mới
        InstructorRequest::create([
            'user_id' => $user->id,
            'name' => $validatedData['name'],
            'phone_number' => $validatedData['phone_number'],
            'professional_links' => $validatedData['professional_links'],
            'bio' => $validatedData['bio'],
            'organization' => $validatedData['organization'],
            'qualifications' => $validatedData['qualifications'],
            'teaching_experience' => $validatedData['teaching_experience'],
            'expertise' => $validatedData['expertise'],
            'course_proposal' => $validatedData['course_proposal'],
            'motivation' => $validatedData['motivation'],
            'document_urls' => $validatedData['document_urls'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Instructor role request submitted successfully.',
        ], 201);
    }

    public function reviewInstructorRequest(Request $request, $requestId)
    {
        $admin = Auth::user();

        // Kiểm tra xem user có phải admin không
        if ($admin->role !== 'admin') {
            return response()->json([
                'error' => 'Unauthorized. Only admins can review instructor requests.',
            ], 403);
        }

        $requestData = $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $instructorRequest = InstructorRequest::findOrFail($requestId);

        $adminInAdminTable = \App\Models\Admins::where('user_id', $admin->id)->first();
        if (!$adminInAdminTable) {
            return response()->json([
                'error' => 'Admin not found in the admin table.',
            ], 404);
        }
        // Cập nhật trạng thái request
        $instructorRequest->update([
            'status' => $requestData['status'],
            'admin_notes' => $requestData['admin_notes'],
            'admin_id' => $adminInAdminTable->id,
            'reviewed_at' => now(),
        ]);

        if ($requestData['status'] === 'approved') {
            // Cập nhật role của user thành instructor
            $user = User::findOrFail($instructorRequest->user_id);
            $user->update(['role' => 'instructor']);

            // Tạo bản ghi trong bảng instructors
            Instructors::create([
                'user_id' => $user->id,
                'name' => $instructorRequest->name,
                'bio' => $instructorRequest->bio,
                'organization' => $instructorRequest->organization,
            ]);
        }

        return response()->json([
            'message' => 'Instructor request reviewed successfully.',
            'request' => $instructorRequest,
        ], 200);
    }
//     public function uploadInstructorDocuments(Request $request)
// {
//     $user = Auth::user();

//     // Kiểm tra xem user có pending request không
//     $instructorRequest = InstructorRequest::where('user_id', $user->id)
//         ->where('status', 'pending')
//         ->first();

//     if (!$instructorRequest) {
//         return response()->json([
//             'error' => 'No pending instructor request found.',
//         ], 400);
//     }

//     $validatedData = $request->validate([
//         'documents' => 'required|array',
//         'documents.*' => 'file|mimes:pdf,doc,docx,mp4|max:10240', // 10MB max
//     ]);

//     $urls = [];
//     foreach ($validatedData['documents'] as $document) {
//         $path = $document->store('instructor_documents', 'public');
//         $url = asset('storage/' . $path);

//         // Lưu vào bảng media
//         $media = \App\Models\Media::create([
//             'medially_type' => InstructorRequest::class,
//             'medially_id' => $instructorRequest->id,
//             'file_url' => $url,
//             'file_name' => $document->getClientOriginalName(),
//             'file_type' => $document->getClientMimeType(),
//             'size' => $document->getSize(),
//         ]);

//         $urls[] = $url;
//     }

//     // Cập nhật document_urls
//     $existingUrls = $instructorRequest->document_urls ? explode(',', $instructorRequest->document_urls) : [];
//     $newUrls = array_merge($existingUrls, $urls);
//     $instructorRequest->update([
//         'document_urls' => implode(',', $newUrls),
//     ]);

//     return response()->json([
//         'message' => 'Documents uploaded successfully.',
//         'urls' => $urls,
//     ], 200);
// }
public function getCurrentUser()
{
    try {
        // Lấy user hiện tại đã xác thực
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or user not found',
            ], 401);
        }
        if ($user->role === 'instructor') {
            $instructor = Instructors::where('user_id', $user->id)->first();
            if (!$instructor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Instructor not found',
                ], 404);
            }
            $user->instructor = $instructor;
        }
        // Tải thêm các quan hệ cần thiết (nếu có)
        // Ví dụ: $user->load(['profile', 'roles', 'permissions']);
        
        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving user information',
            'error' => $e->getMessage()
        ], 500);
    }
}
/**
 * Update the authenticated user's profile information.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\JsonResponse
 */
public function updateProfile(Request $request)
{
    try {
        // Lấy ID của user hiện tại từ Auth
        $userId = Auth::id();
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized or user not found',
            ], 401);
        }
        
        // Tìm user bằng model User
        $user = User::findOrFail($userId);
        Log::info('User found: ' .$request->all);
        // Validate dữ liệu đầu vào
        $validatedData = $request->validate([
            'final_cc_cname_DI' => 'nullable|string|max:100',
            'LoE_DI' => 'nullable|string|max:50',
            'YoB' => 'nullable|integer|min:1900|max:' . (date('Y') - 13),
            'gender' => 'nullable|string|in:Male,Female,other',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            // Thông tin cho student
            'learning_goals' => 'nullable|string',
            'interests' => 'nullable|string',
            // Thông tin cho instructor
            'bio' => 'nullable|string|max:1000',
            'organization' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
        ]);
        Log::info('Validated data: ' . json_encode($validatedData));
        // Upload avatar mới nếu có
        if ($request->hasFile('avatar')) {
            try {
                Log::info('Uploading avatar to Cloudinary');
                
                // Xóa avatar cũ nếu có
                if ($user->avatar && strpos($user->avatar, 'cloudinary.com') !== false) {
                    try {
                        $this->cloudinaryService->deleteByUrl($user->avatar);
                    } catch (\Exception $e) {
                        Log::error('Error deleting old avatar: ' . $e->getMessage());
                    }
                }
                
                $avatarUrl = $this->cloudinaryService->uploadImage($request->file('avatar'), 'user_avatars');
                Log::info('Avatar URL: ' . $avatarUrl);
                
                $validatedData['avatar'] = $avatarUrl;
            } catch (\Exception $e) {
                Log::error('Avatar upload error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload avatar',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
        
        // Cập nhật thông tin cơ bản của user
        $userDataToUpdate = array_intersect_key($validatedData, array_flip([
            'final_cc_cname_DI', 'LoE_DI', 'YoB', 'gender', 'avatar'
        ]));
        
        if (!empty($userDataToUpdate)) {
            $user->update($userDataToUpdate);
        }
        
        // Cập nhật thông tin bổ sung dựa trên vai trò
        if ($user->role === 'student') {
            $studentDataToUpdate = array_intersect_key($validatedData, array_flip([
                'learning_goals', 'interests'
            ]));
            
            if (!empty($studentDataToUpdate)) {
                // Sử dụng relationship student để cập nhật
                $student = $user->student;
                if ($student) {
                    $student->update($studentDataToUpdate);
                }
            }
        } elseif ($user->role === 'instructor') {
            $instructorDataToUpdate = array_intersect_key($validatedData, array_flip([
                'bio', 'organization', 'name'
            ]));
            
            if (!empty($instructorDataToUpdate)) {
                // Sử dụng relationship instructor để cập nhật
                $instructor = $user->instructor;
                if ($instructor) {
                    $instructor->update($instructorDataToUpdate);
                }
            }
        }
        
        // Refresh user model để lấy dữ liệu mới nhất
        $user = $user->fresh();
        
        // Thêm thông tin bổ sung dựa trên vai trò
        if ($user->role === 'instructor') {
            $user->load('instructor');
        } elseif ($user->role === 'student') {
            $user->load('student');
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'User not found',
            'error' => $e->getMessage()
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error updating profile',
            'error' => $e->getMessage()
        ], 500);
    }
}


}