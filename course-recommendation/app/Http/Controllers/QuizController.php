<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quiz\StoreQuizRequest;
use App\Http\Requests\Quiz\UpdateQuizRequest;
use App\Models\CertificateRule;
use App\Models\Course;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Lesson;
use App\Models\Enrollment;
use App\Models\Course_Instructors;
use App\Models\Instructors;
use App\Models\QuizResult;
use App\Models\UserAnswer;
use App\Models\QuestionChoice;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    /**
     * Admin quiz.
     */
    public function index(): JsonResponse
    {
        $quizzes = Quiz::paginate(10);
        return response()->json(['data' => $quizzes]);
    }

    public function show($id): JsonResponse
    {
        $quiz = Quiz::findOrFail($id);
        return response()->json(['data' => $quiz]);
    }

    public function store(StoreQuizRequest $request): JsonResponse
    {
        $quiz = Quiz::create($request->validated());
        return response()->json(['message' => 'Quiz created successfully', 'data' => $quiz], 201);
    }

    public function update(UpdateQuizRequest $request, $id): JsonResponse
    {
        $quiz = Quiz::findOrFail($id);
        $quiz->fill($request->validated());
        if(!$quiz->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 400);
        }
        $quiz->update($request->validated());
        return response()->json(['message' => 'Quiz updated successfully', 'data' => $quiz]);
    }

    public function destroy($id): JsonResponse
    {
        $quiz = Quiz::findOrFail($id);
        $quiz->delete();
        return response()->json(['message' => 'Quiz deleted successfully']);
    }
    // Instructor quiz

public function indexForInstructor(Request $request, $courseId): JsonResponse
    {
        // Get the authenticated user
        $user = Auth::user();

        // Check if the user is authenticated and has the instructor role
        if (!$user || $user->role !== 'instructor') {
            return response()->json([
                'message' => 'Unauthorized. Only instructors can access this endpoint.'
            ], 403);
        }

        // Find the instructor record for the user
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor) {
            return response()->json([
                'message' => 'Instructor profile not found.'
            ], 404);
        }

        // Validate course ID and check if the course exists
        if (!$courseId || !is_numeric($courseId)) {
            return response()->json([
                'message' => 'Valid Course ID is required.'
            ], 422);
        }

        $course = Course::find($courseId);
        if (!$course) {
            return response()->json([
                'message' => 'Course not found.'
            ], 404);
        }

        // Fetch all quizzes for the course
        $quizzes = Quiz::whereHas('lesson', function ($query) use ($courseId) {
            $query->where('course_id', $courseId);
        })
            ->select('id', 'lesson_id', 'title', 'max_attempts', 'time_limit', 'is_visible', 'created_at', 'updated_at')
            ->with(['lesson' => function ($query) {
                $query->select('id', 'course_id', 'title');
            }])
            ->paginate();

        // Return the quizzes in a JSON response
        return response()->json([
            'message' => 'Quizzes retrieved successfully.',
            'data' => [
                'course_id' => $courseId,
                'course_name' => $course->course_name,
                'quizzes' => $quizzes
            ]
        ], 200);
    }


    public function showForInstructor($id)
    {
        $user = Auth::user();
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }
          // Check if instructor owns this course
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return response()->json($quiz, 200);
    }

    public function storeForInstructor(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'title' => 'required|string|max:255',
            'max_attempts' => 'nullable|integer|min:1',
            'time_limit' => 'nullable|integer|min:1',
            'is_visible' => 'required|boolean',
        ]);
        
        $lesson = Lesson::find($validated['lesson_id']);
        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        // Check if instructor owns this course
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor || $lesson->course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $quiz = Quiz::create($validated);
        return response()->json($quiz, 201);
    }

    public function updateForInstructor(UpdateQuizRequest $request, $id)
    {
        $user = Auth::user();
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }
        // Check if instructor owns this course
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $quiz->fill($request->validated());
        if(!$quiz->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 400);
        }
        $quiz->update($request->validated());
        return response()->json($quiz, 200);
    }

    public function destroyForInstructor($id)
    {
        $user = Auth::user();
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }
        // Check if instructor owns this course
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $quiz->delete();
        return response()->json(['message' => 'Quiz deleted successfully'], 200);
    }
/**
     * View quiz results for all students in a course.
     */
    public function studentQuizResults($quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::with(['lesson.course'])->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }
        // Get quiz results for all students
        $results = QuizResult::where('quiz_id', $quiz_id)
            ->with(['user' => function ($query) {
                $query->select('id', 'name', 'email');
            }])
            ->select('id', 'user_id', 'quiz_id', 'score', 'completed_at')
            ->orderBy('completed_at', 'desc')
            ->get();

        // Calculate statistics
        $total_attempts = $results->count();
        $average_score = $results->avg('score') ?? 0;
        $highest_score = $results->max('score') ?? 0;
        $lowest_score = $results->min('score') ?? 0;

        return response()->json([
            'quiz' => $quiz,
            'results' => $results,
            'statistics' => [
                'total_attempts' => $total_attempts,
                'average_score' => round($average_score, 2),
                'highest_score' => $highest_score,
                'lowest_score' => $lowest_score,
            ],
        ], 200);
    }

    /**
     * Update quiz settings (e.g., max attempts, time limit).
     */
    public function updateQuizSettings(Request $request, $quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }
        $validated = $request->validate([
            'max_attempts' => 'nullable|integer|min:1',
            'time_limit' => 'nullable|integer|min:1', // In minutes
            'is_visible' => 'nullable|boolean',
        ]);
        $quiz->fill($validated);
        if(!$quiz->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 400);
        }
        // Update quiz settings (assuming these columns exist in quizzes table)
        $quiz->update($validated);

        return response()->json([
            'message' => 'Quiz settings updated successfully',
            'data' => $quiz,
        ], 200);
    }

    /**
     * Preview a quiz as a student would see it.
     */
    public function previewQuiz($quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::with('lesson.course')->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }
          // Check if instructor owns this course
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Return quiz details as a student would see (same as showForStudent)
        return response()->json([
            'data' => $quiz,
        ], 200);
    }
    //Student quiz

    /**
     * Retrieve all quizzes for a specific course the student is enrolled in.
     */
    public function indexForStudent($course_id): JsonResponse
    {
        $user = Auth::user();

        // Check if the user is enrolled in the course
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course_id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        // Get all quizzes for the course
        $quizzes = Quiz::whereHas('lesson', function ($query) use ($course_id) {
            $query->where('course_id', $course_id);
        })->with(['lesson' => function ($query) {
            $query->select('id', 'course_id', 'title');
        }])->paginate(10);

        return response()->json(['data' => $quizzes], 200);
    }

    /**
     * View details of a specific quiz, including past attempts (if any).
     */
    // public function showForStudent($quiz_id): JsonResponse
    // {
    //     $user = Auth::user();
    //     $quiz = Quiz::with('lesson')->find($quiz_id);

    //     if (!$quiz) {
    //         return response()->json(['message' => 'Quiz not found'], 404);
    //     }

    //     // Check if the user is enrolled in the course
    //     $enrollment = Enrollment::where('user_id', $user->id)
    //         ->where('course_id', $quiz->lesson->course_id)
    //         ->where('status', 'active')
    //         ->first();

    //     if (!$enrollment) {
    //         return response()->json(['message' => 'You are not enrolled in this course'], 403);
    //     }

    //     // Get past quiz attempts by the user
    //     $attempts = QuizResult::where('user_id', $user->id)
    //         ->where('quiz_id', $quiz_id)
    //         ->orderBy('completed_at', 'desc')
    //         ->get(['id', 'score', 'completed_at']);

    //     return response()->json([
    //         'data' => $quiz,
    //         'attempts' => $attempts
    //     ], 200);
    // }

    /**
     * Retry a quiz (if allowed based on course rules).
     */
//     public function retryQuiz(Request $request, $quiz_id): JsonResponse
//     {
//     $user = Auth::user();
//     $quiz = Quiz::with('questions.choices')->find($quiz_id);

//     if (!$quiz) {
//         return response()->json(['message' => 'Quiz not found'], 404);
//     }

//     // Check enrollment or instructor
//     $lesson = Lesson::find($quiz->lesson_id);
//     $enrollment = Enrollment::where('user_id', $user->id)
//         ->where('course_id', $lesson->course_id)
//         ->where('status', 'active')
//         ->first();

//     $instructor = Course_Instructors::where('course_id', $lesson->course_id)
//         ->whereHas('instructor', function ($query) use ($user) {
//             $query->where('user_id', $user->id);
//         })
//         ->first();

//     if (!$enrollment && !$instructor) {
//         return response()->json(['message' => 'You do not have access to this quiz'], 403);
//     }

//     // Check max attempts
//     $attempts = QuizResult::where('user_id', $user->id)
//         ->where('quiz_id', $quiz_id)
//         ->count();

//     if ($attempts >= $quiz->max_attempts) {
//         return response()->json(['message' => 'Maximum attempts reached'], 403);
//     }

//     // Validate answers
//     $validated = $request->validate([
//         'answers' => 'required|array',
//         'answers.*.question_id' => 'required|exists:questions,id,quiz_id,' . $quiz_id,
//         'answers.*.choice_id' => 'nullable|exists:question_choices,id',
//         'answers.*.answer_text' => 'nullable|string',
//     ]);

