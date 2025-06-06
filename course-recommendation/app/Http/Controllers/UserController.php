<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\RevenueDistribution;
use App\Models\User;
use App\Models\Admins;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use App\Traits\DetectAndUpdateIfChanged;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use DetectAndUpdateIfChanged;
    /**
     * Display a listing of users with filters for admin.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Kiểm tra vai trò admin
        $admin = Auth::user();
      
        // Lấy query cơ bản
        $query = User::query()->select([
            'id',
            'username',
            'userid_DI',
            'email',
            'avatar',
            'final_cc_cname_DI',
            'LoE_DI',
            'YoB',
            'gender',
            'role',
            'created_at',
            'updated_at',
            'deleted_at'
        ]);

        // Xử lý filter with_trashed
        if ($request->has('with_trashed')) {
            $withTrashed = $request->input('with_trashed');
            if ($withTrashed === '1') {
                $query->withTrashed(); // Lấy cả users đã soft delete
            } elseif ($withTrashed === 'only') {
                $query->onlyTrashed(); // Chỉ lấy users đã soft delete
            }
        }

        if ($request->has('username')) {
            $query->where('username', $request->input('username'));
        }
        // Lọc theo role
        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        // Lọc theo email
        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }

        // Lọc theo gender
        if ($request->has('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        // Lọc theo năm sinh (YoB)
        if ($request->has('yob')) {
            $query->where('YoB', $request->input('yob'));
        }

        // Lọc theo trình độ học vấn (LoE_DI)
        if ($request->has('loe_di')) {
            $query->where('LoE_DI', $request->input('loe_di'));
        }

        // Lọc theo quốc gia (final_cc_cname_DI)
        if ($request->has('country')) {
            $query->where('final_cc_cname_DI', $request->input('country'));
        }

        // Phân trang (20 user/trang)
        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        // Ghi log hoạt động admin
        $logMessage = 'Viewed users list';
        if ($request->has('with_trashed')) {
            $logMessage .= $withTrashed === 'only' ? ' (only trashed)' : ' (including trashed)';
        }
        // Admins::where('user_id', $admin->id)->update([
        //     'activity_log' => DB::raw("CONCAT(COALESCE(activity_log, ''), '\n', '{$logMessage} at ', NOW())")
        // ]);

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users
        ], 200);
    }
     public function trashed(Request $request): JsonResponse
    {
        // Kiểm tra vai trò admin
        $admin = Auth::user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized: Only admins can view trashed users'
            ], 403);
        }

        // Lấy query cơ bản cho users đã soft delete
        $query = User::onlyTrashed()->select([
            'id',
            'username',
            'userid_DI',
            'email',
            'avatar',
            'final_cc_cname_DI',
            'LoE_DI',
            'YoB',
            'gender',
            'role',
            'created_at',
            'updated_at',
            'deleted_at'
        ]);
                // Lọc theo role
        if ($request->has('username')) {
            $query->where('username', $request->input('username'));
        }
        // Lọc theo role
        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        // Lọc theo email
        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->input('email') . '%');
        }

        // Lọc theo gender
        if ($request->has('gender')) {
            $query->where('gender', $request->input('gender'));
        }

        // Lọc theo năm sinh (YoB)
        if ($request->has('yob')) {
            $query->where('YoB', $request->input('yob'));
        }

        // Lọc theo trình độ học vấn (LoE_DI)
        if ($request->has('loe_di')) {
            $query->where('LoE_DI', $request->input('loe_di'));
        }

        // Lọc theo quốc gia (final_cc_cname_DI)
        if ($request->has('country')) {
            $query->where('final_cc_cname_DI', $request->input('country'));
        }

        // Phân trang (20 user/trang)
        $users = $query->orderBy('deleted_at', 'desc')->paginate(10);
        
        // // Ghi log hoạt động admin
        // Admins::where('user_id', $admin->id)->update([
        //     'activity_log' => DB::raw("CONCAT(COALESCE(activity_log, ''), '\n', 'Viewed trashed users list at ', NOW())")
        // ]);

        return response()->json([
            'message' => 'Trashed users retrieved successfully',
            'data' => $users
        ], 200);
    }

  /**
 * Display the specified user for admin with analytics.
 *
 * @param int $id
 * @return JsonResponse
 */
