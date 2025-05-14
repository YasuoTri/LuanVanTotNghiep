<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quiz\StoreQuizRequest;
use App\Http\Requests\Quiz\UpdateQuizRequest;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Lesson;
use App\Models\Enrollment;
use App\Models\Course_Instructors;
use App\Models\QuizResult;
use App\Models\UserAnswer;
use App\Models\QuestionChoice;
use App\Models\Question;

class QuizController extends Controller
{
    /**
     * Admin quiz.
     */
    public function index(): JsonResponse
    {
        $quizzes = Quiz::all();
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

    public function indexForInstructor()
    {
        $user = Auth::user();
        $quizzes = Quiz::whereHas('lesson.course.Course_Instructorss', function ($query) use ($user) {
            $query->whereHas('instructor', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        })->get();

        return response()->json($quizzes, 200);
    }

    public function showForInstructor($id)
    {
        $user = Auth::user();
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        $instructor = Course_Instructors::where('course_id', $quiz->lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
        }

        return response()->json($quiz, 200);
    }

    public function storeForInstructor(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'title' => 'required|string|max:255',
        ]);

        $lesson = Lesson::find($validated['lesson_id']);
        $instructor = Course_Instructors::where('course_id', $lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
        }

        $quiz = Quiz::create($validated);
        return response()->json($quiz, 201);
    }