//     // Start quiz attempt
//     $quizResult = QuizResult::create([
//         'user_id' => $user->id,
//         'quiz_id' => $quiz_id,
//         'attempt_number' => $attempts + 1,
//         'started_at' => now(),
//         'score' => 0,
//     ]);
//     if ($quiz->time_limit && $quizResult->started_at->diffInMinutes(now()) > $quiz->time_limit) {
//     return response()->json(['message' => 'Time limit exceeded'], 403);
// }
//     // Process answers
//     $totalScore = 0;
//     foreach ($validated['answers'] as $answer) {
//         $question = $quiz->questions->find($answer['question_id']);
//         $isCorrect = null;
//         $pointsEarned = 0;

//         if ($question->question_type === 'multiple_choice' || $question->question_type === 'true_false') {
//             $choice = QuestionChoice::find($answer['choice_id']);
//             if ($choice) {
//                 $isCorrect = $choice->is_correct;
//                 $pointsEarned = $isCorrect ? $question->points : 0;
//             }
//         } elseif ($question->question_type === 'open_ended') {
//             // Open-ended answers need manual grading
//             $isCorrect = null;
//             $pointsEarned = null;
//         }

//         $totalScore += $pointsEarned ?? 0;

//         UserAnswer::create([
//             'user_id' => $user->id,
//             'quiz_result_id' => $quizResult->id,
//             'question_id' => $answer['question_id'],
//             'choice_id' => $answer['choice_id'] ?? null,
//             'answer_text' => $answer['answer_text'] ?? null,
//             'is_correct' => $isCorrect,
//             'points_earned' => $pointsEarned,
//         ]);
//     }

//     // Update quiz result
//     $quizResult->update([
//         'score' => $totalScore,
//         'completed_at' => now(),
//     ]);

//     return response()->json([
//         'message' => 'Quiz retried successfully',
//         'data' => $quizResult->load('userAnswers'),
//     ], 201);
//     }

    /**
     * Get progress summary for all quizzes in a course.
     */
    public function quizProgressForCourse($course_id): JsonResponse
    {
        $user = Auth::user();

        // Check enrollment
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course_id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        // Get all quizzes for the course with user's results
        $quizzes = Quiz::whereHas('lesson', function ($query) use ($course_id) {
            $query->where('course_id', $course_id);
        })->with(['lesson' => function ($query) {
            $query->select('id', 'course_id', 'title');
        }, 'quizResults' => function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->select('id', 'quiz_id', 'score', 'completed_at')
                  ->orderBy('completed_at', 'desc');
        }])->get();

        // Calculate summary
        $total_quizzes = $quizzes->count();
        $completed_quizzes = $quizzes->filter(function ($quiz) {
            return $quiz->quizResults->isNotEmpty();
        })->count();
        $average_score = $quizzes->reduce(function ($carry, $quiz) {
            return $carry + ($quiz->quizResults->avg('score') ?? 0);
        }, 0) / max(1, $completed_quizzes);

        return response()->json([
            'total_quizzes' => $total_quizzes,
            'completed_quizzes' => $completed_quizzes,
            'average_score' => round($average_score, 2),
            'quizzes' => $quizzes
        ], 200);
    }
//     public function submitQuiz(Request $request, $quiz_id): JsonResponse
// {
//     $user = Auth::user();
//     $quiz = Quiz::with('questions.choices')->find($quiz_id);

//     if (!$quiz) {
//         return response()->json(['message' => 'Quiz not found'], 404);
//     }

//     // Check enrollment or instructor
//     $lesson = Lesson::find($quiz->lesson_id);
//     $enrollment = Enrollment::where('user_id', $user->id)
//         ->where('course_id', $lesson->course_id)
//         ->where('status', 'active')
//         ->first();

//     $instructor = Course_Instructors::where('course_id', $lesson->course_id)
//         ->whereHas('instructor', function ($query) use ($user) {
//             $query->where('user_id', $user->id);
//         })
//         ->first();

//     if (!$enrollment && !$instructor) {
//         return response()->json(['message' => 'You do not have access to this quiz'], 403);
//     }

//     // Check max attempts
//     $attempts = QuizResult::where('user_id', $user->id)
//         ->where('quiz_id', $quiz_id)
//         ->count();

//     if ($attempts >= $quiz->max_attempts) {
//         return response()->json(['message' => 'Maximum attempts reached'], 403);
//     }

//     // Validate answers
//     $validated = $request->validate([
//         'answers' => 'required|array',
//         'answers.*.question_id' => 'required|exists:questions,id,quiz_id,' . $quiz_id,
//         'answers.*.choice_id' => 'nullable|exists:question_choices,id',
//         'answers.*.answer_text' => 'nullable|string',
//     ]);
    
//     // Start quiz attempt
//     $quizResult = QuizResult::create([
//         'user_id' => $user->id,
//         'quiz_id' => $quiz_id,
//         'attempt_number' => $attempts + 1,
//         'started_at' => now(),
//         'score' => 0,
//     ]);
//     if ($quiz->time_limit && $quizResult->started_at->diffInMinutes(now()) > $quiz->time_limit) {
//     return response()->json(['message' => 'Time limit exceeded'], 403);
// }
//     // Process answers
//     $totalScore = 0;
//     foreach ($validated['answers'] as $answer) {
//         $question = $quiz->questions->find($answer['question_id']);
//         $isCorrect = null;
//         $pointsEarned = 0;

//         if ($question->question_type === 'multiple_choice' || $question->question_type === 'true_false') {
//             $choice = QuestionChoice::find($answer['choice_id']);
//             if ($choice) {
//                 $isCorrect = $choice->is_correct;
//                 $pointsEarned = $isCorrect ? $question->points : 0;
//             }
//         } elseif ($question->question_type === 'open_ended') {
//             // Open-ended answers need manual grading
//             $isCorrect = null;
//             $pointsEarned = null;
//         }

//         $totalScore += $pointsEarned ?? 0;

//         UserAnswer::create([
//             'user_id' => $user->id,
//             'quiz_result_id' => $quizResult->id,
//             'question_id' => $answer['question_id'],
//             'choice_id' => $answer['choice_id'] ?? null,
//             'answer_text' => $answer['answer_text'] ?? null,
//             'is_correct' => $isCorrect,
//             'points_earned' => $pointsEarned,
//         ]);
//     }

//     // Update quiz result
//     $quizResult->update([
//         'score' => $totalScore,
//         'completed_at' => now(),
//     ]);

//     return response()->json([
//         'message' => 'Quiz submitted successfully',
//         'data' => $quizResult->load('userAnswers'),
//     ], 201);
// }
// public function getQuestionsForStudent($quiz_id): JsonResponse
// {
//     $user = Auth::user();
//     $quiz = Quiz::with(['questions' => function ($query) {
//         $query->where('is_visible', true)->with('choices');
//     }])->find($quiz_id);

//     if (!$quiz) {
//         return response()->json(['message' => 'Quiz not found'], 404);
//     }

//     // Check enrollment
//     $enrollment = Enrollment::where('user_id', $user->id)
//         ->where('course_id', $quiz->lesson->course_id)
//         ->where('status', 'active')
//         ->first();

//     if (!$enrollment) {
//         return response()->json(['message' => 'You are not enrolled in this course'], 403);
//     }

//     return response()->json(['data' => $quiz->questions], 200);
// }