public function show($id): JsonResponse
{
    // Check admin role
    $admin = Auth::user();
    if (!$admin || $admin->role !== 'admin') {
        return response()->json([
            'message' => 'Unauthorized: Only admins can view user details'
        ], 403);
    }

    // Find user with relevant relationships
    $user = User::with([
        'enrollments' => function ($query) {
            $query->select('id', 'user_id', 'course_id', 'status', 'enrolled_at', 'completed_at');
        },
        'certificates' => function ($query) {
            $query->select('id', 'user_id', 'course_id', 'certificate_code', 'issued_at');
        },
        'forumPosts' => function ($query) {
            $query->select('id', 'user_id', 'course_id', 'title', 'flagged', 'created_at');
        },
        'payments' => function ($query) {
            $query->select('id', 'user_id', 'course_id', 'amount', 'status', 'payment_date');
        },
        'lessonProgress' => function ($query) {
            $query->select('id', 'user_id', 'lesson_id', 'status', 'completed_at');
        },
        'reviews' => function ($query) {
            $query->select('id', 'user_id', 'course_id', 'rating');
        },
        'quizResults' => function ($query) {
            $query->select('id', 'user_id', 'quiz_id', 'score', 'attempt_number');
        },
        'interactions' => function ($query) {
            $query->select(
                'id',
                'user_id',
                'course_id',
                'rating',
                'nevents',
                'ndays_act',
                'nplay_video',
                'nchapters',
                'nforum_posts'
            );
        },
        'student.categories' => function ($query) {
            $query->select('categories.id', 'categories.name');
        },
        'instructor.courses' => function ($query) {
            $query->select('courses.id', 'course_name', 'course_rating');
        }
    ])->select([
        'id',
        'username',
        'userid_DI',
        'email',
        'avatar',
        'final_cc_cname_DI',
        'LoE_DI',
        'YoB',
        'gender',
        'role',
        'created_at',
        'updated_at'
    ])->find($id);

    if (!$user) {
        return response()->json([
            'message' => 'User not found'
        ], 404);
    }

    // Admin data if user is an admin
    $adminData = null;
    if ($user->role === 'admin') {
        $adminData = Admins::where('user_id', $user->id)
            ->select('id', 'admin_level', 'activity_log')
            ->first();
    }

    // Instructor revenue data if user is an instructor
    $instructorRevenue = null;
    if ($user->role === 'instructor') {
        $instructorRevenue = RevenueDistribution::where('instructor_id', $user->instructor->id)
            ->where('status', 'completed')
            ->sum('instructor_share');
    }

    // Analytics calculations
    $analytics = [
        'total_courses_enrolled' => $user->enrollments->count(),
        'courses_completed' => $user->enrollments->where('status', 'completed')->count(),
        'certificates_earned' => $user->certificates->count(),
        'total_payments_made' => $user->payments->where('status', 'completed')->sum('amount'),
        'average_rating_given' => $user->reviews->count() > 0
            ? round($user->reviews->avg('rating'), 2)
            : null,
        'forum_engagement' => [
            'total_posts' => $user->forumPosts->count(),
            'flagged_posts' => $user->forumPosts->where('flagged', 1)->count(),
        ],
        'lesson_completion_rate' => $this->calculateLessonCompletionRate($user),
        'average_quiz_score' => $user->quizResults->count() > 0
            ? round($user->quizResults->avg('score'), 2)
            : null,
        'interaction_metrics' => $user->interactions->map(function ($interaction) {
            return [
                'course_id' => $interaction->course_id,
                'rating' => $interaction->rating,
                'total_events' => $interaction->nevents,
                'active_days' => $interaction->ndays_act,
                'video_plays' => $interaction->nplay_video,
                'chapters_completed' => $interaction->nchapters,
                'forum_posts' => $interaction->nforum_posts,
            ];
        })->toArray(),
        'categories_of_interest' => $user->role === 'student'
            ? $user->student->categories->pluck('name')->toArray()
            : [],
        'instructor_metrics' => $user->role === 'instructor' && $user->instructor
            ? [
                'courses_taught' => $user->instructor->courses->count(),
                'total_revenue_earned' => (float) $instructorRevenue,
            ]
            : null,
    ];

    return response()->json([
        'message' => 'User retrieved successfully',
        'data' => [
            'user' => $user,
            'admin_data' => $adminData,
            'analytics' => $analytics,
        ]
    ], 200);
}

