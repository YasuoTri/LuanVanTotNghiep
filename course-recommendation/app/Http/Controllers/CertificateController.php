<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certificate\StoreCertificateRequest;
use App\Http\Requests\Certificate\UpdateCertificateRequest;
use App\Mail\CertificateIssuedMail;
use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Models\CertificateRule;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use App\Services\CloudinaryService;
use Cloudinary\Cloudinary as CloudinaryCloudinary;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CertificateController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }
    public function index(): JsonResponse
    {
        $certificates = Certificate::paginate(10);
        return response()->json(['data' => $certificates]);
    }

    public function show($id): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);
        return response()->json(['data' => $certificate]);
    }

     public function store(StoreCertificateRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Upload certificate PDF to Cloudinary
            if ($request->hasFile('certificate_file')) {
                $data['download_url'] = $this->cloudinaryService->upload(
                    $request->file('certificate_file'),
                    'certificates',
                );
            }

            $certificate = Certificate::create($data);
            return response()->json(['message' => 'Certificate created successfully', 'data' => $certificate], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while creating the certificate.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
   public function update(UpdateCertificateRequest $request, $id): JsonResponse
    {
        try {
            $certificate = Certificate::findOrFail($id);
            $data = $request->validated();

            // Update certificate PDF if provided
            if ($request->hasFile('certificate_file')) {
                // Delete old file from Cloudinary if exists
                if ($certificate->download_url) {
                    $this->cloudinaryService->deleteByUrl($certificate->download_url);
                }

                // Upload new file to Cloudinary
                $data['download_url'] = $this->cloudinaryService->upload(
                    $request->file('certificate_file'),
                    'certificates',
                    // 'raw',
                    // 'cert_' . ($data['certificate_code'] ?? $certificate->certificate_code)
                );
            }

            $certificate->update($data);
            return response()->json(['message' => 'Certificate updated successfully', 'data' => $certificate], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while updating the certificate.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
public function destroy($id): JsonResponse
    {
        try {
            $certificate = Certificate::findOrFail($id);

            // Delete certificate PDF from Cloudinary if exists
            if ($certificate->download_url) {
                $this->cloudinaryService->deleteByUrl($certificate->download_url);
            }

            $certificate->delete();
            return response()->json(['message' => 'Certificate deleted successfully'], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while deleting the certificate.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments = Certificate::onlyTrashed()->paginate(10);
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment = Certificate::onlyTrashed()->findOrFail($id);
        $enrollment->restore();
        return response()->json(['message' => 'Certificate restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        $enrollment = Certificate::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        return response()->json(['message' => 'Certificate permanently deleted'], 200);
    }

      public function issue(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $courseId = $request->input('course_id');
        $userId = Auth::user()->id; // dùng auth middleware

        return $this->issueCertificate($courseId, $userId);
    }

// public function issueCertificate($courseId, $userId)
// {
//     // 1. Lấy tất cả lesson gốc trong course
//     $lessonGroups = Lesson::where('course_id', $courseId)
//         ->selectRaw('COALESCE(origin_id, id) as origin_id, MAX(version) as latest_version')
//         ->groupBy('origin_id')
//         ->get();

//     $missingLessons = [];

//     foreach ($lessonGroups as $group) {
//         // Lấy lesson version mới nhất
//         $latestLesson = Lesson::where(function ($q) use ($group) {
//                 $q->where('origin_id', $group->origin_id)
//                   ->orWhere('id', $group->origin_id);
//             })
//             ->where('version', $group->latest_version)
//             ->first();

//         $isCompleted = LessonProgress::where('user_id', $userId)
//             ->where('lesson_id', $latestLesson->id)
//             ->where('status', 'completed')
//             ->exists();

//         if (!$isCompleted) {
//             $missingLessons[] = [
//                 'lesson_id' => $latestLesson->id,
//                 'lesson_title' => $latestLesson->title,
//                 'version' => $latestLesson->version,
//                 'message' => 'Bạn chưa hoàn thành bài học này.'
//             ];
//         }
//     }

//     // 2. Lấy tất cả quiz gốc trong course
//     $lessonIds = Lesson::where('course_id', $courseId)->pluck('id');

//     $quizGroups = Quiz::whereIn('lesson_id', $lessonIds)
//         ->selectRaw('COALESCE(origin_id, id) as origin_id, MAX(version) as latest_version')
//         ->groupBy('origin_id')
//         ->get();

//     $missingQuizzes = [];

//     foreach ($quizGroups as $group) {
//         $latestQuiz = Quiz::where(function ($q) use ($group) {
//                 $q->where('origin_id', $group->origin_id)
//                   ->orWhere('id', $group->origin_id);
//             })
//             ->where('version', $group->latest_version)
//             ->first();

//         $hasPassed = QuizResult::where('quiz_id', $latestQuiz->id)
//             ->where('user_id', $userId)
//             ->where('score', '>=', 60)
//             ->exists();

//         if (!$hasPassed) {
//             $missingQuizzes[] = [
//                 'quiz_id' => $latestQuiz->id,
//                 'quiz_title' => $latestQuiz->title,
//                 'version' => $latestQuiz->version,
//                 'message' => 'Bạn cần làm bài kiểm tra này để đủ điều kiện.'
//             ];
//         }
//     }

//     // 3. Trả về nếu còn thiếu
//     if (!empty($missingLessons) || !empty($missingQuizzes)) {
//         return response()->json([
//             'eligible' => false,
//             'missing_lessons' => $missingLessons,
//             'missing_quizzes' => $missingQuizzes,
//             'message' => 'Bạn chưa hoàn thành đủ điều kiện để được cấp chứng chỉ.'
//         ], 400);
//     }

//     // 4. Kiểm tra enrollment
//     $enrollment = Enrollment::where('user_id', $userId)
//         ->where('course_id', $courseId)
//         ->first();

//     if (!$enrollment) {
//         return response()->json([
//             'eligible' => false,
//             'message' => 'Bạn chưa đăng ký khóa học.'
//         ], 400);
//     }

//     $existingCertificate = Certificate::where('user_id', $userId)
//         ->where('course_id', $courseId)
//         ->first();

//     if ($existingCertificate) {
//         return response()->json([
//             'eligible' => true,
//             'message' => 'Bạn đã có chứng chỉ cho khóa học này.',
//             'certificate_url' => $existingCertificate->download_url
//         ]);
//     }

//     // 5. Tạo chứng chỉ nếu chưa có
//     $certificate = Certificate::firstOrCreate(
//         [
//             'user_id' => $userId,
//             'course_id' => $courseId,
//         ],
//         [
//             'enrollment_id' => $enrollment->id,
//             'instructor_id' => Course::find($courseId)->instructor_id,
//             'certificate_code' => strtoupper(Str::random(12)),
//             'download_url' => url("/certificates/{$userId}/{$courseId}/download")
//         ]
//     );
//     Mail::to($certificate->user->email)
//      ->send(new \App\Mail\CertificateIssuedMail($certificate));
//     return response()->json([
//         'eligible' => true,
//         'message' => 'Đã cấp chứng chỉ thành công.',
//         'certificate_url' => $certificate->download_url
//     ]);
// }

    public function issueCertificate($courseId, $userId)
{
    $rule = CertificateRule::where('course_id', $courseId)->first();
    if ($rule) {
        $lessonPercentRequired = $rule->lesson_completion_percent;
        $lessonVersionRule = $rule->lesson_version_rule;
        $quizMinScore = $rule->quiz_min_score;
        $quizVersionRule = $rule->quiz_version_rule;
    } else {
        $lessonPercentRequired = 100;
        $lessonVersionRule = 'latest';
        $quizMinScore = 70;
        $quizVersionRule = 'latest';
    }
    // 1. Lấy tất cả lesson gốc trong course
    $lessonGroups = Lesson::where('course_id', $courseId)
    ->selectRaw('DISTINCT COALESCE(origin_id, id) as origin_id')
    ->pluck('origin_id');


    $missingLessons = [];
    $totalLessons = count($lessonGroups);
    $completedLessons = 0;

    foreach ($lessonGroups as $originId) {
    $lessonQuery = Lesson::where(function ($q) use ($originId) {
        $q->where('origin_id', $originId)
          ->orWhere('id', $originId);
    });

    if ($lessonVersionRule === 'latest') {
        $lessonQuery->orderByDesc('version')->limit(1);
    }

    $lessons = $lessonQuery->get();

    $isCompleted = LessonProgress::where('user_id', $userId)
        ->whereIn('lesson_id', $lessons->pluck('id'))
        ->where('status', 'completed')
        ->exists();

    if ($isCompleted) {
        $completedLessons++;
    } else {
        $missingLessons[] = [
            'lesson_group' => $originId,
            'message' => 'Learner has not completed the lesson group.'
        ];
    }
}
    $lessonPercent = $totalLessons > 0 ? round($completedLessons * 100 / $totalLessons, 2) : 0;

    if ($lessonPercent < $lessonPercentRequired) {
        return response()->json([
            'eligible' => false,
            'missing_lessons' => $missingLessons,
            'missing_quizzes' => [],
            'message' => "You havent completed at least {$lessonPercentRequired}% lesson."
        ], 400);
    }

    // 2. Lấy tất cả quiz gốc trong course
    $lessonIds = Lesson::where('course_id', $courseId)->pluck('id');

    $quizGroups = Quiz::whereIn('lesson_id', $lessonIds)
        ->selectRaw('DISTINCT COALESCE(origin_id, id) as origin_id')
        ->pluck('origin_id');

    $missingQuizzes = [];

    foreach ($quizGroups as $originId) {
    $quizQuery = Quiz::where(function ($q) use ($originId) {
        $q->where('origin_id', $originId)
          ->orWhere('id', $originId);
    });

    if ($quizVersionRule === 'latest') {
        $quizQuery->orderByDesc('version')->limit(1);
    }

    $quizzes = $quizQuery->get();

    $hasPassed = QuizResult::whereIn('quiz_id', $quizzes->pluck('id'))
        ->where('user_id', $userId)
        ->where('score', '>=', $quizMinScore)
        ->exists();

        if (!$hasPassed) {
            $missingQuizzes[] = [
                'quiz_group' => $originId,
                'message' => "Learner havent completed the quiz with at least {$quizMinScore}."
            ];
        }
    }


    if (!empty($missingQuizzes)) {
        return response()->json([
            'eligible' => false,
            'missing_lessons' => [],
            'missing_quizzes' => $missingQuizzes,
            'message' => 'He has not completed the condition to issue the certificate.'
        ], 400);
    }

    // 3. Trả về nếu còn thiếu
    if (!empty($missingLessons) || !empty($missingQuizzes)) {
        return response()->json([
            'eligible' => false,
            'missing_lessons' => $missingLessons,
            'missing_quizzes' => $missingQuizzes,
            'message' => 'He has not completed the condition to issue the certificate.'
        ], 400);
    }

    // 4. Kiểm tra enrollment
    $enrollment = Enrollment::where('user_id', $userId)
        ->where('course_id', $courseId)
        ->first();

    if (!$enrollment) {
        return response()->json([
            'eligible' => false,
            'message' => 'You have not registered for this course.'
        ], 400);
    }

    $existingCertificate = Certificate::where('enrollment_id', function ($query) use ($userId, $courseId) {
        $query->select('id')
            ->from('enrollments')
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();
    })->first();

    if ($existingCertificate) {
        return response()->json([
            'eligible' => true,
            'message' => 'You already have a certificate for this course.',
            'certificate_url' => $existingCertificate->download_url
        ]);
    }
    $course = Course::findOrFail($courseId);
    $user = User::findOrFail($userId);
    $instructor = $course->instructor;
    $instructor_fullname=User::findOrFail($course->instructor->user_id);
    $pdf = Pdf::loadView('certificate', [
    'student_name' => $user->fullname,
    'course_name' => $course->course_name,
    'instructor_name' => $instructor_fullname ?? 'N/A',
    'instructor' => $instructor ?? 'N/A',
    'issued_at' => now()->format('d/m/Y'),
    'signature_url' => $instructor->signature_url ?? public_path('signatures/default.png')
]);

    // 2. Lưu file tạm
     $pdfPath = storage_path("app/certificates/certificate_{$userId}_{$courseId}.pdf");

    $uploadedFile = new UploadedFile(
        $pdfPath,
        basename($pdfPath),
        'application/pdf',
        null,
        true
    );

    $pdf->save($pdfPath);
    $cloudinaryService=new CloudinaryCloudinary();
    // 3. Upload lên Cloudinary
    $cloudinaryService = new CloudinaryService($cloudinaryService);
    $uploadResult = $cloudinaryService->upload($uploadedFile, 'certificates');

    // 5. Tạo chứng chỉ nếu chưa có
    // $certificate = Certificate::firstOrCreate(
    //     [
    //         'user_id' => $userId,
    //         'course_id' => $courseId,
    //     ],
    //     [
    //         'enrollment_id' => $enrollment->id,
    //         'instructor_id' => Course::find($courseId)->instructor_id,
    //         'certificate_code' => strtoupper(Str::random(12)),
    //         'download_url' => $uploadResult['secure_url'] ?? null,
    //     ]
    // );
    $enrollment = Enrollment::where('user_id', $userId)->where('course_id', $courseId)->firstOrFail();
    $certificate = Certificate::firstOrCreate(
        [
            'enrollment_id' => $enrollment->id,
        ],
        [
            // 'instructor_id' => Course::find($courseId)->instructor_id,
            'certificate_code' => strtoupper(Str::random(12)),
            'download_url' => $uploadResult['secure_url'] ?? null,
        ]
    );
    Mail::to($certificate->user->email)
     ->send(new CertificateIssuedMail($certificate));
     // 6. Xoá file tạm
    if (file_exists($pdfPath)) {
        unlink($pdfPath);
    }
    return response()->json([
        'eligible' => true,
        'message' => 'Certificate issued successfully.',
        'certificate_url' => $certificate->download_url
    ]);
}
public function instructorIssue(Request $request)
{
    $request->validate([
        'course_id' => 'required|exists:courses,id',
        'user_id'   => 'required|exists:users,id',
    ]);

    $courseId = $request->input('course_id');
    $userId   = $request->input('user_id');

    // instructor vẫn gọi lại logic giống learner
    return $this->issueCertificate($courseId, $userId);
}

 public function getCourseProgress(Request $request, int $courseId): JsonResponse
    {
        try {
            // Kiểm tra xem khóa học có tồn tại không
            $course = Course::findOrFail($courseId);

            // Lấy danh sách các user đã đăng ký khóa học
            $enrollments = Enrollment::where('course_id', $courseId)
                ->with(['user'])
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'message' => 'Dont have any users enrolled in this course',
                    'data' => []
                ], 200);
            }

            $visibleLessonIds = Lesson::where('course_id', $courseId)
                ->where('is_visible', true)
                ->pluck('id');

            $totalLessons = $visibleLessonIds->count();

            // 4. Lấy các quiz visible (liên quan tới các visible lessons)
            $visibleQuizIds = Quiz::whereIn('lesson_id', $visibleLessonIds)
                ->where('is_visible', true)
                ->pluck('id');

            $quizzes = Quiz::whereIn('id', $visibleQuizIds)
                ->get()
                ->keyBy('id');

            // Lấy quy tắc cấp chứng chỉ
            $certificateRule = CertificateRule::where('course_id', $courseId)->first();

            $results = [];
            foreach ($enrollments as $enrollment) {
                $user = $enrollment->user;

                // Tính phần trăm hoàn thành lesson
                $completedLessons = LessonProgress::where('user_id', $user->id)
                    ->whereIn('lesson_id', Lesson::where('course_id', $courseId)->pluck('id'))
                    ->where('status', 'completed')
                    ->count();

                $lessonCompletionPercent = $totalLessons > 0
                    ? round(($completedLessons / $totalLessons) * 100, 2)
                    : 0;

                // Lấy kết quả quiz
                $quizResults = QuizResult::where('user_id', $user->id)
                    ->whereIn('quiz_id', $quizzes->pluck('id'))
                    ->get()
                    ->map(function ($quizResult) use ($quizzes, $certificateRule) {
                        $quiz = $quizzes[$quizResult->quiz_id];
                        $isPassed = $certificateRule
                            ? $quizResult->score >= $certificateRule->quiz_min_score
                            : false;

                        return [
                            'quiz_id' => $quizResult->quiz_id,
                            'quiz_title' => $quiz->title,
                            'score' => $quizResult->score,
                            'is_passed' => $isPassed,
                            'completed_at' => $quizResult->completed_at
                                ? $quizResult->completed_at->toISOString()
                                : null,
                        ];
                    });

                // Kiểm tra điều kiện cấp chứng chỉ
                $isEligibleForCertificate = false;
                if ($certificateRule) {
                    $allQuizzesPassed = $quizzes->isEmpty() || $quizResults->every(function ($result) {
                        return $result['is_passed'];
                    });

                    $meetsLessonRequirement = $lessonCompletionPercent >= $certificateRule->lesson_completion_percent;

                    $isEligibleForCertificate = $meetsLessonRequirement && $allQuizzesPassed;
                }

                $results[] = [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'lesson_completion_percent' => $lessonCompletionPercent,
                    'total_lessons' => $totalLessons,
                    'completed_lessons' => $completedLessons,
                    'quizzes' => $quizResults->toArray(),
                    'is_eligible_for_certificate' => $isEligibleForCertificate,
                ];
            }

            return response()->json([
                'message' => 'Course progress retrieved successfully',
                'data' => [
                    'course_id' => $course->id,
                    'course_name' => $course->course_name,
                    'users' => $results,
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Course not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get course progress error:', [
                'course_id' => $courseId,
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'An error occurred while retrieving course progress',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}