public function gradeOpenEndedAnswer(Request $request, $user_answer_id): JsonResponse
{
    $user = Auth::user();
    $userAnswer = UserAnswer::with('question.quiz.lesson')->find($user_answer_id);

    if (!$userAnswer) {
        return response()->json(['message' => 'Answer not found'], 404);
    }
    $validated = $request->validate([
        'is_correct' => 'required|boolean',
        'points_earned' => 'required|numeric|min:0|max:' . $userAnswer->question->points,
    ]);

    $userAnswer->update([
        'is_correct' => $validated['is_correct'],
        'points_earned' => $validated['points_earned'],
    ]);

    // Update quiz result score
    $quizResult = $userAnswer->quizResult;
    $totalScore = $quizResult->userAnswers->sum('points_earned');
    $quizResult->update(['score' => $totalScore]);

    return response()->json([
        'message' => 'Answer graded successfully',
        'data' => $userAnswer,
    ], 200);
}
public function saveDraftAnswers(Request $request, $quiz_id): JsonResponse
{
    $user = Auth::user();
    $quiz = Quiz::find($quiz_id);

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }

    // Check enrollment
    $enrollment = Enrollment::where('user_id', $user->id)
        ->where('course_id', $quiz->lesson->course_id)
        ->first();

    if (!$enrollment) {
        return response()->json(['message' => 'You are not enrolled in this course'], 403);
    }

    $validated = $request->validate([
        'answers' => 'required|array',
        'answers.*.question_id' => 'required|exists:questions,id,quiz_id,' . $quiz_id,
        'answers.*.choice_id' => 'nullable|exists:question_choices,id',
        'answers.*.answer_text' => 'nullable|string',
    ]);

    // Find or create draft quiz result
    $quizResult = QuizResult::firstOrCreate(
        [
            'user_id' => $user->id,
            'quiz_id' => $quiz_id,
            'completed_at' => null,
        ],
        [
            'attempt_number' => 1,
            'started_at' => now(),
            'score' => 0,
        ]
    );

    // Save draft answers
    foreach ($validated['answers'] as $answer) {
        // UserAnswer::updateOrCreate(
        //     [
        //         'user_id' => $user->id,
        //         'quiz_result_id' => $quizResult->id,
        //         'question_id' => $answer['question_id'],
        //     ],
        //     [
        //         'choice_id' => $answer['choice_id'] ?? null,
        //         'answer_text' => $answer['answer_text'] ?? null,
        //         'is_correct' => null,
        //         'points_earned' => null,
        //     ]
        // );
            UserAnswer::updateOrCreate(
            [
                'quiz_result_id' => $quizResult->id,
                'question_id' => $answer['question_id'],
            ],
            [
                'choice_id' => $answer['choice_id'] ?? null,
                'is_correct' => null,
            ]
        );
    }

    return response()->json([
        'message' => 'Draft answers saved successfully',
        'data' => $quizResult,
    ], 200);
}

 /**
     * Lấy bản nháp của quiz để tiếp tục làm bài.
     */
    public function getDraft($quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::with('lesson')->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        // Kiểm tra đăng ký khóa học
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $quiz->lesson->course_id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        // Tìm bản nháp
        $draft = QuizResult::where('user_id', $user->id)
            ->where('quiz_id', $quiz_id)
            ->whereNull('completed_at')
            ->with(['userAnswers' => function ($query) {
                $query->with(['question', 'choice']);
            }])
            ->first();

        if (!$draft) {
            return response()->json(['message' => 'Can not find draft answer'], 404);
        }

        return response()->json([
            'message' => 'Lấy bản nháp thành công',
            'data' => $draft
        ], 200);
    }

    /**
     * Lấy kết quả chi tiết của một lần làm bài quiz.
     */
    public function getResult($quiz_id, $quiz_result_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::with('lesson')->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        // Kiểm tra đăng ký khóa học
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $quiz->lesson->course_id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enroll in this course'], 403);
        }

        // Lấy kết quả quiz
        $quizResult = QuizResult::where('id', $quiz_result_id)
            ->where('user_id', $user->id)
            ->where('quiz_id', $quiz_id)
            ->with(['userAnswers' => function ($query) {
                $query->with(['question' => function ($q) {
                    $q->with(['choices' => function ($c) {
                        $c->where('is_correct', true); // Lấy câu trả lời đúng
                    }]);
                }, 'choice']);
            }])
            ->first();

        if (!$quizResult) {
            return response()->json(['message' => 'Quiz result not found'], 404);
        }

        return response()->json([
            'message' => 'Get detailed quiz result successfully',
            'data' => $quizResult
        ], 200);
    }

    // /**
    //  * Bắt đầu một phiên làm bài quiz.
    //  */
    // public function startQuiz(Request $request, $quiz_id): JsonResponse
    // {
    //     $user = Auth::user();
    //     $quiz = Quiz::with(['lesson'])->find($quiz_id);

    //     if (!$quiz) {
    //         return response()->json(['message' => 'Quiz không tìm thấy'], 404);
    //     }

    //     // Kiểm tra đăng ký khóa học
    //     $lesson = Lesson::find($quiz->lesson_id);
    //     $enrollment = Enrollment::where('user_id', $user->id)
    //         ->where('course_id', $lesson->course_id)
    //         ->where('status', 'active')
    //         ->first();

    //     if (!$enrollment) {
    //         return response()->json(['message' => 'Bạn chưa đăng ký khóa học này'], 403);
    //     }

    //     // Kiểm tra số lần làm bài
    //     $attempts = QuizResult::where('user_id', $user->id)
    //         ->where('quiz_id', $quiz_id)
    //         ->count();

    //     if ($attempts >= $quiz->max_attempts) {
    //         return response()->json(['message' => 'Đã đạt số lần làm bài tối đa'], 403);
    //     }

    //     // Kiểm tra quiz có hiển thị không
    //     if (!$quiz->is_visible) {
    //         return response()->json(['message' => 'Quiz hiện không khả dụng'], 403);
    //     }

    //     // Tạo QuizResult mới
    //     $quizResult = QuizResult::create([
    //         'user_id' => $user->id,
    //         'quiz_id' => $quiz_id,
    //         'attempt_number' => $attempts + 1,
    //         'started_at' => now(),
    //         'score' => 0,
    //     ]);

    //     return response()->json([
    //         'message' => 'Bắt đầu quiz thành công',
    //         'data' => [
    //             'quiz_result_id' => $quizResult->id,
    //             'started_at' => $quizResult->started_at,
    //         ]
    //     ], 201);
    // }

    /**
 * Bắt đầu một phiên làm bài quiz.
 */

public function startQuiz(Request $request, $quiz_id): JsonResponse
{
    $user = Auth::user();
    $quiz = Quiz::with(['lesson', 'questions.choices'])->find($quiz_id);

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }

    // Kiểm tra đăng ký khóa học
    $enrollment = Enrollment::where('user_id', $user->id)
        ->where('course_id', $quiz->lesson->course_id)
        ->first();

    if (!$enrollment) {
        return response()->json(['message' => 'You are not enrolled in this course'], 403);
    }

    // Kiểm tra số lần làm bài
    $attempts = QuizResult::where('user_id', $user->id)
        ->where('quiz_id', $quiz_id)
        ->count();

    if ($attempts >= $quiz->max_attempts) {
        return response()->json(['message' => 'Out of amount to take the quiz'], 403);
    }

    // Kiểm tra quiz có hiển thị không
    if (!$quiz->is_visible) {
        return response()->json(['message' => 'Quiz is unavailable at the moment'], 403);
    }

    // Xóa bản nháp cũ nếu có (chưa hoàn thành)
    // QuizResult::where('user_id', $user->id)
    //     ->where('quiz_id', $quiz_id)
    //     ->whereNull('completed_at')
    //     ->delete();

    // Tạo snapshot toàn bộ quiz tại thời điểm hiện tại
    // $snapshot = [
    //     'quiz' => [
    //         'id' => $quiz->id,
    //         'title' => $quiz->title,
    //         'max_attempts' => $quiz->max_attempts,
    //         'time_limit' => $quiz->time_limit,
    //         'is_visible' => $quiz->is_visible,
    //     ],
    //     'questions' => $quiz->questions->map(function($question) {
    //         return [
    //             'id' => $question->id,
    //             'title' => $question->title,
    //             'question_type' => $question->question_type,
    //             'choices' => $question->choices->map(function($choice) {
    //                 return [
    //                     'id' => $choice->id,
    //                     'content' => $choice->content,
    //                     'is_correct' => $choice->is_correct,
    //                 ];
    //             })
    //         ];
    //     })
    // ];

    // Tạo QuizResult mới với snapshot
    $quizResult = QuizResult::create([
        'user_id' => $user->id,
        'quiz_id' => $quiz_id,
        'attempt_number' => $attempts + 1,
        'started_at' => now(),
        'score' => 0,
        // 'snapshot_json' => json_encode($snapshot),
    ]);

    return response()->json([
        'message' => 'Start Quiz successfully',
        'data' => [
            'quiz_result_id' => $quizResult->id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $quiz->lesson->course_id,
            'started_at' => $quizResult->started_at,
            'time_limit' => $quiz->time_limit,
            'max_attempts' => $quiz->max_attempts,
            'current_attempt' => $attempts + 1,
        ]
    ], 201);
}

    /**
     * Lấy câu hỏi cho sinh viên, hỗ trợ random và giới hạn số lượng.
     */
  public function getQuestionsForStudent($quiz_id): JsonResponse
{
    $user = Auth::user();
    $limit = request()->query('limit', null);
    $page = request()->query('page', 1);

    $quiz = Quiz::with(['questions.choices'])->find($quiz_id);

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }

    // Kiểm tra đăng ký khóa học
    $enrollment = Enrollment::where('user_id', $user->id)
        ->where('course_id', $quiz->lesson->course_id)
        ->first();

    if (!$enrollment) {
        return response()->json(['message' => 'You are not enrolled in this course'], 403);
    }

    // Lấy toàn bộ questions rồi phân trang thủ công (vì cần xử lý từng câu hỏi)
    $questions = $quiz->questions;

    // Gắn thêm `is_multiple_correct` cho từng câu hỏi
    $questions->each(function ($question) {
        $correctCount = $question->choices->where('is_correct', true)->count();
        $question->is_multiple_correct = $correctCount > 1;
    });

    // Phân trang nếu cần
    if ($limit) {
        $totalQuestions = $questions->count();
        $pagedQuestions = $questions->forPage($page, $limit)->values(); // reset key
    } else {
        $pagedQuestions = $questions;
        $totalQuestions = null;
    }

    return response()->json([
        'data' => $pagedQuestions,
        'pagination' => $limit ? [
            'page' => (int)$page,
            'per_page' => (int)$limit,
            'total' => $totalQuestions,
        ] : null
    ], 200);
}


    /**
     * Hiển thị chi tiết quiz cho sinh viên, bao gồm phản hồi.
     */
    public function showForStudent($quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::with('lesson')->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        // Kiểm tra đăng ký khóa học
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $quiz->lesson->course_id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        // Lấy các lần làm bài trước với phản hồi chi tiết
        $attempts = QuizResult::where('user_id', $user->id)
            ->where('quiz_id', $quiz_id)
            ->with(['userAnswers' => function ($query) {
                $query->with(['question' => function ($q) {
                    $q->with(['choices' => function ($c) {
                        $c->where('is_correct', true);
                    }]);
                }, 'choice']);
            }])
            ->orderBy('completed_at', 'desc')
            ->get(['id', 'score', 'completed_at', 'attempt_number']);

        return response()->json([
            'data' => $quiz,
            'attempts' => $attempts
        ], 200);
    }

