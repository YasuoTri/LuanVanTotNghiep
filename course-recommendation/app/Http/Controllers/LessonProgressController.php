<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonProgress\StoreLessonProgressRequest;
use App\Http\Requests\LessonProgress\UpdateLessonProgressRequest;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Course_Instructors;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function index(): JsonResponse
    {
        $lessonProgresses = LessonProgress::paginate(10);
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
        $progress = LessonProgress::where('user_id', $user->id)->with('lesson')->paginate(10);
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
        })->with(['lesson', 'user'])->paginate(10);

        return response()->json($progress, 200);
    }

      public function updateProgress(Request $request, $lesson_id)
    {
        $request->validate([
            'status' => 'required|in:not_started,in_progress,completed',
        ]);

        $user_id = Auth::id();

        $progress = LessonProgress::updateOrCreate(
            ['user_id' => $user_id, 'lesson_id' => $lesson_id],
            [
                'status' => $request->status,
                'completed_at' => $request->status === 'completed' ? now() : null,
            ]
        );
        // Kiểm tra nếu tất cả bài học đã completed thì cập nhật enrollments
        $lesson = Lesson::findOrFail($lesson_id);
        $course_id = $lesson->course_id;

        $total = Lesson::where('course_id', $course_id)->count();
        $completed = LessonProgress::whereHas('lesson', function ($q) use ($course_id) {
        $q->where('course_id', $course_id);
        })->where('user_id', $user_id)->where('status', 'completed')->count();

        if ($total > 0 && $completed === $total) {
            Enrollment::where('user_id', $user_id)
            ->where('course_id', $course_id)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
        return response()->json(['message' => 'Progress updated', 'progress' => $progress]);
    }

    public function getProgressForCourse($course_id)
    {
        $user_id = Auth::id();

        $lessons = Lesson::where('course_id', $course_id)->get();

        $progressData = $lessons->map(function ($lesson) use ($user_id) {
            $progress = LessonProgress::where('lesson_id', $lesson->id)
                ->where('user_id', $user_id)->first();
            return [
                'lesson_id' => $lesson->id,
                'title' => $lesson->title,
                'status' => $progress->status ?? 'not_started',
                'completed_at' => $progress->completed_at??NUll,
            ];
        });

        return response()->json($progressData);
    }

    public function getCourseCompletion($course_id)
    {
        $user_id = Auth::id();

        $total = Lesson::where('course_id', $course_id)->count();

        $completed = LessonProgress::whereHas('lesson', function ($q) use ($course_id) {
            $q->where('course_id', $course_id);
        })->where('user_id', $user_id)->where('status', 'completed')->count();

        $percent = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        return response()->json([
            'completed_lessons' => $completed,
            'total_lessons' => $total,
            'progress_percent' => $percent
        ]);
    }
}