    public function updateForInstructor(Request $request, $id)
    {
        $user = Auth::user();
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        $instructor = Course_Instructors::where('course_id', $quiz->lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $quiz->update($validated);
        return response()->json($quiz, 200);
    }

    public function destroyForInstructor($id)
    {
        $user = Auth::user();
        $quiz = Quiz::find($id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        $instructor = Course_Instructors::where('course_id', $quiz->lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
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
        $quiz = Quiz::find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        // Check if the user is an instructor for the course
        $instructor = Course_Instructors::where('course_id', $quiz->lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
        }

        // Get quiz results for all students
        $results = QuizResult::where('quiz_id', $quiz_id)
            ->with(['user' => function ($query) {
                $query->select('id', 'final_cc_cname_DI');
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

        // Check if the user is an instructor for the course
        $instructor = Course_Instructors::where('course_id', $quiz->lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
        }

        $validated = $request->validate([
            'max_attempts' => 'nullable|integer|min:1',
            'time_limit' => 'nullable|integer|min:1', // In minutes
            'is_visible' => 'nullable|boolean',
        ]);

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
        $quiz = Quiz::with('lesson')->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz not found'], 404);
        }

        // Check if the user is an instructor for the course
        $instructor = Course_Instructors::where('course_id', $quiz->lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
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
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        // Get all quizzes for the course
        $quizzes = Quiz::whereHas('lesson', function ($query) use ($course_id) {
            $query->where('course_id', $course_id);
        })->with(['lesson' => function ($query) {
            $query->select('id', 'course_id', 'title');
        }])->get();

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
            ->where('status', 'active')
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

    // Check if user is an instructor for the course
    $instructor = Course_Instructors::where('course_id', $userAnswer->question->quiz->lesson->course_id)
        ->whereHas('instructor', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->first();

    if (!$instructor && $user->role !== 'admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
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
        ->where('status', 'active')
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
        UserAnswer::updateOrCreate(
            [
                'user_id' => $user->id,
                'quiz_result_id' => $quizResult->id,
                'question_id' => $answer['question_id'],
            ],
            [
                'choice_id' => $answer['choice_id'] ?? null,
                'answer_text' => $answer['answer_text'] ?? null,
                'is_correct' => null,
                'points_earned' => null,
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
            return response()->json(['message' => 'Quiz không tìm thấy'], 404);
        }

        // Kiểm tra đăng ký khóa học
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $quiz->lesson->course_id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Bạn chưa đăng ký khóa học này'], 403);
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
            return response()->json(['message' => 'Không tìm thấy bản nháp'], 404);
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
            return response()->json(['message' => 'Quiz không tìm thấy'], 404);
        }

        // Kiểm tra đăng ký khóa học
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $quiz->lesson->course_id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Bạn chưa đăng ký khóa học này'], 403);
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
            return response()->json(['message' => 'Kết quả quiz không tìm thấy'], 404);
        }

        return response()->json([
            'message' => 'Lấy kết quả chi tiết thành công',
            'data' => $quizResult
        ], 200);
    }

    /**
     * Bắt đầu một phiên làm bài quiz.
     */
    public function startQuiz(Request $request, $quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz không tìm thấy'], 404);
        }

        // Kiểm tra đăng ký khóa học
        $lesson = Lesson::find($quiz->lesson_id);
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->course_id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Bạn chưa đăng ký khóa học này'], 403);
        }

        // Kiểm tra số lần làm bài
        $attempts = QuizResult::where('user_id', $user->id)
            ->where('quiz_id', $quiz_id)
            ->count();

        if ($attempts >= $quiz->max_attempts) {
            return response()->json(['message' => 'Đã đạt số lần làm bài tối đa'], 403);
        }

        // Kiểm tra quiz có hiển thị không
        if (!$quiz->is_visible) {
            return response()->json(['message' => 'Quiz hiện không khả dụng'], 403);
        }

        // Tạo QuizResult mới
        $quizResult = QuizResult::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz_id,
            'attempt_number' => $attempts + 1,
            'started_at' => now(),
            'score' => 0,
        ]);

        return response()->json([
            'message' => 'Bắt đầu quiz thành công',
            'data' => [
                'quiz_result_id' => $quizResult->id,
                'started_at' => $quizResult->started_at,
            ]
        ], 201);
    }

    /**
     * Lấy câu hỏi cho sinh viên, hỗ trợ random và giới hạn số lượng.
     */
    public function getQuestionsForStudent($quiz_id): JsonResponse
    {
        $user = Auth::user();
        $randomize = request()->query('randomize', false);
        $limit = request()->query('limit', null);
        $page = request()->query('page', 1);

        $quiz = Quiz::with(['questions' => function ($query) use ($randomize, $limit, $page) {
            $query->where('is_visible', true);
            if ($randomize) {
                $query->inRandomOrder();
            } else {
                $query->orderBy('sort_order');
            }
            if ($limit) {
                $query->forPage($page, $limit);
            }
            $query->with('choices');
        }])->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz không tìm thấy'], 404);
        }

        // Kiểm tra đăng ký khóa học
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $quiz->lesson->course_id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Bạn chưa đăng ký khóa học này'], 403);
        }

        return response()->json([
            'data' => $quiz->questions,
            'pagination' => $limit ? [
                'page' => (int)$page,
                'per_page' => (int)$limit,
                'total' => $quiz->questions()->where('is_visible', true)->count(),
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
            return response()->json(['message' => 'Quiz không tìm thấy'], 404);
        }

        // Kiểm tra đăng ký khóa học
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $quiz->lesson->course_id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Bạn chưa đăng ký khóa học này'], 403);
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

    /**
     * Nộp bài quiz với kiểm tra thời gian.
     */
    public function submitQuiz(Request $request, $quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::with('questions.choices')->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz không tìm thấy'], 404);
        }

        // Kiểm tra đăng ký hoặc quyền giảng viên
        $lesson = Lesson::find($quiz->lesson_id);
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->course_id)
            ->where('status', 'active')
            ->first();

        $instructor = Course_Instructors::where('course_id', $lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$enrollment && !$instructor) {
            return response()->json(['message' => 'Bạn không có quyền truy cập quiz này'], 403);
        }

        // Kiểm tra số lần làm bài
        $attempts = QuizResult::where('user_id', $user->id)
            ->where('quiz_id', $quiz_id)
            ->count();

        if ($attempts >= $quiz->max_attempts) {
            return response()->json(['message' => 'Đã đạt số lần làm bài tối đa'], 403);
        }

        // Validate câu trả lời
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id,quiz_id,' . $quiz_id,
            'answers.*.choice_id' => 'nullable|exists:question_choices,id',
            'answers.*.answer_text' => 'nullable|string',
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
            $quizResult->delete(); // Xóa lần làm bài không hợp lệ
            return response()->json(['message' => 'Đã vượt quá thời gian cho phép'], 403);
        }

        // Xử lý câu trả lời
        $totalScore = 0;
        foreach ($validated['answers'] as $answer) {
            $question = $quiz->questions->find($answer['question_id']);
            $isCorrect = null;
            $pointsEarned = 0;

            if ($question->question_type === 'multiple_choice' || $question->question_type === 'true_false') {
                $choice = QuestionChoice::find($answer['choice_id']);
                if ($choice) {
                    $isCorrect = $choice->is_correct;
                    $pointsEarned = $isCorrect ? $question->points : 0;
                }
            } elseif ($question->question_type === 'open_ended') {
                $isCorrect = null;
                $pointsEarned = null;
            }

            $totalScore += $pointsEarned ?? 0;

            UserAnswer::create([
                'user_id' => $user->id,
                'quiz_result_id' => $quizResult->id,
                'question_id' => $answer['question_id'],
                'choice_id' => $answer['choice_id'] ?? null,
                'answer_text' => $answer['answer_text'] ?? null,
                'is_correct' => $isCorrect,
                'points_earned' => $pointsEarned,
            ]);
        }

        // Cập nhật kết quả quiz
        $quizResult->update([
            'score' => $totalScore,
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Nộp bài quiz thành công',
            'data' => $quizResult->load('userAnswers'),
        ], 201);
    }

    /**
     * Thử lại quiz với kiểm tra thời gian.
     */
    public function retryQuiz(Request $request, $quiz_id): JsonResponse
    {
        $user = Auth::user();
        $quiz = Quiz::with('questions.choices')->find($quiz_id);

        if (!$quiz) {
            return response()->json(['message' => 'Quiz không tìm thấy'], 404);
        }

        // Kiểm tra đăng ký hoặc quyền giảng viên
        $lesson = Lesson::find($quiz->lesson_id);
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $lesson->course_id)
            ->where('status', 'active')
            ->first();

        $instructor = Course_Instructors::where('course_id', $lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$enrollment && !$instructor) {
            return response()->json(['message' => 'Bạn không có quyền truy cập quiz này'], 403);
        }

        // Kiểm tra số lần làm bài
        $attempts = QuizResult::where('user_id', $user->id)
            ->where('quiz_id', $quiz_id)
            ->count();

        if ($attempts >= $quiz->max_attempts) {
            return response()->json(['message' => 'Đã đạt số lần làm bài tối đa'], 403);
        }

        // Validate câu trả lời
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id,quiz_id,' . $quiz_id,
            'answers.*.choice_id' => 'nullable|exists:question_choices,id',
            'answers.*.answer_text' => 'nullable|string',
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
            return response()->json(['message' => 'Đã vượt quá thời gian cho phép'], 403);
        }