//    /**
//  * Nộp bài quiz với kiểm tra thời gian và tính điểm từ snapshot.
//  */
// public function submitQuiz(Request $request, $quiz_id): JsonResponse
// {
//     $user = Auth::user();
    
//     // Validate câu trả lời
//     $validated = $request->validate([
//         'quiz_result_id' => 'required|exists:quiz_results,id',
//         'answers' => 'required|array',
//         'answers.*.question_id' => 'required|integer',
//         'answers.*.choice_id' => 'nullable|integer',
//     ]);
//     Log::info('Received answers:', ['answers' => $validated['answers']]);
//     // Tìm quiz result đang làm dở
//     $quizResult = QuizResult::where('id', $validated['quiz_result_id'])
//         ->where('user_id', $user->id)
//         ->where('quiz_id', $quiz_id)
//         ->whereNull('completed_at')
//         ->first();

//     if (!$quizResult) {
//         return response()->json(['message' => 'Không tìm thấy phiên làm bài hợp lệ'], 404);
//     }

//     // Lấy snapshot từ quiz result
//     $snapshot = json_decode($quizResult->snapshot_json, true);
//     if (!$snapshot) {
//         return response()->json(['message' => 'Dữ liệu quiz không hợp lệ'], 400);
//     }

//     // Kiểm tra thời gian (nếu có giới hạn)
//     if ($snapshot['quiz']['time_limit']) {
//         // $timeElapsed = $quizResult->started_at->diffInMinutes(now());
//         $timeElapsed = \Carbon\Carbon::parse($quizResult->started_at)->diffInMinutes(now());

//         if ($timeElapsed > $snapshot['quiz']['time_limit']) {
//             // Tự động nộp bài khi hết thời gian
//             $quizResult->update([
//                 'completed_at' => now(),
//                 'score' => 0
//             ]);
//             return response()->json(['message' => 'Đã vượt quá thời gian cho phép. Bài làm đã được tự động nộp với điểm 0.'], 403);
//         }
//     }

//     // Xử lý câu trả lời dựa trên snapshot
//     $results = [];
//     $totalScore = 0;
//     $totalQuestions = count($snapshot['questions']);

//     // Tạo map để dễ tìm kiếm
//     $questionsMap = collect($snapshot['questions'])->keyBy('id');
//     $answersMap = collect($validated['answers'])->keyBy('question_id');

//     foreach ($snapshot['questions'] as $questionData) {
//         $questionId = $questionData['id'];
//         $userAnswer = $answersMap->get($questionId);
        
//         $selectedChoice = null;
//         $correctChoices = collect($questionData['choices'])->where('is_correct', true);
//         $isCorrect = false;

//         // Tìm choice được chọn
//         if ($userAnswer && isset($userAnswer['choice_id'])) {
//             $selectedChoice = collect($questionData['choices'])->firstWhere('id', $userAnswer['choice_id']);
//             if ($selectedChoice && $selectedChoice['is_correct']) {
//                 $isCorrect = true;
//                 $totalScore++;
//             }
//         }
//         Log::info($questionId, $userAnswer);
//         // Lưu user answer vào database
//         UserAnswer::create([
//             'user_id' => $user->id,
//             'quiz_result_id' => $quizResult->id,
//             'question_index' => $questionId,
//             'choice_index' => $userAnswer['choice_id'] ?? null,
//             'is_correct' => $isCorrect,
//         ]);

//         // Chuẩn bị kết quả để trả về
//         $results[] = [
//             'question_id' => $questionId,
//             'question_title' => $questionData['title'],
//             'question_type' => $questionData['question_type'],
//             'selected_choice' => $selectedChoice ? [
//                 'id' => $selectedChoice['id'],
//                 'content' => $selectedChoice['content'],
//                 'is_correct' => $selectedChoice['is_correct']
//             ] : null,
//             'correct_choices' => $correctChoices->map(function($choice) {
//                 return [
//                     'id' => $choice['id'],
//                     'content' => $choice['content']
//                 ];
//             })->values(),
//             'all_choices' => collect($questionData['choices'])->map(function($choice) {
//                 return [
//                     'id' => $choice['id'],
//                     'content' => $choice['content'],
//                     'is_correct' => $choice['is_correct']
//                 ];
//             }),
//             'is_correct' => $isCorrect,
//             'explanation' => $isCorrect ? 'Chính xác!' : 'Sai rồi. Đáp án đúng: ' . $correctChoices->pluck('content')->implode(', ')
//         ];
//     }

//     // Tính phần trăm và xác định pass/fail
//     $percentage = $totalQuestions > 0 ? round(($totalScore / $totalQuestions) * 100, 2) : 0;
//     $isPassed = $percentage >= 80;

//     // Cập nhật quiz result
//     $quizResult->update([
//         'score' => $totalScore,
//         'completed_at' => now(),
//     ]);

//     return response()->json([
//         'message' => 'Nộp bài quiz thành công',
//         'data' => [
//             'quiz_result_id' => $quizResult->id,
//             'quiz_info' => [
//                 'id' => $snapshot['quiz']['id'],
//                 'title' => $snapshot['quiz']['title'],
//                 'total_questions' => $totalQuestions,
//                 'score' => $totalScore,
//                 'percentage' => $percentage,
//                 'is_passed' => $isPassed,
//                 'pass_threshold' => 80,
//                 'attempt_number' => $quizResult->attempt_number,
//                 'time_taken' => $quizResult->started_at->diffInMinutes($quizResult->completed_at),
//                 'completed_at' => $quizResult->completed_at,
//             ],
//             'results' => $results,
//             'summary' => [
//                 'total_questions' => $totalQuestions,
//                 'correct_answers' => $totalScore,
//                 'incorrect_answers' => $totalQuestions - $totalScore,
//                 'percentage' => $percentage,
//                 'status' => $isPassed ? 'PASSED' : 'FAILED',
//                 'message' => $isPassed ? 'Chúc mừng! Bạn đã vượt qua bài kiểm tra.' : 'Bạn cần đạt ít nhất 80% để vượt qua bài kiểm tra.'
//             ]
//         ]
//     ], 201);
// }

/**
 * Nộp bài quiz với kiểm tra thời gian và tính điểm từ snapshot.
 */
//Dùng snapshot
// public function submitQuiz(Request $request, $quiz_id): JsonResponse
// {
//     $user = Auth::user();

//     $validated = $this->validateQuizSubmission($request);

//     $quizResult = $this->findOngoingQuizResult($validated['quiz_result_id'], $quiz_id, $user->id);
//     if (!$quizResult) {
//         return response()->json(['message' => 'Không tìm thấy phiên làm bài hợp lệ'], 404);
//     }

//     $snapshot = $this->getSnapshotFromResult($quizResult);
//     if (!$snapshot) {
//         return response()->json(['message' => 'Dữ liệu quiz không hợp lệ'], 400);
//     }

//     if ($this->isTimeLimitExceeded($snapshot, $quizResult)) {
//         $quizResult->update([
//             'completed_at' => now(),
//             'score' => 0,
//         ]);
//         return response()->json(['message' => 'Đã vượt quá thời gian cho phép. Bài làm đã được tự động nộp với điểm 0.'], 403);
//     }

//     [$results, $totalScore] = $this->processAnswers($snapshot, $validated['answers'], $quizResult, $user->id);

//     $totalQuestions = count($snapshot['questions']);
//     $percentage = $totalQuestions > 0 ? round(($totalScore / $totalQuestions) * 100, 2) : 0;
//     $isPassed = $percentage >= 80;

//     $quizResult->update([
//         'score' => $totalScore,
//         'completed_at' => now(),
//     ]);

//     return response()->json([
//         'message' => 'Nộp bài quiz thành công',
//         'data' => [
//             'quiz_result_id' => $quizResult->id,
//             'quiz_info' => $this->buildQuizInfo($snapshot['quiz'], $quizResult, $totalQuestions, $totalScore, $percentage, $isPassed),
//             'results' => $results,
//             'summary' => $this->buildQuizSummary($totalQuestions, $totalScore, $percentage, $isPassed),
//         ]
//     ], 201);
// }

