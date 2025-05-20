<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizResult\StoreQuizResultRequest;
use App\Http\Requests\QuizResult\UpdateQuizResultRequest;
use App\Models\Enrollment;
use App\Models\QuizResult;
use App\Models\Quiz;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class QuizResultController extends Controller
{
    public function index(): JsonResponse
    {
        $quizResults = QuizResult::paginate(10);
        return response()->json(['data' => $quizResults]);
    }

    public function show($id): JsonResponse
    {
        $quizResult = QuizResult::findOrFail($id);
        return response()->json(['data' => $quizResult]);
    }

  public function store(StoreQuizResultRequest $request): JsonResponse
{
    // 1. Kiểm tra vai trò của user (phải là student)
    $user = User::find($request->user_id);
    if (!$user || $user->role !== 'student') {
        return response()->json([
            'message' => 'Only students can submit quiz results'
        ], 403);
    }

    // 2. Kiểm tra xem quiz có tồn tại không
    $quiz = Quiz::find($request->quiz_id);
    if (!$quiz) {
        return response()->json([
            'message' => 'Quiz not found'
        ], 404);
    }

    // 3. Lấy course_id từ lesson của quiz
    $lesson = Lesson::find($quiz->lesson_id);
    if (!$lesson) {
        return response()->json([
            'message' => 'Lesson for this quiz not found'
        ], 404);
    }

    // 4. Kiểm tra enrollment của user với course
    $enrollment = Enrollment::where('user_id', $request->user_id)
        ->where('course_id', $lesson->course_id)
        ->where('status', 'active')
        ->first();

    if (!$enrollment) {
        return response()->json([
            'message' => 'The student is not enrolled in the course associated with this quiz'
        ], 403);
    }

    // 5. Kiểm tra xem quiz có đang visible không
    if (!$quiz->is_visible) {
        return response()->json([
            'message' => 'This quiz is currently not available'
        ], 403);
    }

    // 6. Kiểm tra số lần làm bài
    $attempts = QuizResult::where('user_id', $request->user_id)
        ->where('quiz_id', $request->quiz_id)
        ->count();

    if ($attempts >= $quiz->max_attempts) {
        return response()->json([
            'message' => 'Maximum quiz attempts reached'
        ], 403);
    }

    // 7. Tạo quiz result
    try {
        $quizResult = QuizResult::create([
            'user_id' => $request->user_id,
            'quiz_id' => $request->quiz_id,
            'attempt_number' => $attempts + 1,
            'score' => $request->score,
            'started_at' => now(),
            'completed_at' => $request->completed_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Quiz result created successfully',
            'data' => $quizResult
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to create quiz result',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function update(UpdateQuizResultRequest $request, $id): JsonResponse
    {
        $quizResult = QuizResult::findOrFail($id);
        $quizResult->update($request->validated());
        return response()->json(['message' => 'Quiz result updated successfully', 'data' => $quizResult]);
    }

    public function destroy($id): JsonResponse
    {
        $quizResult = QuizResult::findOrFail($id);
        $quizResult->delete();
        return response()->json(['message' => 'Quiz result deleted successfully']);
    }
}