        // Xử lý câu trả lời
        $totalScore = 0;
        foreach ($validated['answers'] as $answer) {
            $question = $quiz->questions->find($answer['question_id']);
            $isCorrect = null;
            $pointsEarned = 0;

            if ($question->question_type === 'multiple_choice' || $question->question_type === 'true_false') {
                $choice = QuestionChoice::find($answer['choice_id']);
                if ($choice) {
                    $isCorrect = $choice->is_correct;
                    $pointsEarned = $isCorrect ? $question->points : 0;
                }
            } elseif ($question->question_type === 'open_ended') {
                $isCorrect = null;
                $pointsEarned = null;
            }

            $totalScore += $pointsEarned ?? 0;

            UserAnswer::create([
                'user_id' => $user->id,
                'quiz_result_id' => $quizResult->id,
                'question_id' => $answer['question_id'],
                'choice_id' => $answer['choice_id'] ?? null,
                'answer_text' => $answer['answer_text'] ?? null,
                'is_correct' => $isCorrect,
                'points_earned' => $pointsEarned,
            ]);
        }

        // Cập nhật kết quả quiz
        $quizResult->update([
            'score' => $totalScore,
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Thử lại quiz thành công',
            'data' => $quizResult->load('userAnswers'),
        ], 201);
    }
}