public function submitQuiz(Request $request, int $quizId): JsonResponse
{
    try {
        $user = Auth::user();
        $quiz = Quiz::with(['lesson', 'questions.choices'])->findOrFail($quizId);

        // Kiểm tra quyền truy cập quiz
        $accessCheck = $this->validateQuizAccess($user, $quiz);
        if ($accessCheck !== true) {
            return $accessCheck;
        }

        // Tìm QuizResult chưa hoàn thành
        $quizResult = QuizResult::where('user_id', $user->id)
            ->where('quiz_id', $quizId)
            ->whereNull('completed_at')
            ->first();

        if (!$quizResult) {
            return response()->json([
                'message' => 'Cant find ongoing quiz attempt or you have already submitted this quiz.'
            ], 400);
        }

        $questions = Question::where('quiz_id', $quiz->id)
            ->with('choices')
            ->get();

        // Chuyển đổi dữ liệu answers
        $rawAnswers = $request->input('answers');
        $answers = [];
        if (is_array($rawAnswers)) {
            foreach ($rawAnswers as $answer) {
                if (isset($answer['question_id'], $answer['choice_ids']) && is_array($answer['choice_ids'])) {
                    $answers[$answer['question_id']] = $answer['choice_ids'];
                }
            }
        }

        // Kiểm tra dữ liệu đầu vào answers
        $answersValidation = $this->validateQuizAnswers($answers, $questions);
        if ($answersValidation !== true) {
            return $answersValidation;
        }

        // Xử lý nộp bài
        return $this->processQuizSubmission($user, $quiz, $quizResult, $questions, $answers);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Quiz not found'
        ], 404);
    } catch (\Exception $e) {
        Log::error('Submit quiz error:', [
            'quiz_id' => $quizId,
            'user_id' => $user->id,
            'message' => $e->getMessage()
        ]);
        return response()->json([
            'message' => 'An error occurred while submitting the quiz',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Validate quiz access (visibility and enrollment)
     *
     * @param \App\Models\User $user
     * @param Quiz $quiz
     * @return bool|JsonResponse
     */
    private function validateQuizAccess($user, Quiz $quiz)
    {
        // Kiểm tra quiz có hiển thị không
        if (!$quiz->is_visible) {
            return response()->json(['message' => 'Quiz is unavailable at the moment'], 403);
        }

        // Kiểm tra đăng ký khóa học
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $quiz->lesson->course_id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        return true;
    }

   private function validateQuizAnswers($answers, $questions)
{
    if (!$answers || !is_array($answers)) {
        return response()->json(['message' => 'The answer is invalid'], 422);
    }

    $questionIds = $questions->pluck('id')->toArray();
    foreach ($questions as $question) {
        if (!isset($answers[$question->id]) || empty($answers[$question->id]) || !is_array($answers[$question->id])) {
            return response()->json([
                'message' => 'You have to choose at least one choice for each question',
                'question_id' => $question->id
            ], 422);
        }
        // Kiểm tra xem tất cả choice_id có hợp lệ không
        foreach ($answers[$question->id] as $choiceId) {
            if (!$question->choices->contains('id', $choiceId)) {
                return response()->json([
                    'message' => 'The choice is invalid for question',
                    'question_id' => $question->id,
                    'choice_id' => $choiceId
                ], 422);
            }
        }
    }

    // Kiểm tra xem có gửi câu trả lời cho câu hỏi không hợp lệ không
    foreach (array_keys($answers) as $questionId) {
        if (!in_array($questionId, $questionIds)) {
            return response()->json([
                'message' => 'The question is invalid for this quiz',
                'question_id' => $questionId
            ], 422);
        }
    }

    return true;
}

    /**
     * Process quiz submission (calculate score, save answers, update result)
     *
     * @param \App\Models\User $user
     * @param Quiz $quiz
     * @param QuizResult $quizResult
     * @param \Illuminate\Database\Eloquent\Collection $questions
     * @param array $answers
     * @return JsonResponse
     */
    private function processQuizSubmission($user, Quiz $quiz, QuizResult $quizResult, $questions, array $answers): JsonResponse
    {
        DB::beginTransaction();

        try {
            $correctCount = 0;
            $totalQuestions = $questions->count();
            $passThreshold = 80; // Giả định
            $results = [];

            foreach ($questions as $question) {
                $result = $this->buildQuestionResult($question, $answers[$question->id] ?? []);
                if ($result['is_correct']) {
                    $correctCount++;
                }
                $results[] = $result;

                // Lưu câu trả lời của người dùng
                foreach (collect($answers[$question->id]) as $choiceId) {
                    UserAnswer::create([
                        'quiz_result_id' => $quizResult->id,
                        'question_id' => $question->id,
                        'choice_id' => $choiceId,
                        'is_correct' => $question->choices->where('id', $choiceId)->first()->is_correct ?? false,
                    ]);
                }
            }

            // Tính điểm
            $score = $totalQuestions > 0 ? round(($correctCount / $totalQuestions) * 100, 2) : 0;
            $isPassed = $score >= $passThreshold;
            // Cập nhật QuizResult
            $quizResult->update([
                'score' => $score,
                'completed_at' => now(),
            ]);
            // Tính thời gian làm bài (phút)
            $timeTaken = $quizResult->started_at->diffInMinutes($quizResult->completed_at);

            DB::commit();

            return response()->json([
                'message' => 'Quiz submitted successfully',
                'data' => [
                    'quiz_result_id' => $quizResult->id,
                    'quiz_info' => [
                        'id' => $quiz->id,
                        'title' => $quiz->title,
                        'total_questions' => $totalQuestions,
                        'score' => $correctCount,
                        'percentage' => $score,
                        'is_passed' => $isPassed,
                        'pass_threshold' => $passThreshold,
                        'attempt_number' => $quizResult->attempt_number,
                        'time_taken' => $timeTaken,
                        'completed_at' => now()->toISOString(),
                    ],
                    'results' => $results,
                    'summary' => [
                        'total_questions' => $totalQuestions,
                        'correct_answers' => $correctCount,
                        'incorrect_answers' => $totalQuestions - $correctCount,
                        'percentage' => $score,
                        'status' => $isPassed ? 'PASSED' : 'FAILED',
                        'message' => $isPassed
                            ? "You have passed the quiz."
                            : "You need to have at least {$passThreshold}% to pass the quiz."
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Submit quiz error:', [
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'message' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'An error occurred while submitting the quiz',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build result data for a single question
     *
     * @param Question $question
     * @param array $submittedChoiceIds
     * @return array
     */
    private function buildQuestionResult(Question $question, array $submittedChoiceIds): array
    {
        $correctChoices = $question->choices->where('is_correct', 1)->values();
        $submittedChoiceIds = collect($submittedChoiceIds);

        // Kiểm tra đúng/sai
        $correctChoiceIds = $correctChoices->pluck('id')->sort()->values();
        $submittedSorted = $submittedChoiceIds->sort()->values();
        $isCorrect = $correctChoiceIds->toArray() === $submittedSorted->toArray();

        // Xây dựng dữ liệu lựa chọn đã chọn
        $selectedChoicesData = $question->choices
            ->whereIn('id', $submittedChoiceIds)
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'content' => $c->content,
                    'is_correct' => $c->is_correct
                ];
            })->values();

        $correctChoicesData = $correctChoices->map(function ($c) {
            return [
                'id' => $c->id,
                'content' => $c->content
            ];
        })->values();

        $allChoicesData = $question->choices->map(function ($c) {
            return [
                'id' => $c->id,
                'content' => $c->content,
                'is_correct' => $c->is_correct
            ];
        })->values();

        // Giải thích
        $explanation = $isCorrect
            ? "Exactly! You got it right."
            : "Oh no, the right answer is" . $correctChoices->pluck('content')->implode(', ');

        return [
            'question_id' => $question->id,
            'question_title' => $question->title,
            'question_type' => $question->question_type,
            'selected_choices' => $selectedChoicesData,
            'correct_choices' => $correctChoicesData,
            'all_choices' => $allChoicesData,
            'is_correct' => $isCorrect,
            'explanation' => $explanation
        ];
    }

private function validateQuizSubmission(Request $request): array
{
    return $request->validate([
        'quiz_result_id' => 'required|exists:quiz_results,id',
        'answers' => 'required|array',
        'answers.*.question_id' => 'required|integer',
        'answers.*.choice_ids' => 'required|array',
        'answers.*.choice_ids.*' => 'integer',
    ]);
}


private function findOngoingQuizResult(int $resultId, int $quizId, int $userId): ?QuizResult
{
    return QuizResult::where('id', $resultId)
        ->where('user_id', $userId)
        ->where('quiz_id', $quizId)
        ->whereNull('completed_at')
        ->first();
}

private function getSnapshotFromResult(QuizResult $quizResult): ?array
{
    return json_decode($quizResult->snapshot_json, true);
}

private function isTimeLimitExceeded(array $snapshot, QuizResult $quizResult): bool
{
    if (empty($snapshot['quiz']['time_limit'])) return false;

    $timeElapsed = \Carbon\Carbon::parse($quizResult->started_at)->diffInMinutes(now());
    return $timeElapsed > $snapshot['quiz']['time_limit'];
}
//Check chỉ có 1 đáp án đúng thôi
// private function processAnswers(array $snapshot, array $userAnswers, QuizResult $quizResult, int $userId): array
// {
//     $results = [];
//     $score = 0;

//     $questionsMap = collect($snapshot['questions'])->keyBy('id');
//     $answersMap = collect($userAnswers)->keyBy('question_id');

//     foreach ($snapshot['questions'] as $questionData) {
//         $questionId = $questionData['id'];
//         $userAnswer = $answersMap->get($questionId);
//         $selectedChoice = null;
//         $isCorrect = false;

//         if ($userAnswer && isset($userAnswer['choice_id'])) {
//             $selectedChoice = collect($questionData['choices'])->firstWhere('id', $userAnswer['choice_id']);
//             $isCorrect = $selectedChoice && $selectedChoice['is_correct'];
//             if ($isCorrect) $score++;
//         }

//         UserAnswer::create([
//             'user_id' => $userId,
//             'quiz_result_id' => $quizResult->id,
//             'question_index' => $questionId,
//             'choice_index' => $userAnswer['choice_id'] ?? null,
//             'is_correct' => $isCorrect,
//         ]);

//         $results[] = $this->buildResultItem($questionData, $selectedChoice, $isCorrect);
//     }

//     return [$results, $score];
// }

//check nhiều đáp án đúng
private function processAnswers(array $snapshot, array $userAnswers, QuizResult $quizResult, int $userId): array
{
    $results = [];
    $score = 0;

    $questionsMap = collect($snapshot['questions'])->keyBy('id');
    $answersMap = collect($userAnswers)->keyBy('question_id');

    foreach ($snapshot['questions'] as $questionData) {
        $questionId = $questionData['id'];
        $userAnswer = $answersMap->get($questionId);
        $selectedChoices = [];
        $isCorrect = false;

        if ($userAnswer && isset($userAnswer['choice_ids']) && is_array($userAnswer['choice_ids'])) {
            $correctChoices = collect($questionData['choices'])->filter(fn($c) => $c['is_correct'])->pluck('id')->sort()->values()->all();
            $userChoiceIds = collect($userAnswer['choice_ids'])->sort()->values()->all();

            $isCorrect = $userChoiceIds === $correctChoices;

            $selectedChoices = collect($questionData['choices'])
                ->whereIn('id', $userChoiceIds)
                ->values()
                ->all();

            if ($isCorrect) $score++;
        }

        foreach ($selectedChoices as $choice) {
            UserAnswer::create([
                'user_id' => $userId,
                'quiz_result_id' => $quizResult->id,
                'question_index' => $questionId,
                'choice_index' => $choice['id'],
                'is_correct' => $isCorrect,
            ]);
        }

        $results[] = $this->buildResultItem($questionData, $selectedChoices, $isCorrect);
    }

    return [$results, $score];
}

private function buildResultItem(array $questionData, array $selectedChoices, bool $isCorrect): array

{
    $correctChoices = collect($questionData['choices'])->where('is_correct', true);

    return [
        'question_id' => $questionData['id'],
        'question_title' => $questionData['title'],
        'question_type' => $questionData['question_type'],
'selected_choices' => collect($selectedChoices)->map(fn($c) => [
    'id' => $c['id'],
    'content' => $c['content'],
    'is_correct' => $c['is_correct'],
])->values(),
        'correct_choices' => $correctChoices->map(fn($c) => [
            'id' => $c['id'],
            'content' => $c['content']
        ])->values(),
        'all_choices' => collect($questionData['choices'])->map(fn($c) => [
            'id' => $c['id'],
            'content' => $c['content'],
            'is_correct' => $c['is_correct']
        ]),
        'is_correct' => $isCorrect,
        'explanation' => $isCorrect ? 'Exactly! My Friend !Congratulation' : 'Oh no, the right answer is: ' . $correctChoices->pluck('content')->implode(', ')
    ];
}

private function buildQuizInfo(array $quiz, QuizResult $quizResult, int $totalQuestions, int $score, float $percentage, bool $isPassed): array
{
    return [
        'id' => $quiz['id'],
        'title' => $quiz['title'],
        'total_questions' => $totalQuestions,
        'score' => $score,
        'percentage' => $percentage,
        'is_passed' => $isPassed,
        'pass_threshold' => 80,
        'attempt_number' => $quizResult->attempt_number,
        'time_taken' => $quizResult->started_at->diffInMinutes($quizResult->completed_at),
        'completed_at' => $quizResult->completed_at,
    ];
}

private function buildQuizSummary(int $totalQuestions, int $score, float $percentage, bool $isPassed): array
{
    return [
        'total_questions' => $totalQuestions,
        'correct_answers' => $score,
        'incorrect_answers' => $totalQuestions - $score,
        'percentage' => $percentage,
        'status' => $isPassed ? 'PASSED' : 'FAILED',
        'message' => $isPassed
            ? 'Congratulations! You have passed the quiz.'
            : 'You have fail to pass the quiz.'
    ];
}


     public function submitQuizForInstructor(Request $request, $quizId): JsonResponse
{
    $user = Auth::user();

    // Check if user is authenticated and has instructor role
    if (!$user || $user->role !== 'instructor') {
        return response()->json(['message' => 'Unauthorized. Only instructors can access this endpoint.'], 403);
    }

    // Find the quiz with its questions and choices
    $quiz = Quiz::with(['questions.choices', 'lesson.course'])->find($quizId);
    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }

    // Check if the instructor owns this course
    $instructor = Instructors::where('user_id', $user->id)->first();
    if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Validate answers
    $validated = $request->validate([
        'answers' => 'required|array',
        'answers.*.question_id' => 'required|exists:questions,id,quiz_id,' . $quizId,
        'answers.*.choice_id' => 'nullable|exists:question_choices,id',
    ]);

    // Process answers for preview only (không lưu database)
    $results = [];
    $totalScore = 0;
    $totalQuestions = count($validated['answers']);

    foreach ($validated['answers'] as $answer) {
        $question = $quiz->questions->find($answer['question_id']);
        $selectedChoice = null;
        $correctChoices = [];
        $isCorrect = false;

        if ($question) {
            // Lấy tất cả choices của question
            $allChoices = $question->choices;
            
            // Tìm choice được chọn
            if (isset($answer['choice_id'])) {
                $selectedChoice = $allChoices->find($answer['choice_id']);
                if ($selectedChoice && $selectedChoice->is_correct) {
                    $isCorrect = true;
                    $totalScore++;
                }
            }

            // Lấy tất cả đáp án đúng
            $correctChoices = $allChoices->where('is_correct', true)->values();

            $results[] = [
                'question_id' => $question->id,
                'question_title' => $question->title,
                'question_type' => $question->question_type,
                'selected_choice' => $selectedChoice ? [
                    'id' => $selectedChoice->id,
                    'content' => $selectedChoice->content,
                    'is_correct' => $selectedChoice->is_correct
                ] : null,
                'correct_choices' => $correctChoices->map(function($choice) {
                    return [
                        'id' => $choice->id,
                        'content' => $choice->content,
                        'is_correct' => $choice->is_correct
                    ];
                }),
                'all_choices' => $allChoices->map(function($choice) {
                    return [
                        'id' => $choice->id,
                        'content' => $choice->content,
                        'is_correct' => $choice->is_correct
                    ];
                }),
                'is_correct' => $isCorrect,
                'explanation' => $isCorrect ? 'Exactly!' : 'Wrong , the right answer is' . $correctChoices->pluck('content')->implode(', ')
            ];
        }
    }

    return response()->json([
        'message' => 'Test Quiz successfully',
        'data' => [
            'quiz_info' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'total_questions' => $totalQuestions,
                'score' => $totalScore,
                'percentage' => $totalQuestions > 0 ? round(($totalScore / $totalQuestions) * 100, 2) : 0
            ],
            'results' => $results
        ]
    ], 200);
}


    /**
     * Thử lại quiz với kiểm tra thời gian. cho student
     */
    public function retryQuiz(Request $request, $quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::with('questions.choices','lesson')->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        // Kiểm tra đăng ký hoặc quyền giảng viên
        $lesson = Lesson::find($quiz->lesson_id);
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->course_id)
            ->first();
        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        // Kiểm tra số lần làm bài
        $attempts = QuizResult::where('user_id', $user->id)
            ->where('quiz_id', $quiz_id)
            ->count();

        if ($attempts >= $quiz->max_attempts) {
            return response()->json(['message' => 'Out of amount to do this quiz'], 403);
        }

        // Validate câu trả lời
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id,quiz_id,' . $quiz_id,
            'answers.*.choice_id' => 'nullable|exists:question_choices,id',
        ]);

        // Bắt đầu lần làm bài
        $quizResult = QuizResult::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz_id,
            'attempt_number' => $attempts + 1,
            'started_at' => now(),
            'score' => 0,
        ]);

        // Kiểm tra thời gian tại thời điểm nộp bài
        if ($quiz->time_limit && $quizResult->started_at->diffInMinutes(now()) > $quiz->time_limit) {
            $quizResult->delete();
            return response()->json(['message' => 'You have passed the time to submit'], 403);
        }

        // Xử lý câu trả lời
        $totalScore = 0;
        foreach ($validated['answers'] as $answer) {
            $question = $quiz->questions->find($answer['question_id']);
            $isCorrect = null;

            if ($question->question_type === 'multiple_choice' || $question->question_type === 'true_false') {
                $choice = QuestionChoice::find($answer['choice_id']);
                if ($choice) {
                    $isCorrect = $choice->is_correct;
                    if ($isCorrect) {
                        $totalScore += 1;
                    }
                }
            }

            UserAnswer::create([
                'quiz_result_id' => $quizResult->id,
                'question_id' => $answer['question_id'],
                'choice_id' => $answer['choice_id'] ?? null,
                'is_correct' => $isCorrect,
            ]);
        }

        // Cập nhật kết quả quiz
        $quizResult->update([
            'score' => $totalScore,
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Try quiz again successfully',
            'data' => $quizResult->load('userAnswers'),
        ], 201);
    }

    public function indexQuestionsForInstructor($quiz_id): JsonResponse
{
    $user = Auth::user();
    $quiz = Quiz::with(['questions.choices','lesson.course'])->find($quiz_id);

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }
    // Check if instructor owns this course
    $instructor = Instructors::where('user_id', $user->id)->first();
    if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    return response()->json([
        'message' => 'Questions retrieved successfully',
        'data' => $quiz->questions
    ], 200);
}

