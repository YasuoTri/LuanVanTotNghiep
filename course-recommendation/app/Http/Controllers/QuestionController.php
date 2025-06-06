<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Course_Instructors;
use App\Models\QuestionChoice;
use App\Models\QuizResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuestionController extends Controller
{
    public function index()
    {
        $questions = Question::with('quiz')->paginate(10);
        return response()->json($questions);
    }


    public function show(Question $question)
    {
        return response()->json($question->load('choices'));
    }

public function store(StoreQuestionRequest $request)
{
    $quiz = Quiz::find($request->quiz_id);
    $user = Auth::user();

    if ($user->role === 'instructor') {
        $instructor = Course_Instructors::where('course_id', $quiz->lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    }

    $question = Question::create($request->validated());
    return response()->json($question, 201);
}

public function update(UpdateQuestionRequest $request, Question $question)
{
    $user = Auth::user();

    if ($user->role === 'instructor') {
        $instructor = Course_Instructors::where('course_id', $question->quiz->lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    }
    $question->fill($request->validated());
    if (!$question->isDirty()) {
        return response()->json(['message' => 'No changes detected'], 200);
    }
    $question->update($request->validated());
    return response()->json($question);
}

public function destroy(Question $question)
{
    $user = Auth::user();

    if ($user->role === 'instructor') {
        $instructor = Course_Instructors::where('course_id', $question->quiz->lesson->course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    }

    $question->delete();
    return response()->json(['message' => 'Deleted question successfully'], 201);
}

public function getChoicesByQuestionId($question_id)
{
    // Kiểm tra xem câu hỏi có tồn tại không
    $question = Question::find($question_id);

    if (!$question) {
        return response()->json(['message' => 'Question not found'], 404);
    }

    // Lấy danh sách các lựa chọn
    $choices = QuestionChoice::where('question_id', $question_id)
        ->orderBy('sort_order') // nếu muốn sắp xếp theo thứ tự hiển thị
        ->get();

    return response()->json([
        'question_id' => $question_id,
        'question_title' => $question->title,
        'choices' => $choices,
    ]);
}
   function search(Request $request) {
    return Question::query()
        ->when($request->filled('quiz_id'), fn($q) => $q->where('quiz_id', $request->input('quiz_id')))
        ->when($request->filled('title'), fn($q) => $q->where('title', 'like', "%{$request->input('title')}%"))
        ->when($request->filled('question_type'), fn($q) => $q->where('question_type', $request->input('question_type')))
        ->paginate(10);
}
}