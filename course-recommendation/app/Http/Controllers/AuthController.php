<?php

namespace App\Http\Controllers;

use App\Mail\InstructorApprovalMail;
use App\Mail\InstructorRejectionMail;
use App\Models\Admins;
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
use Illuminate\Support\Facades\Mail;

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
            'username' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'LoE_DI' => 'nullable|string|max:50',
            'birthdate' => 'nullable|date_format:Y-m-d|before_or_equal:' . now()->subYears(13)->format('Y-m-d'), // Updated from YoB to birthdate
            'gender' => 'nullable|string|in:Male,Female,other',
            'learning_goals' => 'nullable|string',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        // Create a unique userid_DI
        $validatedData['userid_DI'] = 'user_' . Str::random(10);
        $validatedData['password'] = Hash::make($validatedData['password']);

        // Upload avatar to Cloudinary if provided
        $avatarUrl = null;
        if ($request->hasFile('avatar')) {
            try {
                Log::info('Uploading avatar to Cloudinary');
                $avatarUrl = $this->cloudinaryService->uploadImage($request->file('avatar'), 'user_avatars');
                Log::info('Avatar URL: ' . $avatarUrl);
            } catch (\Exception $e) {
                Log::error('Avatar upload error: ' . $e->getMessage());
            }
        }

        // Create user with default role as student
        $user = User::create([
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
            'userid_DI' => $validatedData['userid_DI'],
            'LoE_DI' => $validatedData['LoE_DI'] ?? 'Unknown',
            'birthdate' => $validatedData['birthdate'] ?? null, // Updated from YoB to birthdate
            'gender' => $validatedData['gender'],
            'role' => 'student',
            'avatar' => $avatarUrl,
        ]);

        // Create record in students table
        $student = Student::create([
            'user_id' => $user->id,
            'learning_goals' => $validatedData['learning_goals'],
            'total_courses_completed' => 0,
        ]);

        // Sync categories if provided
        if (isset($validatedData['category_ids']) && !empty($validatedData['category_ids'])) {
            $student->categories()->sync($validatedData['category_ids']);
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

    //  public function login(Request $request)
    // {
    //     $credentials = $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required',
    //     ]);
        
    //     if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
    //         $user = Auth::user();
    //         $token = JWTAuth::fromUser($user);

    //         $cookie = cookie(
    //             'jwt_token',
    //             $token,
    //             60,
    //             '/',
    //             null,
    //             true,
    //             true,
    //             false,
    //             'Strict'
    //         );

    //         return response()->json([
    //             'message' => 'Login successful',
    //             'token' => $token,
    //             'user' => $user,
    //         ])->withCookie($cookie);
    //     }

    //     return response()->json([
    //         'error' => 'The provided credentials do not match our records.',
    //     ], 401);
    // }
    public function login(Request $request)
    {
        // Validate input
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            // Attempt authentication
            if (!Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
                return response()->json([
                    'error' => 'The provided credentials do not match our records.',
                ], 401);
            }

            $user = Auth::user();

            // Check user status
            if ($user->status === 'banned') {
                Auth::logout(); // Log out the user to prevent token generation
                return response()->json([
                    'error' => 'Your account has been permanently banned. Please contact support.',
                ], 403);
            }

            if ($user->status === 'suspended' && $user->suspended_until && now()->lt($user->suspended_until)) {
                Auth::logout(); // Log out the user
                return response()->json([
                    'error' => 'Your account is suspended until ' . $user->suspended_until->toFormattedDateString() . '. Please try again later.',
                ], 403);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            // Create JWT cookie
            $cookie = cookie(
                'jwt_token',
                $token,
                60, // 60 minutes
                '/',
                null,
                true, // secure
                true, // httpOnly
                false,
                'Strict'
            );

            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user,
            ])->withCookie($cookie);
        } catch (\Exception $e) {
            Log::error('Error in login: ' . $e->getMessage());
            return response()->json([
                'error' => 'An error occurred during login. Please try again.',
            ], 500);
        }
    }

    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
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
            JWTAuth::invalidate(JWTAuth::getToken());
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
        $user = User::where('provider_id', $socialUser->getId())
            ->where('provider', $provider)
            ->first();

        if (!$user) {
            $existingUser = User::where('email', $socialUser->getEmail())->first();
            if ($existingUser) {
                return response()->json([
                    'error' => 'Email already registered with another account.',
                ], 400);
            }

            // Generate unique username
            $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $socialUser->getName() ?? $socialUser->getId());
            $username = $baseUsername;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            $avatarUrl = null;
            if ($socialUser->getAvatar()) {
                try {
                    $tempImage = tempnam(sys_get_temp_dir(), 'avatar');
                    file_put_contents($tempImage, file_get_contents($socialUser->getAvatar()));
                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $tempImage,
                        'avatar.jpg',
                        'image/jpeg',
                        null,
                        true
                    );
                    $avatarUrl = $this->cloudinaryService->uploadImage($uploadedFile, 'user_avatars');
                    @unlink($tempImage);
                } catch (\Exception $e) {
                    $avatarUrl = $socialUser->getAvatar();
                }
            }

            $user = User::create([
                'username' => $username,
                'userid_DI' => 'user_' . Str::random(10),
                'email' => $socialUser->getEmail() ?? $socialUser->getId() . '@' . $provider . '.com',
                'password' => Hash::make('password'), // Placeholder password
                'avatar' => $avatarUrl,
                'final_cc_cname_DI' => $socialUser->getName() ?? 'Unknown',
                'role' => 'student',
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'learning_goals' => null,
                'total_courses_completed' => 0,
            ]);
        }

        $token = JWTAuth::fromUser($user);
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

        if ($user->role === 'instructor') {
            return response()->json([
                'error' => 'You are already an instructor.',
            ], 400);
        }

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
        $instructorRequest->update([
            'status' => $requestData['status'],
            'admin_notes' => $requestData['admin_notes'],
            'admin_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        $user = User::findOrFail($instructorRequest->user_id);
        if ($requestData['status'] === 'approved') {
            $user->update(['role' => 'instructor']);
            $instructor=Instructors::where('user_id', $instructorRequest->user_id)->first();
            if ($instructor) {
                $instructor->update([
                    'bio' => $instructorRequest->bio,
                    'organization' => $instructorRequest->organization,
                ]);
            } else {
                Instructors::create([
                'user_id' => $user->id,
                'name' => $instructorRequest->name,
                'bio' => $instructorRequest->bio,
                'organization' => $instructorRequest->organization,
            ]);
                Log::info('Creating new instructor record for user ID: ' . $user->id);
            }
              // Gửi email thông báo
            Mail::to($user->email)->send(new InstructorApprovalMail($user, $instructorRequest));
        }elseif ($requestData['status'] === 'rejected') {
            // Gửi email từ chối
            Mail::to($user->email)->send(new InstructorRejectionMail($user, $requestData['admin_notes']));
        }

        return response()->json([
            'message' => 'Instructor request reviewed successfully.',
            'request' => $instructorRequest,
        ], 200);
    }

    public function getCurrentUser()
    {
        try {
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
            if ($user->role === 'student') {
                $student = Student::where('user_id', $user->id)->first();
                if (!$student) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Student not found',
                    ], 404);
                }
                $user->student = $student;
            }
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

    // public function updateProfile(Request $request)
    // {
    //     try {
    //         $userId = Auth::id();
    //         if (!$userId) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Unauthorized or user not found',
    //             ], 401);
    //         }

    //         $user = User::findOrFail($userId);

    //         // Validate input
    //         $validatedData = $request->validate([
    //             'username' =>'required|string|max:50',
    //             'final_cc_cname_DI' => 'nullable|string|max:100',
    //             'LoE_DI' => 'nullable|string|max:50',
    //             'YoB' => 'nullable|integer|min:1900|max:' . (date('Y') - 13),
    //             'gender' => 'nullable|string|in:Male,Female,other',
    //             'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
    //             'learning_goals' => 'nullable|string',
    //             'category_ids' => 'nullable|array',
    //             'category_ids.*' => 'nullable|exists:categories,id',
    //             'bio' => 'nullable|string|max:1000',
    //             'organization' => 'nullable|string|max:100',
    //             'name' => 'nullable|string|max:100',
    //         ]);

    //         // Handle avatar upload
    //         if ($request->hasFile('avatar')) {
    //             try {
    //                 Log::info('Uploading avatar to Cloudinary');
    //                 if ($user->avatar && strpos($user->avatar, 'cloudinary.com') !== false) {
    //                     try {
    //                         $this->cloudinaryService->deleteByUrl($user->avatar);
    //                     } catch (\Exception $e) {
    //                         Log::error('Error deleting old avatar: ' . $e->getMessage());
    //                     }
    //                 }
    //                 $avatarUrl = $this->cloudinaryService->uploadImage($request->file('avatar'), 'user_avatars');
    //                 $validatedData['avatar'] = $avatarUrl;
    //             } catch (\Exception $e) {
    //                 Log::error('Avatar upload error: ' . $e->getMessage());
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Failed to upload avatar',
    //                     'error' => $e->getMessage()
    //                 ], 500);
    //             }
    //         }

    //         // Update user data
    //         $userDataToUpdate = array_intersect_key($validatedData, array_flip([
    //             'username', 'final_cc_cname_DI', 'LoE_DI', 'YoB', 'gender', 'avatar'
    //         ]));
    //         $user->fill($userDataToUpdate);
    //         $userIsDirty = $user->isDirty();
    //         if (!empty($userDataToUpdate)) {
    //             $user->update($userDataToUpdate);
    //         }
          
    //         $studentIsDirty = false;
    //         $instructorIsDirty = false;
    //         $categoryChanged = false;
    //         // Update role-based data
    //         if ($user->role === 'student') {
    //             $studentDataToUpdate = array_intersect_key($validatedData, array_flip([
    //                 'learning_goals'
    //             ]));
    //             if (!empty($studentDataToUpdate)) {
    //                 $student = $user->student;
    //                 if ($student) {
    //                     $student->update($studentDataToUpdate);
    //                 }
    //             }
    //             if (isset($validatedData['category_ids'])) {
    //                 $student = $user->student;
    //                 if ($student) {
    //                     $student->categories()->sync($validatedData['category_ids']);
    //                 }
    //             }
    //         } elseif ($user->role === 'instructor') {
    //             $instructorDataToUpdate = array_intersect_key($validatedData, array_flip([
    //                 'bio', 'organization', 'name'
    //             ]));
    //             if (!empty($instructorDataToUpdate)) {
    //                 $instructor = $user->instructor;
    //                 if ($instructor) {
    //                     $instructor->update($instructorDataToUpdate);
    //                 }
    //             }
    //         }

    //         $user = $user->fresh();
    //         if ($user->role === 'instructor') {
    //             $user->load('instructor');
    //         } elseif ($user->role === 'student') {
    //             $user->load('student', 'student.categories');
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Profile updated successfully',
    //             'user' => $user
    //         ]);
    //     } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'User not found',
    //             'error' => $e->getMessage()
    //         ], 404);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error updating profile',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
     public function updateProfile(Request $request)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized or user not found',
                ], 401);
            }

            $user = User::findOrFail($userId);

            // Validate input
            $validatedData = $request->validate([
                'username' =>'required|string|max:50',
                'LoE_DI' => 'nullable|string|max:50',
                'birthdate' => 'nullable|date_format:Y-m-d|before_or_equal:' . now()->subYears(13)->format('Y-m-d'), // Updated from YoB to birthdate
                'gender' => 'nullable|string|in:Male,Female,other',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'learning_goals' => 'nullable|string',
                'category_ids' => 'nullable|array',
                'category_ids.*' => 'nullable|exists:categories,id',
                'bio' => 'nullable|string|max:1000',
                'organization' => 'nullable|string|max:100',
                'name' => 'nullable|string|max:100',
            ]);

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                try {
                    Log::info('Uploading avatar to Cloudinary');
                    if ($user->avatar && strpos($user->avatar, 'cloudinary.com') !== false) {
                        try {
                            $this->cloudinaryService->deleteByUrl($user->avatar);
                        } catch (\Exception $e) {
                            Log::error('Error deleting old avatar: ' . $e->getMessage());
                        }
                    }
                    $avatarUrl = $this->cloudinaryService->uploadImage($request->file('avatar'), 'user_avatars');
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

            // Update user data
            $userDataToUpdate = array_intersect_key($validatedData, array_flip([
                'username', 'LoE_DI', 'birthdate', 'gender', 'avatar'
            ]));
            $user->fill($userDataToUpdate);
            $userIsDirty = $user->isDirty();
            if (!empty($userDataToUpdate)) {
                $user->update($userDataToUpdate);
            }
          
            $studentIsDirty = false;
            $instructorIsDirty = false;
            $categoryChanged = false;
            // Update role-based data
           if ($user->role === 'student') {
                $student = $user->student;
                if ($student) {
                    $studentDataToUpdate = array_intersect_key($validatedData, array_flip(['learning_goals']));
                    $student->fill($studentDataToUpdate);
                    $studentIsDirty = $student->isDirty();

                    if (isset($validatedData['category_ids'])) {
                        $existingIds = $student->categories()->pluck('categories.id')->toArray();
                        $incomingIds = $validatedData['category_ids'];
                        // Compare sorted arrays
                        $categoryChanged = array_diff($existingIds, $incomingIds) || array_diff($incomingIds, $existingIds);
                    }
                }
            } elseif ($user->role === 'instructor') {
                $instructor = $user->instructor;
                if ($instructor) {
                    $instructorDataToUpdate = array_intersect_key($validatedData, array_flip([
                        'bio', 'organization', 'name'
                    ]));
                    $instructor->fill($instructorDataToUpdate);
                    $instructorIsDirty = $instructor->isDirty();
                }
            }

            // ✅ Nếu không có thay đổi gì thì return luôn
            if (!$userIsDirty && !$studentIsDirty && !$instructorIsDirty && !$categoryChanged) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes detected.',
                    'user' => $user->fresh()
                ]);
            }

            // Nếu có thay đổi, mới tiến hành save như cũ
            if ($userIsDirty) {
                $user->save();
            }
            if ($studentIsDirty) {
                $student->save();
            }
            if ($categoryChanged) {
                $student->categories()->sync($validatedData['category_ids']);
            }
            if ($instructorIsDirty) {
                $instructor->save();
            }

            $user = $user->fresh();
            if ($user->role === 'instructor') {
                $user->load('instructor');
            } elseif ($user->role === 'student') {
                $user->load('student', 'student.categories');
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