public function storeQuestionForInstructor(Request $request, $quiz_id): JsonResponse
{
    $user = Auth::user();
    $quiz = Quiz::with(['lesson.course'])->find($quiz_id);

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }
    $validated = $request->validate([
        'title' => 'required|string',
        'question_type' => 'required|in:multiple_choice,true_false',
        'choices' => 'required_if:question_type,multiple_choice,true_false|array|min:2',
        'choices.*.content' => 'required|string',
        'choices.*.is_correct' => 'required|boolean',
    ]);

    $question = Question::create([
        'quiz_id' => $quiz_id,
        'title' => $validated['title'],
        'question_type' => $validated['question_type'],
    ]);

    if (in_array($validated['question_type'], ['multiple_choice', 'true_false'])) {
        foreach ($validated['choices'] as $index => $choice) {
            QuestionChoice::create([
                'question_id' => $question->id,
                'content' => $choice['content'],
                'is_correct' => $choice['is_correct']
            ]);
        }
    }

    return response()->json([
        'message' => 'Question created successfully',
        'data' => $question->load('choices')
    ], 201);
}

public function quizAnalyticsForInstructor($quiz_id): JsonResponse
{
    $user = Auth::user();
    $quiz = Quiz::with(['questions','lesson.course'])->find($quiz_id);

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }

     // Check if instructor owns this course
    $instructor = Instructors::where('user_id', $user->id)->first();
    if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $analytics = [];
    foreach ($quiz->questions as $question) {
        $answers = UserAnswer::where('question_id', $question->id)->get();
        $total = $answers->count();
        $correct = $answers->where('is_correct', true)->count();
        $analytics['questions'][$question->id] = [
            'title' => $question->title,
            'total_answers' => $total,
            'correct_answers' => $correct,
            'correct_rate' => $total ? round(($correct / $total) * 100, 2) : 0,
        ];
    }

    $scores = QuizResult::where('quiz_id', $quiz_id)->pluck('score')->toArray();
    $scoreDistribution = array_count_values($scores);

    return response()->json([
        'message' => 'Quiz analytics retrieved successfully',
        'data' => [
            'question_analytics' => $analytics['questions'],
            'score_distribution' => $scoreDistribution,
        ],
        'chart' => [
            'type' => 'chartjs',
            'data' => [
                'type' => 'bar',
                'data' => [
                    'labels' => array_keys($scoreDistribution),
                    'datasets' => [
                        [
                            'label' => 'Score Distribution',
                            'data' => array_values($scoreDistribution),
                            'backgroundColor' => '#4CAF50',
                            'borderColor' => '#388E3C',
                            'borderWidth' => 1,
                        ],
                    ],
                ],
                'options' => [
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'title' => ['display' => true, 'text' => 'Number of Students'],
                        ],
                        'x' => [
                            'title' => ['display' => true, 'text' => 'Score'],
                        ],
                    ],
                ],
            ],
        ],
    ], 200);
}

