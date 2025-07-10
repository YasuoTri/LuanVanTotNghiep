<?php

namespace App\Http\Controllers;

use App\Http\Requests\Interaction\UpdateInteractionRequest;
use App\Http\Requests\StoreInstructorRequest;
use App\Models\Course;
use Illuminate\Http\Request;
use App\Models\Instructors;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InstructorController extends Controller
{
    public function index()
    {
        $instructors = Instructors::paginate(10);
        return response()->json($instructors);
    }
      public function indexWithoutAuthentication()
    {
        $users=User::with('instructor')->where('role', 'instructor')->paginate(10);
        if ($users->isEmpty()) {
            return response()->json(['message' => 'No instructors found'], 404);
        }
        return response()->json($users);
    }
    
    public function show($id)
    {
        $instructor = Instructors::find($id);
        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }
        return response()->json($instructor);
    }
    public function store(StoreInstructorRequest $request)
    {
        $instructor = Instructors::create($request->all());
        return response()->json($instructor, 201);
    }
    public function update(UpdateInteractionRequest $request, $id)
    {
        $instructor = Instructors::find($id);
        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }
        $instructor->fill($request->all());
        if (!$instructor->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 200);
        }
        $instructor->update($request->all());
        return response()->json($instructor);
    }
    public function destroy($id)
    {
        $instructor = Instructors::find($id);
        if (!$instructor) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }
        // Check if the instructor has any associated courses
        if ($instructor->courses()->exists()) {
            return response()->json(['message' => 'Cannot delete instructor with associated courses'], 400);
        }
       $user = User::find($instructor->user_id);
        if ($user) {
            $user->delete();
        }
        $instructor->delete();
        return response()->json(['message' => 'Instructor deleted successfully']);
    }

     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments = Instructors::onlyTrashed()->paginate(10);
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment = Instructors::onlyTrashed()->findOrFail($id);
        if (!$enrollment) {
            return response()->json(['message' => 'Instructor not found'], 404);
        }
        $user = User::withTrashed()->find($enrollment->user_id);
        if ($user) {
            $user->restore();
        }
        $enrollment->restore();
        return response()->json(['message' => 'Instructor restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        $enrollment = Instructors::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        return response()->json(['message' => 'Instructor permanently deleted'], 200);
    }

    public function search(Request $request)
{
    $query = Instructors::query();

    if ($request->filled('name')) {
        $query->where('name', 'like', '%' . $request->name . '%');
    }

    if ($request->filled('organization')) {
        $query->where('organization', 'like', '%' . $request->organization . '%');
    }

    return response()->json($query->paginate(10));
}
/**
     * Get the top 10 outstanding instructors based on enrollments, course ratings, and course count.
     *
     * @return JsonResponse
     */
   public function getTopInstructors(): JsonResponse
{
    try {
        // Truy vấn top 10 giảng viên
        $topInstructors = Instructors::select([
            'instructors.id as instructor_id',
            'users.username as instructor_name',
            'instructors.user_id as user_id',
            DB::raw('COUNT(DISTINCT courses.id) as course_count'),
            DB::raw('COALESCE(AVG(courses.course_rating), 0) as avg_course_rating'),
            DB::raw('COUNT(DISTINCT enrollments.id) as total_enrollments')
        ])
        ->join('users', 'instructors.user_id', '=', 'users.id')
        ->leftJoin('courses', function ($join) {
            $join->on('instructors.id', '=', 'courses.instructor_id')
                 ->whereNull('courses.deleted_at'); // Bỏ qua các khóa học đã soft-delete
        })
        ->leftJoin('enrollments', 'courses.id', '=', 'enrollments.course_id')
        ->groupBy('instructors.id', 'users.username', 'instructors.user_id')
        ->orderByDesc('total_enrollments')
        ->orderByDesc('avg_course_rating')
        ->orderByDesc('course_count')
        ->take(10)
        ->get();

        // Format kết quả trả về
        $response = $topInstructors->map(function ($instructor) {
            $instructor_info = Instructors::find($instructor->instructor_id);
            $user_info = User::find($instructor->user_id);
            return [
                'instructor_id' => $instructor->instructor_id,
                'name' => $instructor->instructor_name,
                'instructor_profile' => $instructor_info ?? null,
                'user_info' => $user_info ?? null,
                'course_count' => $instructor->course_count,
                'avg_course_rating' => round($instructor->avg_course_rating, 2),
                'total_enrollments' => $instructor->total_enrollments,
            ];
        });

        return response()->json([
            'message' => 'Top 10 instructors retrieved successfully',
            'data' => $response
        ], 200);

    } catch (\Exception $e) {
        Log::error('Error retrieving top instructors: ' . $e->getMessage());
        return response()->json([
            'error' => 'An error occurred while retrieving top instructors',
            'message' => $e->getMessage()
        ], 500);
    }
}
public function createInstructorProfile(Request $request): JsonResponse
{
    $user = Auth::user();

    // Check if user already has an instructor profile
    if ($user->instructor) {
        return response()->json([
            'message' => 'Instructor profile already exists.'
        ], 409);
    }
    $user1=User::find($user->id);
    $user1->role = 'instructor';
    $user1->save();
    // Create instructor profile
    $instructor = Instructors::create([
        'user_id' => $user->id,
        'bio' => $request->input('bio'),
        'organization' => $request->input('organization'),
        'email_paypal' => $request->input('email_paypal'),
    ]);

    return response()->json([
        'message' => 'Instructor profile created successfully',
        'data' => $instructor
    ], 201);
}

public function getInstructorCourses(Request $request,$instructorId)
    {

        // Lấy thông tin instructor từ bảng users và instructors
        $instructor = Instructors::select(
            'instructors.id as instructor_id',
            'instructors.bio',
            'instructors.organization',
            'instructors.email_paypal',
            'users.username',
            'users.fullname',
            'users.email',
            'users.birthdate',
            'users.gender',
            'users.avatar',
            'users.role',
            'users.status'
        )
        ->join('users', 'instructors.user_id', '=', 'users.id')
        ->where('instructors.id', $instructorId)
        ->first();

        if (!$instructor) {
            return response()->json([
                'status' => 'error',
                'message' => 'Instructor not found.',
            ], 404);
        }

        // Lấy danh sách khóa học của instructor
        $courses = Course::select(
            'courses.id as course_id',
            'courses.course_name',
            'courses.difficulty_level',
            'courses.course_rating',
            'courses.course_url',
            'courses.image',
            'courses.course_description',
            'courses.is_certificate_enabled',
            'courses.price',
            'courses.skills',
            'courses.status',
            'courses.is_certificate_enabled',
            DB::raw('COUNT(DISTINCT enrollments.user_id) as total_students'),
            DB::raw('COUNT(DISTINCT reviews.id) as total_reviews'),
            DB::raw('COUNT(DISTINCT reports.id) as total_reports')
        )
        ->leftJoin('enrollments', 'courses.id', '=', 'enrollments.course_id')
        ->leftJoin('reviews', 'courses.id', '=', 'reviews.course_id')
        ->leftJoin('reports', 'courses.id', '=', 'reports.course_id')
        ->where('courses.instructor_id', $instructorId)
        ->groupBy(
            'courses.id',
            'courses.course_name',
            'courses.difficulty_level',
            'courses.course_rating',
            'courses.course_url',
            'courses.image',
            'courses.course_description',
            'courses.price',
            'courses.skills',
            'courses.status',
            'courses.is_certificate_enabled'
        )
        ->limit(3)
        ->get();

        // Tính tổng của tổng
        $totalSummary = [
            'total_students' => Course::where('instructor_id', $instructorId)
                ->join('enrollments', 'courses.id', '=', 'enrollments.course_id')
                ->distinct('enrollments.user_id')
                ->count('enrollments.user_id'),
            'total_reviews' => Course::where('instructor_id', $instructorId)
                ->join('reviews', 'courses.id', '=', 'reviews.course_id')
                ->count('reviews.id'),
            'total_reports' => Course::where('instructor_id', $instructorId)
                ->join('reports', 'courses.id', '=', 'reports.course_id')
                ->count('reports.id'),
        ];

        // Trả về response JSON
        return response()->json([
            'status' => 'success',
            'data' => [
                'instructor' => $instructor,
                'courses' => $courses,
                'total_summary' => $totalSummary,
            ],
        ], 200);
    }
}