/**
 * Calculate the lesson completion rate for a user.
 *
 * @param User $user
 * @return float|null
 */
protected function calculateLessonCompletionRate($user)
{
    // Get all enrolled course IDs
    $courseIds = $user->enrollments->pluck('course_id')->toArray();
    if (empty($courseIds)) {
        return null;
    }

    // Count total lessons in enrolled courses
    $totalLessons = Lesson::whereIn('course_id', $courseIds)->count();

    // Count completed lessons
    $completedLessons = $user->lessonProgress->where('status', 'completed')->count();

    // Calculate completion rate
    return $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : null;
}
    /**
     * Store a newly created user in storage.
     *
     * @param StoreUserRequest $request
     * @return JsonResponse
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = User::create($request->validated());

            // Nếu role là admin, tạo bản ghi trong bảng admins
            if ($user->role === 'admin') {
                Admins::create([
                    'user_id' => $user->id,
                    'admin_level' => $request->admin_level,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'User created successfully',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  public function update(UpdateUserRequest $request, $id): JsonResponse
{
    // Kiểm tra vai trò admin
    $admin = Auth::user();
    if (!$admin || $admin->role !== 'admin') {
        return response()->json([
            'message' => 'Unauthorized: Only admins can update users'
        ], 403);
    }

    // Tìm user (chỉ lấy users chưa bị soft delete)
    $user = User::find($id);
    if (!$user) {
        return response()->json([
            'message' => 'User not found'
        ], 404);
    }

    // Validate request
    $validated = $request->validate([
        'email' => [
            'sometimes',
            'email',
            Rule::unique('users', 'email')->ignore($user->id)
        ],
        'password' => 'sometimes|string|min:8|max:255',
        'avatar' => 'sometimes|string|max:255',
        'final_cc_cname_DI' => 'sometimes|string|max:100',
        'LoE_DI' => 'sometimes|string|max:50',
        'YoB' => 'sometimes|integer|min:1900|max:' . date('Y'),
        'gender' => 'sometimes|string|max:20',
        'role' => 'sometimes|in:student,instructor,admin',
        'admin_level' => 'required_if:role,admin|in:organization,program'
    ]);

    // Chuẩn bị dữ liệu cập nhật
    $updateData = array_filter([
        'email' => $validated['email'] ?? $user->email,
        'password' => isset($validated['password']) && !Hash::check($validated['password'], $user->password)
            ? bcrypt($validated['password'])
            : $user->password,
        'avatar' => $validated['avatar'] ?? $user->avatar,
        'final_cc_cname_DI' => $validated['final_cc_cname_DI'] ?? $user->final_cc_cname_DI,
        'LoE_DI' => $validated['LoE_DI'] ?? $user->LoE_DI,
        'YoB' => $validated['YoB'] ?? $user->YoB,
        'gender' => $validated['gender'] ?? $user->gender,
        'role' => $validated['role'] ?? $user->role,
    ], fn($value, $key) => $key === 'YoB' ? (int)$value !== (int)$user->$key : $value !== $user->$key, ARRAY_FILTER_USE_BOTH);

    // Kiểm tra xem có thay đổi nào không
    if (empty($updateData)) {
        return response()->json([
            'message' => 'No changes detected'
        ], 200);
    }

    // Bắt đầu transaction
    DB::beginTransaction();
    try {
        // Cập nhật user
        $user->update($updateData);

        // Cập nhật hoặc tạo bản ghi trong bảng admins nếu role là admin
        if (isset($validated['role']) && $validated['role'] === 'admin') {
            Admins::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'admin_level' => $validated['admin_level'],
                    'updated_at' => now()
                ]
            );
        } elseif ($user->role === 'admin' && (!isset($validated['role']) || $validated['role'] !== 'admin')) {
            // Xóa bản ghi admin nếu role thay đổi từ admin sang role khác
            Admins::where('user_id', $user->id)->delete();
        }

        DB::commit();

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user->fresh()
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to update user',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Soft delete the specified user for admin.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        // Kiểm tra vai trò admin
        $admin = Auth::user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized: Only admins can delete users'
            ], 403);
        }

        // Tìm user (chỉ lấy users chưa bị soft delete)
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Không cho phép admin tự xóa chính mình
        if ($user->id === $admin->id) {
            return response()->json([
                'message' => 'Admins cannot delete themselves'
            ], 422);
        }

        // Bắt đầu transaction
        DB::beginTransaction();
        try {
            // Soft delete user
            $user->delete();
            if ($user->role === 'instructor') {
                // Xóa bản ghi instructor nếu user là instructor
                $user->instructor()->delete();
            } elseif ($user->role === 'student') {
                // Xóa bản ghi student nếu user là student
                $user->student()->delete();
            }

            // Xóa bản ghi admin nếu user là admin (vì admins không có soft delete)
            Admins::where('user_id', $user->id)->delete();

            // Ghi log hoạt động admin
            // Admins::where('user_id', $admin->id)->update([
            //     'activity_log' => DB::raw("CONCAT(COALESCE(activity_log, ''), '\n', 'Soft deleted user ID {$id} at ', NOW())")
            // ]);

            DB::commit();

            return response()->json([
                'message' => 'User soft deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to soft delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore($id): JsonResponse
    {
        // Kiểm tra vai trò admin
        $admin = Auth::user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized: Only admins can restore users'
            ], 403);
        }

        // Tìm user đã bị soft delete
        $user = User::onlyTrashed()->find($id);
        if (!$user) {
            return response()->json([
                'message' => 'Soft deleted user not found'
            ], 404);
        }

        // Bắt đầu transaction
        DB::beginTransaction();
        try {
            // Khôi phục user
            $user->restore();
            if ($user->role === 'instructor') {
                // Khôi phục bản ghi instructor nếu user là instructor
                $user->instructor()->restore();
            } elseif ($user->role === 'student') {
                // Khôi phục bản ghi student nếu user là student
                $user->student()->restore();
            }

            // Ghi log hoạt động admin
            // Admins::where('user_id', $admin->id)->update([
            //     'activity_log' => DB::raw("CONCAT(COALESCE(activity_log, ''), '\n', 'Restored user ID {$id} at ', NOW())")
            // ]);

            DB::commit();

            return response()->json([
                'message' => 'User restored successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to restore user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete a soft-deleted user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function forceDelete($id): JsonResponse
    {
        // Kiểm tra vai trò admin
        $admin = Auth::user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized: Only admins can permanently delete users'
            ], 403);
        }

        // Tìm user đã bị soft delete
        $user = User::onlyTrashed()->find($id);
        if (!$user) {
            return response()->json([
                'message' => 'Soft deleted user not found'
            ], 404);
        }

        // Không cho phép admin tự xóa chính mình
        if ($user->id === $admin->id) {
            return response()->json([
                'message' => 'Admins cannot permanently delete themselves'
            ], 422);
        }

        // Bắt đầu transaction
        DB::beginTransaction();
        try {
            // Xóa vĩnh viễn user (các bảng liên quan sẽ tự động xóa do ON DELETE CASCADE)
            $user->forceDelete();
            if ($user->role === 'instructor') {
                // Xóa bản ghi instructor nếu user là instructor
                $user->instructor()->forceDelete();
            } elseif ($user->role === 'student') {
                // Xóa bản ghi student nếu user là student
                $user->student()->forceDelete();
            }

            // Ghi log hoạt động admin
            // Admins::where('user_id', $admin->id)->update([
            //     'activity_log' => DB::raw("CONCAT(COALESCE(activity_log, ''), '\n', 'Permanently deleted user ID {$id} at ', NOW())")
            // ]);

            DB::commit();

            return response()->json([
                'message' => 'User permanently deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to permanently delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
{
    $query = User::query();

    if ($request->filled('email')) {
        $query->where('email', 'like', '%' . $request->email . '%');
    }

    if ($request->filled('role')) {
        $query->where('role', $request->role);
    }

    if ($request->filled('gender')) {
        $query->where('gender', $request->gender);
    }

    return response()->json($query->get());
}

}