public function storeDraftQuiz(Request $request): JsonResponse
{
    $user = Auth::user();
    $validated = $request->validate([
        'lesson_id' => 'required|exists:lessons,id',
        'title' => 'required|string|max:255',
    ]);
    $lesson = Lesson::find($validated['lesson_id']);
            // Check if instructor owns this course
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor || $lesson->course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    $quiz = Quiz::create([
        'lesson_id' => $validated['lesson_id'],
        'title' => $validated['title'],
        'max_attempts' => 3,
        'is_visible' => false,
    ]);

    return response()->json([
        'message' => 'Draft quiz created successfully',
        'data' => $quiz
    ], 201);
}

public function restoreQuiz($id): JsonResponse
{
    $user = Auth::user();
    $quiz =Quiz::onlyTrashed()->with(['lesson.course'])->find($id);

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }

        // Check if instructor owns this course
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

    $quiz->restore();
    return response()->json([
        'message' => 'Quiz restored successfully',
        'data' => $quiz
    ], 200);
}

public function viewStudentAnswers($quiz_id, $quiz_result_id): JsonResponse
{
    $user = Auth::user();
    $quiz = Quiz::with(['lesson.course'])->find($quiz_id);

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }
     // Check if instructor owns this course
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    $quizResult = QuizResult::where('id', $quiz_result_id)
        ->where('quiz_id', $quiz_id)
        ->with(['userAnswers' => function ($query) {
            $query->with(['question' => function ($q) {
                $q->with(['choices' => function ($c) {
                    $c->where('is_correct', true);
                }]);
            }, 'choice']);
        }])
        ->first();

    if (!$quizResult) {
        return response()->json(['message' => 'Quiz result not found'], 404);
    }

    return response()->json([
        'message' => 'Student answers retrieved successfully',
        'data' => $quizResult->userAnswers
    ], 200);
}

//sap chép
public function reuseQuestions(Request $request, $quiz_id): JsonResponse
{
    $user = Auth::user();
    $quiz = Quiz::with(['lesson.course'])->find($quiz_id);

    if (!$quiz) {
        return response()->json(['message' => 'Quiz not found'], 404);
    }
    // Check if instructor owns this course
    $instructor = Instructors::where('user_id', $user->id)->first();
    if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'question_ids' => 'required|array',
        'question_ids.*' => 'exists:questions,id',
        'source_quiz_id' => 'nullable|exists:quizzes,id',
    ]);

    $newQuestions = [];
    foreach ($validated['question_ids'] as $question_id) {
        $originalQuestion = Question::with('choices')->find($question_id);
        if ($originalQuestion) {
            $newQuestion = $originalQuestion->replicate();
            $newQuestion->quiz_id = $quiz_id;
            $newQuestion->save();

            foreach ($originalQuestion->choices as $choice) {
                $newChoice = $choice->replicate();
                $newChoice->question_id = $newQuestion->id;
                $newChoice->save();
            }

            $newQuestions[] = $newQuestion->load('choices');
        }
    }

    return response()->json([
        'message' => 'Questions reused successfully',
        'data' => $newQuestions
    ], 201);
}

public function fullPreviewQuiz($quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::with([
            'lesson.course',
            'questions' => function ($query) {
                      $query->with('choices')
                      ->orderBy('id');
            }
        ])->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz không tìm thấy'], 404);
        }

        // Check if instructor owns this course
        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor || $quiz->lesson->course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'message' => 'Get quiz infor successfully',
            'data' => $quiz
        ], 200);
    }

    public function search(Request $request)
{
    $query = Quiz::query();

    if ($request->filled('lesson_id')) {
        $query->where('lesson_id', $request->lesson_id);
    }

    if ($request->filled('title')) {
        $query->where('title', 'like', '%' . $request->title . '%');
    }

    if ($request->filled('is_visible')) {
        $query->where('is_visible', $request->is_visible);
    }

    return response()->json($query->paginate(10));
}
// /**
//  * Lấy danh sách quiz theo lesson_id
//  *
//  * @param int $lessonId
//  * @return JsonResponse
//  */
// public function getQuizzesByLessonId($lessonId): JsonResponse
// {
//     try {
//         // Truy vấn các quiz theo lesson_id và đếm số lượng questions
//         $quizzes = Quiz::where('lesson_id', $lessonId)
//             ->withCount('questions')
//             ->get(['id', 'title', 'max_attempts', 'time_limit', 'is_visible', 'created_at', 'updated_at'])
//             ->map(function ($quiz) {
//                 return [
//                     'quiz_id' => $quiz->id,
//                     'title' => $quiz->title,
//                     'max_attempts' => $quiz->max_attempts,
//                     'time_limit' => $quiz->time_limit,
//                     'is_visible' => $quiz->is_visible,
//                     'questions_count' => $quiz->questions_count,
//                     'created_at' => $quiz->created_at,
//                     'updated_at' => $quiz->updated_at,
//                 ];
//             });

//         // Kiểm tra xem có quiz nào hay không
//         if ($quizzes->isEmpty()) {
//             return response()->json([
//                 'status' => 'success',
//                 'message' => 'No quizzes found for this lesson.',
//                 'data' => []
//             ], 200);
//         }

//         // Trả về danh sách quiz
//         return response()->json([
//             'status' => 'success',
//             'message' => 'Quizzes retrieved successfully.',
//             'data' => $quizzes
//         ], 200);

//     } catch (\Exception $e) {
//         // Xử lý lỗi nếu có
//         return response()->json([
//             'status' => 'error',
//             'message' => 'An error occurred while retrieving quizzes.',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

// /**
//  * Lấy danh sách quiz theo lesson_id
//  *
//  * @param int $lessonId
//  * @return JsonResponse
//  */
// public function getQuizzesByLessonId($lessonId): JsonResponse
// {
//     try {
//         $user = Auth::user();

