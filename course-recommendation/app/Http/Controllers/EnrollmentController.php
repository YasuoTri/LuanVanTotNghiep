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
use App\Models\Certificate;
use Carbon\Carbon;
use App\Http\Controllers\PaymentController;
use App\Http\Requests\Enrollment\Renew;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
            'message' => 'You are already enrolled in this course',
        ], 409); // HTTP 409 Conflict là phù hợp cho trường hợp trùng lặp
    }

    // Tạo enrollment mới
    $enrollment = Enrollment::create($validated);

    return response()->json([
        'message' => 'Enrollment created successfully',
        'data' => $enrollment
    ], 201);
}
   /**
     * Cập nhật enrollment (gia hạn thời gian truy cập)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
  public function updateAdmin(UpdateEnrollmentRequest $request, $id)
{
    try {
        // Tìm enrollment theo ID
        $enrollment = Enrollment::findOrFail($id);

        // Kiểm tra trạng thái enrollment
        if ($enrollment->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only active enrollments can be updated.'
            ], 400);
        }

        // Cập nhật enrollment với dữ liệu hợp lệ
        $enrollment->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Enrollment updated successfully.',
            'data' => $enrollment
        ], 200);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Enrollment not found.'
        ], 404);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred while updating enrollment.',
            'error' => $e->getMessage() // Có thể bật khi debug
        ], 500);
    }
}
//Renew an enrollment
    public function update(Renew $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Find the enrollment
            $enrollment = Enrollment::where('id', $id)
                ->where('user_id', Auth::user()->id)
                ->first();

            if (!$enrollment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Enrollment not found or you do not have permission.'
                ], 404);
            }

            // Check enrollment status
            if ($enrollment->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only active enrollments can be renewed.'
                ], 400);
            }

            // Check if expires_at is still valid or close to expiring
            if (!$enrollment->expires_at || Carbon::parse($enrollment->expires_at)->isPast()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Enrollment has expired and cannot be renewed.'
                ], 400);
            }

            // Get course details for renewal fee
            $course = Course::find($enrollment->course_id);
            if (!$course) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Course not found.'
                ], 404);
            }

            // Calculate renewal fee (e.g., 50% of course price)
            $renewalFee = $course->price * 0.5;
            if ($renewalFee <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid renewal fee.'
                ], 400);
            }

            // Prepare payment data
            $paymentData = [
                'amount' => $renewalFee,
                'method' => $request->validated()['payment_method'],
                'coupon_id' => null, // Optionally allow coupons in the future
                'payment_date' => null,
            ];

            // Initiate payment using PaymentController::store
            $paymentResponse = $this->paymentController->store(
                $paymentData,
                Auth::user()->id,
                $enrollment->course_id
            );

            // Check if payment initiation was successful
            if ($paymentResponse->getStatusCode() !== 201) {
                return $paymentResponse; // Return error from PaymentController
            }

            // Extract payment from response
            $payment = Payment::find(json_decode($paymentResponse->getContent(), true)['data']['id']);
            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to retrieve payment record.'
                ], 500);
            }

            // Simulate VNPay payment success (since VNPay is not fully implemented)
            $payment->status = 'completed';
            $payment->transaction_code = 'VNPAY_' . time();
            $payment->payment_date = Carbon::now();
            $payment->save();

            // Update expires_at
            $enrollment->expires_at = Carbon::parse($enrollment->expires_at)->addMonths(3);
            $enrollment->save();

            // Log the renewal
            Log::info('Enrollment renewed', [
                'enrollment_id' => $enrollment->id,
                'user_id' => Auth::user()->id,
                'course_id' => $enrollment->course_id,
                'payment_id' => $payment->id,
                'new_expires_at' => $enrollment->expires_at
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Enrollment renewed successfully.',
                'data' => [
                    'enrollment_id' => $enrollment->id,
                    'course_id' => $enrollment->course_id,
                    'expires_at' => $enrollment->expires_at,
                    'payment_id' => $payment->id,
                    'amount_paid' => $payment->amount,
                    'payment_status' => $payment->status,
                    'transaction_code' => $payment->transaction_code
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Enrollment renewal failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'enrollment_id' => $id
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while renewing enrollment: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Xóa enrollment (hủy đăng ký khóa học)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Tìm enrollment theo ID và đảm bảo thuộc về student
            $enrollment = Enrollment::where('id', $id)
                ->where('user_id', Auth::user()->id)
                ->first();

            if (!$enrollment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Enrollment not found or you do not have permission.'
                ], 404);
            }

            // Kiểm tra trạng thái enrollment
            if ($enrollment->status !== 'active' || $enrollment->completed_at) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only active and incomplete enrollments can be cancelled.'
                ], 400);
            }

            // Kiểm tra thời gian hủy (ví dụ: chỉ cho phép trong 7 ngày)
            $enrollmentDate = Carbon::parse($enrollment->enrolled_at);
            if ($enrollmentDate->diffInDays(Carbon::now()) > 7) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cancellation period has expired (7 days).'
                ], 400);
            }

            // Kiểm tra thanh toán liên quan
            $payment = Payment::where('course_id', $enrollment->course_id)
                ->where('user_id', Auth::user()->id)
                ->where('status', 'completed')
                ->first();

            if ($payment) {
                // TODO: Xử lý hoàn tiền nếu cần (cập nhật payments.status thành 'refunded')
                // Ví dụ: $payment->status = 'refunded'; $payment->save();
            }

            // Vô hiệu hóa certificate nếu có
            Certificate::where('enrollment_id', $enrollment->id)->delete();

            // Xóa enrollment
            $enrollment->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Enrollment cancelled successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while cancelling enrollment.'
            ], 500);
        }
    }
    // public function getStudentEnrollments(): JsonResponse
    // {
    //     $user = Auth::user();
        
    //     if ($user->role !== 'student') {
    //         return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
    //     }

    //    $enrollments = Enrollment::where('user_id', $user->id)
    // ->whereHas('course', function ($query) {
    //     $query->whereIn('status', ['approved', 'available', 'unavailable']);
    // })
    // ->with('course','course.reports')
    // ->select('id', 'user_id', 'course_id', 'enrolled_at', 'completed_at', 'status')
    // ->orderBy('enrolled_at', 'desc')
    // ->paginate(10);

        
    //     return response()->json(['data' => $enrollments]);
    // }
    public function getStudentEnrollments(): JsonResponse
{
    $user = Auth::user();

    $enrollments = Enrollment::where('user_id', $user->id)
        ->whereHas('course', function ($query) {
            $query->whereIn('status', ['approved', 'available', 'unavailable']);
        })
        ->with(['course', 'course.reports' => function($query) use ($user) {
            $query->where('user_id', $user->id); // chỉ lấy report của user này
        }])
        ->select('id', 'user_id', 'course_id', 'enrolled_at', 'completed_at', 'status')
        ->orderBy('enrolled_at', 'desc')
        ->paginate(10);

    // Thêm biến has_pending_report
    $enrollments->getCollection()->transform(function ($enrollment) use ($user) {
        $pendingReport = $enrollment->course->reports->first();
        $enrollment->has_pending_report = $pendingReport ? true : false;
        $enrollment->pending_report_id = $pendingReport ? $pendingReport->id : null;
        return $enrollment;
    });

    return response()->json(['data' => $enrollments]);
}

      public function checkEnrollmentStatus($id): JsonResponse
    {
        $user = Auth::user();

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
        
        $totalLessons = $lessons = Lesson::where('course_id', $enrollment->course_id)->count();
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
        $enrollment = Enrollment::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'feedback_type' => 'nullable|string|max:1000',
        ]);

        // Kiểm tra xem đã có review cho khóa học này chưa
        $existingReview = Review::where('user_id', $user->id)
            ->where('course_id', $enrollment->course_id)
            ->first();

        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this course'], 400);
        }

   // Kiểm tra từ ngữ phản cảm nếu có comment
        if ($request->comment) {
            $response = Http::asForm()->post('https://neutrinoapi.net/bad-word-filter', [
                'user-id' => 'phamminhtri26102003',
                'api-key' => '2pHRUxWhHr0hVLDVGR8BPmF7lTGNPPSTeFTiVPsrHgIRnDXM',
                'content' => $request->comment,
                'censor-character' => '*' // Optional: dùng để thay từ vi phạm nếu cần
            ]);
            Log::info('Bad word filter response', ['response' => $response->body()]); 
            if ($response->successful()) {
                $result = $response->json();
                if ($result['is-bad']) {
                    return response()->json([
                        'message' => 'The comment contain inapproriate content'
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => "Unable to check content at this time. Please try again later."
                ], 500);
            }
        }
        $comment = strtolower($request->comment);
        $badWords = json_decode(file_get_contents(storage_path('app/vi_badwords.json')), true);

        foreach ($badWords as $category => $words) {
            foreach ($words as $word) {
                if (stripos($comment, $word) !== false) {
                    return response()->json([
                        'message' => 'Your comment contains inappropriate language: '
                    ], 422);
                }
            }
        }

        $review = Review::create([
            'user_id' => $user->id,
            'course_id' => $enrollment->course_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'feedback_type' => $request->feedback_type,
        ]);

        return response()->json(['message' => 'Review submitted successfully', 'data' => $review], 201);
    }

    /**
     * Enroll in a free course.
     */
    public function enrollFreeCourse(Request $request, $course_id): JsonResponse
    {
        $user = Auth::user();
         // Tìm khóa học
        $course = Course::withCount('lessons')->find($course_id);

        if (!$course) {
            return response()->json(['message' => 'Course dont have lesson'], 404);
        }
        // Check if course exists and is free
        $course = Course::find($course_id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
         // Kiểm tra nếu user là instructor của course này thì không cho đăng ký
     if ($user->instructor && $course->instructor_id == $user->instructor->id) {
            return response()->json([
                'message' => 'Instructors cannot enroll in their own courses.'
            ], 403);
        }
        // Check if user is a student but not yet registered in students table
        if ($user->role == 'instructor' && !$user->student) {
            return response()->json([
                'message' => 'Student profile not found. Please complete your profile.',
                'redirect' => true,
                'url' => 'localhost:4200/student/profile/create' // Frontend URL for student profile creation
            ], 200);
        }
        // Check if the course is free (price == 0)
        if ($course->price > 0) {
            return response()->json(['message' => 'This course requires payment. Please use the payment process.'], 400);
        }

        $existingEnrollment = Enrollment::where('user_id', $user->id)
        ->where('course_id', $course_id)
        ->where(function ($query) {
        $query->whereNull('expires_at')
              ->orWhere('expires_at', '>=', now());
        })
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

         // ✅ Tạo lesson_progress cho tất cả bài học (mọi version)
        $allLessons = Lesson::where('course_id', $course_id)->get();

        foreach ($allLessons as $lesson) {
            LessonProgress::create([
                'user_id' => $user->id,
                'lesson_id' => $lesson->id,
                'status' => 'not_started',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        return response()->json([
            'message' => 'Successfully enrolled in free course',
            'data' => $enrollment
        ], 201);
    }
   public function storePaid(StorePaidEnrollmentRequest $request, $course_id): JsonResponse
    {
        $user = Auth::user();            
        // Check if course exists and is paid
        $course = Course::find($course_id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        // Kiểm tra nếu user là instructor của course này thì không cho đăng ký
       if ($user->instructor && $course->instructor_id == $user->instructor->id) {
            return response()->json([
                'message' => 'Instructors cannot enroll in their own courses.'
            ], 403);
        }
              // Check if user is a student but not yet registered in students table
        if (!$user->student) {
            return response()->json([
                'message' => 'Student profile not found. Please complete your profile.',
            ], 200);
        }
        if ($course->price <= 0) {
            return response()->json(['message' => 'This course is free. Use the free enrollment endpoint.'], 400);
        }

        // Check if already enrolled
        $existingEnrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course_id)
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
     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments = Enrollment::onlyTrashed()->paginate(10);
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment = Enrollment::onlyTrashed()->findOrFail($id);
        $enrollment->restore();
        return response()->json(['message' => 'Enrollment restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        try{
        $enrollment = Enrollment::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        }catch(\Exception $e){
            return response()->json(['message' => 'Error deleting enrollment: ' . $e->getMessage()], 500);
        }
        return response()->json(['message' => 'Enrollment permanently deleted'], 200);
    }

    function search(Request $request) {
    return Enrollment::query()
        ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')))
        ->when($request->filled('course_id'), fn($q) => $q->where('course_id', $request->input('course_id')))
        ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
        ->paginate(10);
}
}