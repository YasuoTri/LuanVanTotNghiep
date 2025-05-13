<?php

namespace App\Http\Controllers;

use App\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Http\Requests\Enrollment\StorePaidEnrollmentRequest;
use App\Http\Requests\Enrollment\UpdateEnrollmentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\LessonProgress;
use App\Models\Review;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;

class EnrollmentController extends Controller
{
    protected $paymentController;

    public function __construct(PaymentController $paymentController)
    {
        $this->paymentController = $paymentController;
    }
    public function index(): JsonResponse
    {
        $enrollments = Enrollment::all();
        return response()->json(['data' => $enrollments]);
    }

    public function show($id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        return response()->json(['data' => $enrollment]);
    }

   public function store(StoreEnrollmentRequest $request): JsonResponse
{
    // Lấy dữ liệu đã được validated
    $validated = $request->validated();

    // Kiểm tra xem enrollment đã tồn tại chưa
    $existingEnrollment = Enrollment::where('user_id', $validated['user_id'])
        ->where('course_id', $validated['course_id'])
        ->first();

    if ($existingEnrollment) {
        return response()->json([
            'message' => 'Bạn đã đăng ký khóa học này rồi'
        ], 409); // HTTP 409 Conflict là phù hợp cho trường hợp trùng lặp
    }

    // Tạo enrollment mới
    $enrollment = Enrollment::create($validated);

    return response()->json([
        'message' => 'Enrollment created successfully',
        'data' => $enrollment
    ], 201);
}
    public function update(UpdateEnrollmentRequest $request, $id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        $enrollment->update($request->validated());
        return response()->json(['message' => 'Enrollment updated successfully', 'data' => $enrollment]);
    }

    public function destroy($id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        $payment = Payment::where('user_id', $enrollment->user_id)
    ->where('course_id', $enrollment->course_id)
    ->where('status', 'completed')
    ->first();
if ($payment) {
    return response()->json(['message' => 'Cannot unenroll from a paid course without a refund process'], 400);
}
        $enrollment->delete();
        return response()->json(['message' => 'Enrollment deleted successfully']);
    }
    public function getStudentEnrollments(): JsonResponse
    {
        $user = Auth::user();
        
        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        $enrollments = Enrollment::where('user_id', $user->id)
            ->with(['course' => function ($query) {
                $query->select('id', 'course_name', 'university', 'difficulty_level', 'course_rating');
            }])
            ->select('id', 'user_id', 'course_id', 'enrolled_at', 'completed_at', 'expires_at', 'status')
            ->orderBy('enrolled_at', 'desc')
            ->get();

        return response()->json(['data' => $enrollments]);
    }
      public function checkEnrollmentStatus($id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        $enrollment = Enrollment::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['course' => function ($query) {
                $query->select('id', 'course_name', 'university');
            }])
            ->select('id', 'user_id', 'course_id', 'status', 'enrolled_at', 'completed_at', 'expires_at')
            ->firstOrFail();

        return response()->json(['data' => $enrollment]);
    }
     public function completeEnrollment($id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        $enrollment = Enrollment::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Kiểm tra xem enrollment đã hoàn thành chưa
        if ($enrollment->status === 'completed') {
            return response()->json(['message' => 'Enrollment is already completed'], 400);
        }

        // Kiểm tra tiến trình lesson để đảm bảo tất cả lesson đã hoàn thành
        $lessonsCompleted = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', function ($query) use ($enrollment) {
                $query->select('id')
                    ->from('lessons')
                    ->where('course_id', $enrollment->course_id);
            })
            ->where('status', 'completed')
            ->count();

        $totalLessons = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', function ($query) use ($enrollment) {
                $query->select('id')
                    ->from('lessons')
                    ->where('course_id', $enrollment->course_id);
            })
            ->count();

        if ($lessonsCompleted < $totalLessons) {
            return response()->json(['message' => 'Cannot complete enrollment: Not all lessons are completed'], 400);
        }

        // Cập nhật trạng thái enrollment
        $enrollment->status = 'completed';
        $enrollment->completed_at = now();
        $enrollment->save();

        // Cập nhật số khóa học hoàn thành của student
        $student = $user->student;
        if ($student) {
            $student->total_courses_completed += 1;
            $student->save();
        }

        return response()->json(['message' => 'Enrollment marked as completed', 'data' => $enrollment]);
    }
      public function getLearningProgress($id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        $enrollment = Enrollment::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $progress = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', function ($query) use ($enrollment) {
                $query->select('id')
                    ->from('lessons')
                    ->where('course_id', $enrollment->course_id);
            })
            ->with(['lesson' => function ($query) {
                $query->select('id', 'title', 'duration', 'is_preview', 'sort_order');
            }])
            ->select('id', 'user_id', 'lesson_id', 'status', 'completed_at')
            ->get();

        $totalLessons = $progress->count();
        $completedLessons = $progress->where('status', 'completed')->count();
        $progressPercentage = $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0;

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'course_id' => $enrollment->course_id,
                'progress' => $progress,
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress_percentage' => round($progressPercentage, 2)
            ]
        ]);
    }
     public function submitReview(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        $enrollment = Enrollment::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);

        // Kiểm tra xem đã có review cho khóa học này chưa
        $existingReview = Review::where('user_id', $user->id)
            ->where('course_id', $enrollment->course_id)
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this course'], 400);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'course_id' => $enrollment->course_id,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);

        return response()->json(['message' => 'Review submitted successfully', 'data' => $review], 201);
    }


    /**
     * Enroll in a free course.
     */
    public function enrollFreeCourse(Request $request, $course_id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        // Check if course exists and is free
        $course = Course::find($course_id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

// Check if the course is free (price == 0)
    if ($course->price > 0) {
        return response()->json(['message' => 'This course requires payment. Please use the payment process.'], 400);
    }

        // Check for existing enrollment
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course_id)
            ->first();

        if ($existingEnrollment) {
            return response()->json(['message' => 'You are already enrolled in this course'], 409);
        }

        // Create enrollment
        $enrollment = Enrollment::create([
            'user_id' => $user->id,
            'course_id' => $course_id,
            'enrolled_at' => now(),
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Successfully enrolled in free course',
            'data' => $enrollment
        ], 201);
    }
   public function storePaid(StorePaidEnrollmentRequest $request, $course_id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        // Check if course exists and is paid
        $course = Course::find($course_id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        if ($course->price <= 0) {
            return response()->json(['message' => 'This course is free. Use the free enrollment endpoint.'], 400);
        }

        // Check if already enrolled
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course_id)
            ->where('status', 'active')
            ->first();

        if ($existingEnrollment) {
            return response()->json(['message' => 'You are already enrolled in this course'], 409);
        }

        // Initiate payment
        $paymentResponse = $this->paymentController->store(
            $request->validated(), // Pass validated data as array
            $user->id,
            $course_id
        );

        return $paymentResponse;
    }
}