//         // Truy vấn các quiz theo lesson_id và đếm số lượng questions
//         $quizzes = Quiz::where('lesson_id', $lessonId)
//             ->withCount('questions')
//             ->get(['id', 'title', 'max_attempts', 'time_limit', 'is_visible', 'created_at', 'updated_at'])
//             // ->map(function ($quiz) {
//             //     return [
//             //         'quiz_id' => $quiz->id,
//             //         'title' => $quiz->title,
//             //         'max_attempts' => $quiz->max_attempts,
//             //         'time_limit' => $quiz->time_limit,
//             //         'is_visible' => $quiz->is_visible,
//             //         'questions_count' => $quiz->questions_count,
//             //         'created_at' => $quiz->created_at,
//             //         'updated_at' => $quiz->updated_at,
//             //     ];
//             // });
//             ->map(function ($quiz) use ($user) {
//             // đếm số lần user đã làm
//             $userAttempts = $quiz->quizResults()
//                 ->where('user_id', $user->id)
//                 ->count();

//             $remainingAttempts = $quiz->max_attempts - $userAttempts;

//             // lấy kết quả chi tiết các lần làm
//             $results = $quiz->quizResults()
//                 ->where('user_id', $user->id)
//                 ->get();

//             return [
//                 'quiz_id'           => $quiz->id,
//                 'title'             => $quiz->title,
//                 'max_attempts'      => $quiz->max_attempts,
//                 'time_limit'        => $quiz->time_limit,
//                 'is_visible'        => $quiz->is_visible,
//                 'questions_count'   => $quiz->questions_count,
//                 'remaining_attempts'=> $remainingAttempts,
//                 'results'           => $results,
//                 'created_at'        => $quiz->created_at,
//                 'updated_at'        => $quiz->updated_at,
//             ];
//         });

//         // Kiểm tra xem có quiz nào hay không
//         if ($quizzes->isEmpty()) {
//             return response()->json([
//                 'status' => 'success',
//                 'message' => 'No quizzes found for this lesson.',
//                 'data' => []
//             ], 200);
//         }

//         // Trả về danh sách quiz
//         return response()->json([
//             'status' => 'success',
//             'message' => 'Quizzes retrieved successfully.',
//             'data' => $quizzes
//         ], 200);

//     } catch (\Exception $e) {
//         // Xử lý lỗi nếu có
//         return response()->json([
//             'status' => 'error',
//             'message' => 'An error occurred while retrieving quizzes.',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }
public function getQuizzesByLessonId($lessonId): JsonResponse
{
    try {
        $user = Auth::user();

        // Lấy lesson và course liên quan
        $lesson = Lesson::with('course')->find($lessonId);
        if (!$lesson) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lesson not found.',
                'data' => []
            ], 404);
        }

        $course = $lesson->course;
        $isOwner = false;
        if ($user->instructor && $course && $course->instructor_id == $user->instructor->id) {
            $isOwner = true;
        }

        $quizQuery = Quiz::where('lesson_id', $lessonId);
        if (!$isOwner) {
            $quizQuery->where('is_visible', 1);
        }

        $quizQuery->orderByRaw('COALESCE(origin_id, id), version');

        $quizzes = $quizQuery
            ->withCount('questions')
            ->get(['id', 'origin_id', 'version', 'title', 'max_attempts', 'time_limit', 'is_visible', 'created_at', 'updated_at']);

        // 👉 Truy xuất quiz_min_score nếu có từ bảng certificate_rules
        $certificateRule = CertificateRule::where('course_id', $course->id)->first();
        $quizMinScore = $certificateRule ? $certificateRule->quiz_min_score : 70;

        $results = $quizzes->map(function ($quiz) use ($user, $quizMinScore) {
            $userAttempts = $quiz->quizResults()
                ->where('user_id', $user->id)
                ->count();

            $remainingAttempts = $quiz->max_attempts - $userAttempts;

            $results = $quiz->quizResults()
                ->where('user_id', $user->id)
                ->get();

            return [
                'quiz_id'            => $quiz->id,
                'origin_id'          => $quiz->origin_id,
                'version'            => $quiz->version,
                'title'              => $quiz->title,
                'max_attempts'       => $quiz->max_attempts,
                'time_limit'         => $quiz->time_limit,
                'is_visible'         => $quiz->is_visible,
                'questions_count'    => $quiz->questions_count,
                'remaining_attempts' => $remainingAttempts,
                'results'            => $results,
                'quiz_min_score'     => (int)$quizMinScore, // ✅ Thêm ở đây
                'created_at'         => $quiz->created_at,
                'updated_at'         => $quiz->updated_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Quizzes retrieved successfully.',
            'data' => $results
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'An error occurred while retrieving quizzes.',
            'error' => $e->getMessage()
        ], 500);
    }
}

 public function getByLesson(Lesson $lesson)
    {
        // Lấy tất cả quiz thuộc lesson
        $quizzes = $lesson->quizzes()->get();

        return response()->json([
            'lesson_id' => $lesson->id,
            'quizzes' => $quizzes,
        ]);
    }
    // public function clone($id)
    // {
    //     try {
    //         $originalQuiz = Quiz::with('questions.choices')->findOrFail($id);

    //         // Step 1: Clone quiz
    //         $newQuiz = $originalQuiz->replicate();
    //         $newQuiz->is_visible = false;
    //         $newQuiz->created_at = now();
    //         $newQuiz->updated_at = now();
    //         $newQuiz->save();

    //         // Step 2: Clone questions and choices
    //         foreach ($originalQuiz->questions as $question) {
    //             $newQuestion = $question->replicate();
    //             $newQuestion->quiz_id = $newQuiz->id;
    //             $newQuestion->save();

    //             foreach ($question->choices as $choice) {
    //                 $newChoice = $choice->replicate();
    //                 $newChoice->question_id = $newQuestion->id;
    //                 $newChoice->save();
    //             }
    //         }


    //         return response()->json([
    //             'message' => 'Quiz cloned successfully',
    //             'quiz_id' => $newQuiz->id,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }
    public function clone($id)
{
    try {
        $originalQuiz = Quiz::with('questions.choices')->findOrFail($id);

        // Bước 1: Xác định origin
        $originId = $originalQuiz->origin_id ?? $originalQuiz->id;

        // Bước 2: Kiểm tra có phải version mới nhất không
        $maxVersion = Quiz::where('origin_id', $originId)
            ->orWhere('id', $originId)
            ->max('version');

        if ($originalQuiz->version < $maxVersion) {
            return response()->json([
                'error' => 'You can only clone the latest version of a quiz.',
                'latest_version' => $maxVersion,
                'your_version' => $originalQuiz->version
            ], 400);
        }

        // Bước 3: Tạo version mới
        $nextVersion = $maxVersion + 1;

        $newQuiz = $originalQuiz->replicate();
        $newQuiz->is_visible = false;
        $newQuiz->created_at = now();
        $newQuiz->updated_at = now();
        $newQuiz->origin_id = $originId;
        $newQuiz->version = $nextVersion;
        $newQuiz->save();

        // Bước 4: Clone question + choice
        foreach ($originalQuiz->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->quiz_id = $newQuiz->id;
            $newQuestion->save();

            foreach ($question->choices as $choice) {
                $newChoice = $choice->replicate();
                $newChoice->question_id = $newQuestion->id;
                $newChoice->save();
            }
        }   
        // $originalQuiz->update([
        //     'is_visible' => false, // Đặt quiz gốc thành không hiển thị
        // ]);
        return response()->json([
            'message' => 'Quiz cloned successfully.',
            'quiz_id' => $newQuiz->id,
            'origin_id' => $originId,
            'version' => $nextVersion
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
public function showAnalyticOfQuiz($quizId)
{
    $quiz = Quiz::with('lesson')->findOrFail($quizId);

    $questions = Question::where('quiz_id', $quizId)
        ->with(['choices'])
        ->get()
        ->map(function ($question) {
            $totalAnswers = $question->userAnswers()->count();

            $correctAnswers = $question->userAnswers()->where('is_correct', 1)->count();

            $incorrectAnswers = $totalAnswers - $correctAnswers;

            $choiceStats = $question->choices->map(function ($choice) use ($question, $totalAnswers) {
                $count = UserAnswer::where('question_id', $question->id)
                    ->where('choice_id', $choice->id)
                    ->count();

                $percent = $totalAnswers > 0 ? round($count * 100 / $totalAnswers, 2) : 0;

                return [
                    'choice_id' => $choice->id,
                    'content' => $choice->content,
                    'times_selected' => $count,
                    'percent_selected' => $percent,
                    'is_correct' => $choice->is_correct,
                ];
            });

            return [
                'question_id' => $question->id,
                'title' => $question->title,
                'total_answers' => $totalAnswers,
                'correct_answers' => $correctAnswers,
                'correct_percent' => $totalAnswers > 0 ? round($correctAnswers * 100 / $totalAnswers, 2) : 0,
                'incorrect_answers' => $incorrectAnswers,
                'incorrect_percent' => $totalAnswers > 0 ? round($incorrectAnswers * 100 / $totalAnswers, 2) : 0,
                'choices' => $choiceStats,
            ];
        });

    return response()->json([
        'quiz_id' => $quiz->id,
        'quiz_title' => $quiz->title,
        'lesson' => $quiz->lesson->title ?? null,
        'questions' => $questions,
    ]);
}
}