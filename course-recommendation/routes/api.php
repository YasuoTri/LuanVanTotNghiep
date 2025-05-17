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
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\QuestionChoiceController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StudentController;
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
Route::get('/courses/{slug}', [CourseController::class, 'showSlug']);
Route::get('/courses/course_id/{id}', [CourseController::class, 'show']);
// Student Routes
Route::middleware(['jwt_cookie', 'student'])->group(function () {
    Route::get('/courses', [CourseController::class, 'index']);
    Route::put('/enrollments/{id}', [EnrollmentController::class, 'update']);//xong
    Route::get('/enrollments/student', [EnrollmentController::class, 'getStudentEnrollments']);//xong
    Route::delete('/enrollments/{id}', [EnrollmentController::class, 'destroy']);//xong
    Route::get('/enrollments/{id}/status', [EnrollmentController::class, 'checkEnrollmentStatus']);//xong
    Route::post('/enrollments/{id}/complete', [EnrollmentController::class, 'completeEnrollment']);//xong
    Route::get('/enrollments/{id}/progress', [EnrollmentController::class, 'getLearningProgress']);//xong
    Route::post('/enrollments/{id}/review', [EnrollmentController::class, 'submitReview']);//xong
    //free course enrollment
    Route::post('/courses/{course_id}/enroll-free', [EnrollmentController::class, 'enrollFreeCourse'])->name('enrollments.enrollFree');//xong
    Route::post('/courses/{course_id}/enroll-paid', [EnrollmentController::class, 'storePaid'])//xong
        ->name('enrollments.enrollPaid');
    Route::get('/payments', [PaymentController::class, 'index']);//xong
    Route::get('/payments/{id}', [PaymentController::class, 'show']);//xong
    // Check Payment Status
    Route::get('/payments/{payment_id}/status', [PaymentController::class, 'checkPaymentStatus'])->name('payments.checkStatus');//xong

    // View Lesson Content (Videos, Materials)
    Route::get('/courses/{course_id}/lessons/{lesson_id}', [LessonController::class, 'showForStudent'])->name('lessons.showForStudent');//xong
    Route::get('/courses/{enroll_id}/lessons', [LessonController::class, 'getCourseLessons'])->name('lessons.getCourseLessons');//xong
    // Quiz Interactions
    Route::get('/courses/{course_id}/quizzes', [QuizController::class, 'indexForStudent'])->name('quizzes.indexForStudent');//xong
    Route::get('/quizzes/{quiz_id}', [QuizController::class, 'showForStudent'])->name('quizzes.showForStudent');//xong
    Route::post('/quizzes/{quiz_id}/retry', [QuizController::class, 'retryQuiz'])->name('quizzes.retry');//xong
    Route::get('/courses/{course_id}/quiz-progress', [QuizController::class, 'quizProgressForCourse'])->name('quizzes.progressForCourse');//xong
    Route::post('/quizzes/{quiz_id}/submit', [QuizController::class, 'submitQuiz'])->name('quizzes.submit');//xong
    Route::get('/quizzes/{quiz_id}/draft', [QuizController::class, 'getDraft'])->name('quizzes.getDraft');//xong
    Route::get('/quizzes/{quiz_id}/results/{quiz_result_id}', [QuizController::class, 'getResult'])->name('quizzes.getResult');//xong
    Route::post('/quizzes/{quiz_id}/start', [QuizController::class, 'startQuiz'])->name('quizzes.start');//xong
    // Track Learning Progress
    Route::get('/lesson-progress', [LessonProgressController::class, 'indexForStudent'])->name('lessonProgress.indexForStudent');//xong
    Route::get('/lesson-progress/{id}', [LessonProgressController::class, 'showForStudent'])->name('lessonProgress.showForStudent');//xong

    // Ask Questions, Discuss (Forum Posts)
    Route::get('/courses/{course_id}/forum-posts', [ForumPostController::class, 'indexForStudent'])->name('forumPosts.indexForStudent');//xong
    Route::post('/courses/{course_id}/forum-posts', [ForumPostController::class, 'storeForStudent'])->name('forumPosts.storeForStudent');//xong

    Route::get('/quizzes/{quiz_id}/questions', [QuizController::class, 'getQuestionsForStudent'])->name('quizzes.getQuestionsForStudent');//xong
    Route::post('/quizzes/{quiz_id}/draft', [QuizController::class, 'saveDraftAnswers'])->name('quizzes.saveDraftAnswers');//xong
    Route::post('/user-answers', [UserAnswerController::class, 'store'])->name('user-answers.store');//xong
    Route::get('/user-answers/{answer}', [UserAnswerController::class, 'show'])->name('user-answers.show');//xong
});

