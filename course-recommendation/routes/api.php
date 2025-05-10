<?php
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizResultController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ForumPostController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZaloPayController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
// Route::get('/courses', [RecommendationController::class, 'getCourses']);
Route::post('/recommend', [RecommendationController::class, 'recommend']);
Route::post('/rate', [RecommendationController::class, 'rate']);
Route::post('/users', [RecommendationController::class, 'createUser']);

// API routes không yêu cầu CSRF token
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');

Route::middleware(['auth:api', 'admin'])->group(function () {
    // Course routes
    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::get('/courses/{id}/admin-stats', [CourseController::class, 'adminStats']);
    // Admin duyệt khóa học
    Route::get('/courses/pending', [CourseController::class, 'getPendingCourses']);
    Route::put('/courses/{id}/approve', [CourseController::class, 'approveCourse']);
    Route::put('/courses/{id}/reject', [CourseController::class, 'rejectCourse']);
});

Route::middleware(['auth:api', 'student'])->group(function () {
    // Student routes
    // Add student-specific routes here
});

Route::middleware(['auth:api', 'instructor'])->group(function () {
    Route::get('/instructor/courses', [CourseController::class, 'indexCourseInstructor']);
    Route::post('/instructor/courses', [CourseController::class, 'storeCourseInstructor']);
    Route::put('instructor/courses/{id}', [CourseController::class, 'updateCourseInstructor']);
    Route::delete('instructor/courses/{id}', [CourseController::class, 'destroyCourseInstructor']);
    // Instructor routes
    // Add instructor-specific routes here
});
// Admin middleware (example)
Route::middleware("student")->group(function () {
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    // Enrollment Routes
Route::get('/enrollments', [EnrollmentController::class, 'index']);
Route::get('/enrollments/{id}', [EnrollmentController::class, 'show']);
Route::post('/enrollments', [EnrollmentController::class, 'store']);
Route::put('/enrollments/{id}', [EnrollmentController::class, 'update']);
Route::delete('/enrollments/{id}', [EnrollmentController::class, 'destroy']);

// Payment Routes
Route::get('/payments', [PaymentController::class, 'index']);
Route::get('/payments/{id}', [PaymentController::class, 'show']);
Route::post('/payments', [PaymentController::class, 'store']);
Route::put('/payments/{id}', [PaymentController::class, 'update']);
Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);
    // Additional admin routes
});

// Quiz Routes
Route::get('/quizzes', [QuizController::class, 'index']);
Route::get('/quizzes/{id}', [QuizController::class, 'show']);
Route::post('/quizzes', [QuizController::class, 'store']);
Route::put('/quizzes/{id}', [QuizController::class, 'update']);
Route::delete('/quizzes/{id}', [QuizController::class, 'destroy']);

// Quiz Result Routes
Route::get('/quiz-results', [QuizResultController::class, 'index']);
Route::get('/quiz-results/{id}', [QuizResultController::class, 'show']);
Route::post('/quiz-results', [QuizResultController::class, 'store']);
Route::put('/quiz-results/{id}', [QuizResultController::class, 'update']);
Route::delete('/quiz-results/{id}', [QuizResultController::class, 'destroy']);

// Certificate Routes
Route::get('/certificates', [CertificateController::class, 'index']);
Route::get('/certificates/{id}', [CertificateController::class, 'show']);
Route::post('/certificates', [CertificateController::class, 'store']);
Route::put('/certificates/{id}', [CertificateController::class, 'update']);
Route::delete('/certificates/{id}', [CertificateController::class, 'destroy']);

// Forum Post Routes
Route::get('/forum-posts', [ForumPostController::class, 'index']);
Route::get('/forum-posts/{id}', [ForumPostController::class, 'show']);
Route::post('/forum-posts', [ForumPostController::class, 'store']);
Route::put('/forum-posts/{id}', [ForumPostController::class, 'update']);
Route::delete('/forum-posts/{id}', [ForumPostController::class, 'destroy']);

// Review Routes
Route::get('/reviews', [ReviewController::class, 'index']);
Route::get('/reviews/{id}', [ReviewController::class, 'show']);
Route::post('/reviews', [ReviewController::class, 'store']);
Route::put('/reviews/{id}', [ReviewController::class, 'update']);
Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);

Route::post('/payments/callback', [PaymentController::class, 'handleZaloPayCallback']);