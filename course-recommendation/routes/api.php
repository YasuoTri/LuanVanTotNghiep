<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizResultController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ForumPostController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\QuestionChoiceController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserAnswerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZaloPayController;
use App\Services\PaymentGateways\PaymentGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Existing General Routes
Route::post('/recommend', [RecommendationController::class, 'recommend']);
Route::post('/rate', [RecommendationController::class, 'rate']);
Route::post('/users', [RecommendationController::class, 'createUser']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
Route::get('/courses/search', [SearchController::class, 'search'])->name('courses.search');
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{id}', [CourseController::class, 'show']);
// Student Routes
Route::middleware(['auth:api', 'student'])->group(function () {
    Route::get('/enrollments/student', [EnrollmentController::class, 'getStudentEnrollments']);
    Route::put('/enrollments/{id}', [EnrollmentController::class, 'update']);
    Route::delete('/enrollments/{id}', [EnrollmentController::class, 'destroy']);
    Route::get('/enrollments/{id}/status', [EnrollmentController::class, 'checkEnrollmentStatus']);
    Route::post('/enrollments/{id}/complete', [EnrollmentController::class, 'completeEnrollment']);
    Route::get('/enrollments/{id}/progress', [EnrollmentController::class, 'getLearningProgress']);
    Route::post('/enrollments/{id}/review', [EnrollmentController::class, 'submitReview']);
    //free course enrollment
    Route::post('/courses/{course_id}/enroll-free', [EnrollmentController::class, 'enrollFreeCourse'])->name('enrollments.enrollFree');
    Route::post('/courses/{course_id}/enroll-paid', [EnrollmentController::class, 'storePaid'])
        ->name('enrollments.enrollPaid');
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    // Check Payment Status
    Route::get('/payments/{payment_id}/status', [PaymentController::class, 'checkPaymentStatus'])->name('payments.checkStatus');

    // View Lesson Content (Videos, Materials)
    Route::get('/courses/{course_id}/lessons/{lesson_id}', [LessonController::class, 'showForStudent'])->name('lessons.showForStudent');
    Route::get('/courses/{enroll_id}/lessons', [LessonController::class, 'getCourseLessons'])->name('lessons.getCourseLessons');
    // Quiz Interactions
    Route::get('/courses/{course_id}/quizzes', [QuizController::class, 'indexForStudent'])->name('quizzes.indexForStudent');
    Route::get('/quizzes/{quiz_id}', [QuizController::class, 'showForStudent'])->name('quizzes.showForStudent');
    Route::post('/quizzes/{quiz_id}/retry', [QuizController::class, 'retryQuiz'])->name('quizzes.retry');
    Route::get('/courses/{course_id}/quiz-progress', [QuizController::class, 'quizProgressForCourse'])->name('quizzes.progressForCourse');
    Route::post('/quizzes/{quiz_id}/submit', [QuizController::class, 'submitQuiz'])->name('quizzes.submit');

    // Track Learning Progress
    Route::get('/lesson-progress', [LessonProgressController::class, 'indexForStudent'])->name('lessonProgress.indexForStudent');
    Route::get('/lesson-progress/{id}', [LessonProgressController::class, 'showForStudent'])->name('lessonProgress.showForStudent');

    // Ask Questions, Discuss (Forum Posts)
    Route::get('/courses/{course_id}/forum-posts', [ForumPostController::class, 'indexForStudent'])->name('forumPosts.indexForStudent');
    Route::post('/courses/{course_id}/forum-posts', [ForumPostController::class, 'storeForStudent'])->name('forumPosts.storeForStudent');

    Route::get('/quizzes/{quiz_id}/questions', [QuizController::class, 'getQuestionsForStudent'])->name('quizzes.getQuestionsForStudent');
    Route::post('/quizzes/{quiz_id}/draft', [QuizController::class, 'saveDraftAnswers'])->name('quizzes.saveDraftAnswers');
    Route::post('/user-answers', [UserAnswerController::class, 'store'])->name('user-answers.store');
    Route::get('/user-answers/{answer}', [UserAnswerController::class, 'show'])->name('user-answers.show');
});
Route::get('/enrollments', [EnrollmentController::class, 'index']);
Route::get('/enrollments/{id}', [EnrollmentController::class, 'show']);
// Instructor Routes
Route::middleware(['auth:api', 'instructor'])->group(function () {
  
    Route::get('/instructor/courses', [CourseController::class, 'indexCourseInstructor']);
    Route::post('/instructor/courses', [CourseController::class, 'storeCourseInstructor']);
    Route::put('instructor/courses/{id}', [CourseController::class, 'updateCourseInstructor']);
    Route::delete('instructor/courses/{id}', [CourseController::class, 'destroyCourseInstructor']);

    // New Routes for Instructors
    // View Lesson Content (Preview Own Courses)
    Route::get('/instructor/courses/{course_id}/lessons/{lesson_id}', [LessonController::class, 'showForInstructor'])->name('lessons.showForInstructor');

   
    // Quiz Results and Statistics
    Route::get('/instructor/quizzes/{quiz_id}/results', [QuizController::class, 'studentQuizResults'])->name('quizzes.studentResults');
    // Update Quiz Settings
    Route::put('/instructor/quizzes/{quiz_id}/settings', [QuizController::class, 'updateQuizSettings'])->name('quizzes.updateSettings');
    // Preview Quiz
    Route::get('/instructor/quizzes/{quiz_id}/preview', [QuizController::class, 'previewQuiz'])->name('quizzes.preview');
     // Take Quizzes (Test Own Quizzes)
    Route::post('/instructor/quizzes/{quiz_id}/test', [QuizController::class, 'submitQuiz'])->name('quizzes.testForInstructor');

    // Track Student Progress in Their Courses
    Route::get('/instructor/courses/{course_id}/student-progress', [LessonProgressController::class, 'indexForInstructor'])->name('lessonProgress.indexForInstructor');

    // Manage Quizzes
    Route::get('/instructor/quizzes', [QuizController::class, 'indexForInstructor'])->name('quizzes.indexForInstructor');
    Route::get('/instructor/quizzes/{id}', [QuizController::class, 'showForInstructor'])->name('quizzes.showForInstructor');
    Route::post('/instructor/quizzes', [QuizController::class, 'storeForInstructor'])->name('quizzes.storeForInstructor');
    Route::put('/instructor/quizzes/{id}', [QuizController::class, 'updateForInstructor'])->name('quizzes.updateForInstructor');
    Route::delete('/instructor/quizzes/{id}', [QuizController::class, 'destroyForInstructor'])->name('quizzes.destroyForInstructor');

    // Manage Lessons
    Route::get('/instructor/courses/{course_id}/lessons', [LessonController::class, 'indexForInstructor'])->name('lessons.indexForInstructor');
    Route::get('/instructor/courses/{course_id}/lessons/{lesson_id}', [LessonController::class, 'showForInstructor'])->name('lessons.showForInstructor');
    Route::post('/instructor/courses/{course_id}/lessons', [LessonController::class, 'storeForInstructor'])->name('lessons.storeForInstructor');
    Route::put('/instructor/courses/{course_id}/lessons/{lesson_id}', [LessonController::class, 'updateForInstructor'])->name('lessons.updateForInstructor');
    Route::delete('/instructor/courses/{course_id}/lessons/{lesson_id}', [LessonController::class, 'destroyForInstructor'])->name('lessons.destroyForInstructor');

    // Participate in Forum Discussions
    Route::get('/instructor/courses/{course_id}/forum-posts', [ForumPostController::class, 'indexForInstructor'])->name('forumPosts.indexForInstructor');
    Route::post('/instructor/courses/{course_id}/forum-posts', [ForumPostController::class, 'storeForInstructor'])->name('forumPosts.storeForInstructor');
    //Analytics
    Route::get('/instructor/courses/{course_id}/analytics', [AnalyticsController::class, 'courseAnalytics'])
        ->name('analytics.course');

    // Routes cho Question
    Route::apiResource('questions', QuestionController::class);

    // Routes cho QuestionChoice
    Route::apiResource('question-choices', QuestionChoiceController::class);
    Route::post('/user-answers/{user_answer_id}/grade', [QuizController::class, 'gradeOpenEndedAnswer'])->name('user-answers.grade');
});

// Admin Routes
Route::middleware(['auth:api', 'admin'])->group(function () {
    // Existing Routes
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::get('/courses/{id}/admin-stats', [CourseController::class, 'adminStats']);
    Route::get('/courses/pending', [CourseController::class, 'getPendingCourses']);
    Route::put('/courses/{id}/approve', [CourseController::class, 'approveCourse']);
    Route::put('/courses/{id}/reject', [CourseController::class, 'rejectCourse']);
    Route::get('/admin/quizzes', [QuizController::class, 'index']);
    Route::get('/admin/quizzes/{id}', [QuizController::class, 'show']);
    Route::post('/admin/quizzes', [QuizController::class, 'store']);
    Route::put('/admin/quizzes/{id}', [QuizController::class, 'update']);
    Route::delete('/admin/quizzes/{id}', [QuizController::class, 'destroy']);
    Route::get('/admin/quiz-results', [QuizResultController::class, 'index']);
    Route::get('/admin/quiz-results/{id}', [QuizResultController::class, 'show']);
    Route::post('/admin/quiz-results', [QuizResultController::class, 'store']);
    Route::put('/admin/quiz-results/{id}', [QuizResultController::class, 'update']);
    Route::delete('/admin/quiz-results/{id}', [QuizResultController::class, 'destroy']);
    Route::get('/admin/certificates', [CertificateController::class, 'index']);
    Route::get('/admin/certificates/{id}', [CertificateController::class, 'show']);
    Route::post('/admin/certificates', [CertificateController::class, 'store']);
    Route::put('/admin/certificates/{id}', [CertificateController::class, 'update']);
    Route::delete('/admin/certificates/{id}', [CertificateController::class, 'destroy']);
    Route::get('/admin/forum-posts', [ForumPostController::class, 'index']);
    Route::get('/admin/forum-posts/{id}', [ForumPostController::class, 'show']);
    Route::post('/admin/forum-posts', [ForumPostController::class, 'store']);
    Route::put('/admin/forum-posts/{id}', [ForumPostController::class, 'update']);
    Route::delete('/admin/forum-posts/{id}', [ForumPostController::class, 'destroy']);
    Route::get('/admin/reviews', [ReviewController::class, 'index']);
    Route::get('/admin/reviews/{id}', [ReviewController::class, 'show']);
    Route::post('/admin/reviews', [ReviewController::class, 'store']);
    Route::put('/admin/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/admin/reviews/{id}', [ReviewController::class, 'destroy']);
    Route::get('/admin/lessons', [LessonController::class, 'index']);
    Route::get('/admin/lessons/{id}', [LessonController::class, 'show']);
    Route::post('/admin/lessons', [LessonController::class, 'store']);
    Route::put('/admin/lessons/{id}', [LessonController::class, 'update']);
    Route::delete('/admin/lessons/{id}', [LessonController::class, 'destroy']);
    Route::get('/admin/lessonProgress', [LessonProgressController::class, 'index']);
    Route::get('/admin/lessonProgress/{id}', [LessonProgressController::class, 'show']);
    Route::post('/admin/lessonProgress', [LessonProgressController::class, 'store']);
    Route::put('/admin/lessonProgress/{id}', [LessonProgressController::class, 'update']);
    Route::delete('/admin/lessonProgress/{id}', [LessonProgressController::class, 'destroy']);
     Route::post('/admin/enrollments', [EnrollmentController::class, 'store']);
    // Manage Payments
    Route::get('/admin/payments', [PaymentController::class, 'indexForAdmin'])->name('payments.indexForAdmin');
    Route::get('/admin/payments/{id}', [PaymentController::class, 'showForAdmin'])->name('payments.showForAdmin');
    
    Route::post('/admin/payments', [PaymentController::class, 'storeAdmin']);
    Route::put('/admin/payments/{id}', [PaymentController::class, 'update']);
    Route::delete('/admin/payments/{id}', [PaymentController::class, 'destroy']);
    // Manage Violating Content (Flag/Remove Forum Posts)
    Route::put('/admin/forum-posts/{id}/flag', [ForumPostController::class, 'flag'])->name('forumPosts.flag');
    Route::delete('/admin/forum-posts/{id}/remove', [ForumPostController::class, 'remove'])->name('forumPosts.remove');

    // Manage Users
    Route::get('/admin/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/admin/users/{id}', [UserController::class, 'show'])->name('users.show');
    Route::put('/admin/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::post('/admin/users', [UserController::class, 'store'])->name('users.store');
    Route::delete('/admin/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');

    //Analytics
    Route::get('/admin/analytics/courses', [AnalyticsController::class, 'adminCourseAnalytics'])
        ->name('analytics.courses');
    Route::get('/admin/analytics/users', [AnalyticsController::class, 'adminUserAnalytics'])
        ->name('analytics.users');

    Route::apiResource('/admin/questions', QuestionController::class);
    Route::apiResource('/admin/question-choices', QuestionChoiceController::class);
    });

// Payment Callback (Existing)
Route::post('/vnpay_payment', [PaymentGateway::class, 'createOrder']);
Route::post('/payments/callback', [PaymentController::class, 'handleZaloPayCallback']);
Route::post('/payments/vnpay/callback', [PaymentController::class, 'handleVNPayCallback']);
Route::get('/vnpay/ipn', [PaymentController::class, 'handleVNPayIPN'])->name('vnpay.ipn');