// Instructor Routes
Route::middleware(['jwt_cookie', 'instructor'])->group(function () {
  
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
    Route::get('/instructor/deleted-courses', [CourseController::class, 'getDeletedCoursesForInstructor']);
});

// Admin Routes
Route::middleware(['jwt_cookie', 'admin'])->group(function () {
    // Existing Routes
    Route::post('/admin/courses', [CourseController::class, 'store']);
    Route::put('/admin/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/admin/courses/{id}', [CourseController::class, 'destroy']);
    Route::delete('/admin/force-delete/{id}', [CourseController::class, 'forceDelete']);
    Route::get('/admin/courses/{id}/admin-stats', [CourseController::class, 'adminStats']);
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
    Route::get('/admin/certificates/trashed', [CertificateController::class, 'trashed']);
    Route::get('/admin/certificates', [CertificateController::class, 'index']);
    Route::get('/admin/certificates/{id}', [CertificateController::class, 'show']);
    Route::post('/admin/certificates', [CertificateController::class, 'store']);
    Route::put('/admin/certificates/{id}', [CertificateController::class, 'update']);
    Route::delete('/admin/certificates/{id}', [CertificateController::class, 'destroy']);
    Route::get('/admin/forum-posts/trashed', [ForumPostController::class, 'trashed']);
    Route::get('/admin/forum-posts', [ForumPostController::class, 'index']);
    Route::get('/admin/forum-posts/{id}', [ForumPostController::class, 'show']);
    Route::post('/admin/forum-posts', [ForumPostController::class, 'store']);
    Route::put('/admin/forum-posts/{id}', [ForumPostController::class, 'update']);
    Route::delete('/admin/forum-posts/{id}', [ForumPostController::class, 'destroy']);
    Route::get('/admin/reviews/trashed', [ReviewController::class, 'trashed']);
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
    Route::put('/admin/courses/{course_id}/lessons/{lesson_id}/approve', [LessonController::class, 'approve'])->name('lessons.approve');
    Route::put('/admin/courses/{course_id}/lessons/{lesson_id}/reject', [LessonController::class, 'reject'])->name('lessons.reject');
    Route::get('/admin/lessonProgress', [LessonProgressController::class, 'index']);
    Route::get('/admin/lessonProgress/{id}', [LessonProgressController::class, 'show']);
    Route::post('/admin/lessonProgress', [LessonProgressController::class, 'store']);
    Route::put('/admin/lessonProgress/{id}', [LessonProgressController::class, 'update']);
    Route::delete('/admin/lessonProgress/{id}', [LessonProgressController::class, 'destroy']);
     Route::post('/admin/enrollments', [EnrollmentController::class, 'store']);
    // Manage Payments
    Route::get('/admin/payments/trashed', [PaymentController::class, 'trashed']);
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
    Route::delete('/admin/users/{id}/force', [UserController::class, 'forceDelete']);
    Route::delete('/admin/users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::patch('/admin/users/{id}/restore', [UserController::class, 'restore']);

    //Analytics
    Route::get('/admin/analytics/courses', [AnalyticsController::class, 'adminCourseAnalytics'])
        ->name('analytics.courses');
    Route::get('/admin/analytics/users', [AnalyticsController::class, 'adminUserAnalytics'])
        ->name('analytics.users');

    Route::apiResource('/admin/questions', QuestionController::class);
    Route::apiResource('/admin/question-choices', QuestionChoiceController::class);
    Route::get('/admin/enrollments/trashed', [EnrollmentController::class, 'trashed']);
    Route::get('admin/enrollments', [EnrollmentController::class, 'index']);//xong
    Route::get('admin/enrollments/{id}', [EnrollmentController::class, 'show']);//xong
    Route::put('/admin/enrollments/{id}', [EnrollmentController::class, 'updateAdmin']);//xong
    Route::get('/admin/deleted-courses', [CourseController::class, 'getDeletedCoursesForAdmin']);
    Route::get('/admin/courses', [CourseController::class, 'getAllCoursesForAdmin']);

    Route::post('/admin/enrollments/restore/{id}', [EnrollmentController::class, 'restore']);
    Route::delete('/admin/enrollments/force-delete/{id}', [EnrollmentController::class, 'forceDelete']);


    Route::post('/admin/reviews/restore/{id}', [ReviewController::class, 'restore']);
    Route::delete('/admin/reviews/force-delete/{id}', [ReviewController::class, 'forceDelete']);

    Route::get('/admin/instructors/trashed', [InstructorController::class, 'trashed']);
    Route::get('/admin/instructors', [InstructorController::class, 'index']);
    Route::get('/admin/instructors/{id}', [InstructorController::class, 'show']);
    Route::post('/admin/instructors', [InstructorController::class, 'store']);
    Route::put('/admin/instructors/{id}', [InstructorController::class, 'update']);
    Route::delete('/admin/instructors/{id}', [InstructorController::class, 'destroy']);
    Route::post('/admin/instructors/restore/{id}', [InstructorController::class, 'restore']);
    Route::delete('/admin/instructors/force-delete/{id}', [InstructorController::class, 'forceDelete']);

    Route::post('/admin/certificates/restore/{id}', [CertificateController::class, 'restore']);
    Route::delete('/admin/certificates/force-delete/{id}', [CertificateController::class, 'forceDelete']);

   
    Route::post('/admin/payments/restore/{id}', [PaymentController::class, 'restore']);
    Route::delete('/admin/payments/force-delete/{id}', [PaymentController::class, 'forceDelete']);

    Route::get('/admin/interaction/trashed', [InteractionController::class, 'trashed']);
    Route::get('/admin/interaction', [InteractionController::class, 'index']);
    Route::get('/admin/interaction/{id}', [InteractionController::class, 'show']);
    Route::post('/admin/interaction', [InteractionController::class, 'store']);
    Route::put('/admin/interaction/{id}', [InteractionController::class, 'update']);
    Route::delete('/admin/interaction/{id}', [InteractionController::class, 'destroy']);
    Route::post('/admin/interaction/restore/{id}', [InteractionController::class, 'restore']);
    Route::delete('/admin/interaction/force-delete/{id}', [InteractionController::class, 'forceDelete']);

    Route::get('/admin/students/trashed', [StudentController::class, 'trashed']);
    Route::get('/admin/students', [StudentController::class, 'index']);
    Route::get('/admin/students/{id}', [StudentController::class, 'show']);
    Route::post('/admin/students', [StudentController::class, 'store']);
    Route::put('/admin/students/{id}', [StudentController::class, 'update']);
    Route::delete('/admin/students/{id}', [StudentController::class, 'destroy']);
    Route::post('/admin/students/restore/{id}', [StudentController::class, 'restore']);
    Route::delete('/admin/students/force-delete/{id}', [StudentController::class, 'forceDelete']);


    Route::post('/admin/forum-posts/restore/{id}', [ForumPostController::class, 'restore']);
    Route::delete('/admin/forum-posts/force-delete/{id}', [ForumPostController::class, 'forceDelete']);
});

// Payment Callback (Existing)
Route::post('/vnpay_payment', [PaymentGateway::class, 'createOrder']);
Route::post('/payments/callback', [PaymentController::class, 'handleZaloPayCallback']);
Route::post('/payments/vnpay/callback', [PaymentController::class, 'handleVNPayCallback']);
Route::get('/vnpay/ipn', [PaymentController::class, 'handleVNPayIPN'])->name('vnpay.ipn');
Route::post('/password/email', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
Route::post('/password/reset', [AuthController::class, 'reset'])->name('password.reset');
Route::post('/courses/{id}/restore', [CourseController::class, 'restoreCourse']);
