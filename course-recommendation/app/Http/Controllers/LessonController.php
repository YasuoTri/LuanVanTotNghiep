<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lesson\StoreLessonRequest;
use App\Services\CloudinaryService;
use App\Http\Requests\Lesson\UpdateLessonRequest;
use App\Models\Lesson;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use App\Models\Course_Instructors;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use App\Models\Course;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LessonController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index(): JsonResponse
    {
        $lessons = Lesson::all();
        return response()->json(['data' => $lessons]);
    }

    public function show($id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);
        return response()->json(['data' => $lesson]);
    }

    public function store(StoreLessonRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Upload video to Cloudinary
            if ($request->hasFile('video')) {
                $data['video_url'] = $this->cloudinaryService->upload(
                    $request->file('video'),
                    'lessons',
                );
            }

            $lesson = Lesson::create($data);
            return response()->json(['message' => 'Lesson created successfully', 'data' => $lesson], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while creating the lesson.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateLessonRequest $request, $id)
    {
        $lesson = Lesson::findOrFail($id);
        Log::info('Lesson update data:', $request->all());
        $data = $request->validated();
Log::info('Lesson update data:', $data);
        // Update video if provided
        if ($request->hasFile('video')) {
            // Delete old video from Cloudinary if exists
            if ($lesson->video_url) {
                $this->cloudinaryService->deleteByUrl($lesson->video_url);
            }
            $data['video_url'] = $this->cloudinaryService->upload(
                $request->file('video'),
                'lessons'
            );
        }

        $lesson->update($data);
        return response()->json(['message' => 'Lesson updated successfully', 'data' => $lesson]);
    }

    public function destroy($id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);

        // Delete video from Cloudinary if exists
        if ($lesson->video_url) {
            $this->cloudinaryService->deleteByUrl($lesson->video_url);
        }

        $lesson->delete();
        return response()->json(['message' => 'Lesson deleted successfully']);
    }

    public function showForStudent($course_id, $lesson_id)
    {
        // Check if the student is enrolled in the course
        $user = Auth::user();
                $enrollmentCheck = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course_id)
            ->where('status', 'completed')
            ->first();
              if ($enrollmentCheck) {
            return response()->json(['message' => 'You was completed this course'], 403);
        }
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course_id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course'], 403);
        }

        $lesson = Lesson::where('id', $lesson_id)
            ->where('course_id', $course_id)
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        return response()->json($lesson, 200);
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

        $lessons = Lesson::where('course_id', $course_id)->get();
        return response()->json($lessons, 200);
    }

    public function showForInstructor($course_id, $lesson_id)
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

        $lesson = Lesson::where('id', $lesson_id)
            ->where('course_id', $course_id)
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Lesson not found'], 404);
        }

        return response()->json($lesson, 200);
    }

    public function storeForInstructor(StoreLessonRequest $request, $course_id)
    {
        try {
            $user = Auth::user();
            $instructor = Course_Instructors::where('course_id', $course_id)
                ->whereHas('instructor', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if (!$instructor) {
                return response()->json(['message' => 'You are not an instructor for this course'], 403);
            }

            $data = $request->validated();

            // Upload video to Cloudinary
            if ($request->hasFile('video')) {
                $data['video_url'] = $this->cloudinaryService->upload(
                    $request->file('video'),
                    'lessons',
                );
            }

            $lesson = Lesson::create(array_merge($data, ['course_id' => $course_id]));
            return response()->json(['message' => 'Lesson created successfully', 'data' => $lesson], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while creating the lesson.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateForInstructor(UpdateLessonRequest $request, $course_id, $lesson_id)
    {
        try {
            $user = Auth::user();
            $instructor = Course_Instructors::where('course_id', $course_id)
                ->whereHas('instructor', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if (!$instructor) {
                return response()->json(['message' => 'You are not an instructor for this course'], 403);
            }

            $lesson = Lesson::where('id', $lesson_id)
                ->where('course_id', $course_id)
                ->first();

            if (!$lesson) {
                return response()->json(['message' => 'Lesson not found'], 404);
            }

            $data = $request->validated();

            // Update video if provided
            if ($request->hasFile('video')) {
                // Delete old video from Cloudinary if exists
                if ($lesson->video_url) {
                    $this->cloudinaryService->deleteByUrl($lesson->video_url);
                }
                $data['video_url'] = $this->cloudinaryService->upload(
                    $request->file('video'),
                    'lessons'
                );
            }

            $lesson->update($data);
            return response()->json(['message' => 'Lesson updated successfully', 'data' => $lesson], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while updating the lesson.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroyForInstructor($course_id, $lesson_id)
    {
        try {
            $user = Auth::user();
            $instructor = Course_Instructors::where('course_id', $course_id)
                ->whereHas('instructor', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->first();

            if (!$instructor) {
                return response()->json(['message' => 'You are not an instructor for this course'], 403);
            }

            $lesson = Lesson::where('id', $lesson_id)
                ->where('course_id', $course_id)
                ->first();

            if (!$lesson) {
                return response()->json(['message' => 'Lesson not found'], 404);
            }

            // Delete video from Cloudinary if exists
            if ($lesson->video_url) {
                $this->cloudinaryService->deleteByUrl($lesson->video_url);
            }

            $lesson->delete();
            return response()->json(['message' => 'Lesson deleted successfully'], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while deleting the lesson.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
      public function getCourseLessons($id): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        // Kiểm tra enrollment có thuộc về student không
        $enrollment = Enrollment::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Lấy tất cả lesson thuộc khóa học
        $lessons = Lesson::where('course_id', $enrollment->course_id)
            ->leftJoin('lesson_progress', function ($join) use ($user) {
                $join->on('lessons.id', '=', 'lesson_progress.lesson_id')
                    ->where('lesson_progress.user_id', '=', $user->id);
            })
            ->select(
                'lessons.id',
                'lessons.title',
                'lessons.video_url',
                'lessons.duration',
                'lessons.is_preview',
                'lessons.sort_order',
                'lesson_progress.status as progress_status',
                'lesson_progress.completed_at'
            )
            ->orderBy('lessons.sort_order', 'asc')
            ->get();

        // Thêm thông tin khóa học vào phản hồi
        $course = Course::where('id', $enrollment->course_id)
            ->select('id', 'course_name', 'university')
            ->first();

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'course' => $course,
                'lessons' => $lessons
            ]
        ]);
    }
}