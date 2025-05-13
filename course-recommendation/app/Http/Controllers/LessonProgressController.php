<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonProgress\StoreLessonProgressRequest;
use App\Http\Requests\LessonProgress\UpdateLessonProgressRequest;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Course_Instructors;
use App\Models\Lesson;

class LessonProgressController extends Controller
{
    public function index(): JsonResponse
    {
        $lessonProgresses = LessonProgress::all();
        return response()->json(['data' => $lessonProgresses]);
    }

    public function show($id): JsonResponse
    {
        $lessonProgress = LessonProgress::findOrFail($id);
        return response()->json(['data' => $lessonProgress]);
    }

    public function store(StoreLessonProgressRequest $request): JsonResponse
    {
        $lessonProgress = LessonProgress::create($request->validated());
        return response()->json(['message' => 'Lesson progress created successfully', 'data' => $lessonProgress], 201);
    }

    public function update(UpdateLessonProgressRequest $request, $id): JsonResponse
    {
        $lessonProgress = LessonProgress::findOrFail($id);
        $lessonProgress->update($request->validated());
        return response()->json(['message' => 'Lesson progress updated successfully', 'data' => $lessonProgress]);
    }

    public function destroy($id): JsonResponse
    {
        $lessonProgress = LessonProgress::findOrFail($id);
        $lessonProgress->delete();
        return response()->json(['message' => 'Lesson progress deleted successfully']);
    }

      public function indexForStudent()
    {
        $user = Auth::user();
        $progress = LessonProgress::where('user_id', $user->id)->with('lesson')->get();
        return response()->json($progress, 200);
    }

    public function showForStudent($id)
    {
        $user = Auth::user();
        $progress = LessonProgress::where('id', $id)
            ->where('user_id', $user->id)
            ->with('lesson')
            ->first();

        if (!$progress) {
            return response()->json(['message' => 'Progress not found'], 404);
        }

        return response()->json($progress, 200);
    }

    public function indexForInstructor($course_id)
    {
        $user = Auth::user();
        $instructor = Course_Instructors::where('course_id', $course_id)
            ->whereHas('instructor', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$instructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
        }

        $progress = LessonProgress::whereHas('lesson', function ($query) use ($course_id) {
            $query->where('course_id', $course_id);
        })->with(['lesson', 'user'])->get();

        return response()->json($progress, 200);
    }
}