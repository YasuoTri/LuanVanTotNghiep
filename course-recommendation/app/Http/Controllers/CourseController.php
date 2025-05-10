<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Course_Instructors;
use App\Models\CourseReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CourseController extends Controller
{
    public function index()
{
    $courses = Course::with(['instructors', 'reviews'])
        ->where('status', 'approved')
        ->paginate(10); // giữ phân trang nếu cần
    return response()->json($courses);
}

public function show($id)
{
    $course = Course::with(['instructors', 'reviews', 'lessons'])
        ->where('status', 'approved')
        ->findOrFail($id);
    return response()->json($course);
}


    public function store(CreateCourseRequest $request)
    {
        $course = Course::create($request->validated());
        return response()->json($course, 201);
    }

    public function update(UpdateCourseRequest $request, $id)
    {
        $course = Course::findOrFail($id);
        $course->update($request->validated());
        return response()->json($course, 200);
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);
        $course->delete();
        return response()->json(['message' => 'Course deleted'], 200);
    }

    // Admin-specific APIs
    public function adminStats(Request $request, $id)
    {
        // if (!auth()->user()->is_admin) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        $course = Course::findOrFail($id);
        $stats = [
            'enrollments' => $course->enrollments()->count(),
            'average_rating' => $course->reviews()->avg('rating'),
            'certificates_issued' => $course->certificates()->count(),
            'forum_posts' => $course->forumPosts()->count(),
        ];

        return response()->json($stats, 200);
    }
    public function indexCourseInstructor()
    {
        try {
            $instructor = Auth::user()->instructor;
            $courses = $instructor->courses()->get();

            return response()->json($courses, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch instructor courses: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to fetch courses'], 500);
        }
    }

public function storeCourseInstructor(CreateCourseRequest $request)
{
    try {
        $validated = $request->validated();

        // Kiểm tra từ khóa cấm
        $bannedWords = ['inappropriate', 'offensive'];
        if (isset($validated['course_description']) &&
            preg_match('/\b(' . implode('|', $bannedWords) . ')\b/i', $validated['course_description'])) {
            return response()->json(['message' => 'Course description contains banned words'], 422);
        }

        $instructor = Auth::user()->instructor;

        $course = Course::create($validated);

        Course_Instructors::create([
            'course_id' => $course->id,
            'instructor_id' => $instructor->id,
        ]);

        return response()->json($course, 201);
    } catch (\Exception $e) {
        Log::error("Failed to create course: {$e->getMessage()}");
        return response()->json(['message' => 'Failed to create course'], 500);
    }
}


    public function updateCourseInstructor(UpdateCourseRequest $request, $id)
    {
        try {
           

            $course = Course::find($id);
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }

            $instructor = Auth::user()->instructor;
            $courseInstructor = Course_Instructors::where('course_id', $id)
                                                ->where('instructor_id', $instructor->id)
                                                ->first();
            if (!$courseInstructor) {
                return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
            }

            $course->update($request->validated());

            return response()->json($course, 200);
        } catch (\Exception $e) {
            Log::error("Failed to update course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update course'], 500);
        }
    }

    public function destroyCourseInstructor($id)
    {
        try {
            $course = Course::find($id);
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }

            $instructor = Auth::user()->instructor;
            $courseInstructor = Course_Instructors::where('course_id', $id)
                                                ->where('instructor_id', $instructor->id)
                                                ->first();
            if (!$courseInstructor) {
                return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
            }

            $course->delete();

            return response()->json(['message' => 'Course deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to delete course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to delete course'], 500);
        }
    }
    public function approveCourse(Request $request, $id)
{
    $course = Course::findOrFail($id);
    $course->status = 'approved';
    $course->save();

    // Lưu lịch sử duyệt (nếu dùng bảng course_reviews)
    CourseReview::create([
        'course_id' => $course->id,
        'admin_id' => Auth::user()->admin->id,
        'status' => 'approved',
        'notes' => $request->input('notes'),
    ]);

    return response()->json(['message' => 'Course approved successfully']);
}

public function rejectCourse(Request $request, $id)
{
    $request->validate([
        'notes' => 'required|string', // Bắt buộc có lý do từ chối
    ]);

    $course = Course::findOrFail($id);
    $course->status = 'rejected';
    $course->save();

    // Lưu lịch sử duyệt
    CourseReview::create([
        'course_id' => $course->id,
        'admin_id' => Auth::user()->admin->id,
        'status' => 'rejected',
        'notes' => $request->notes,
    ]);

    return response()->json(['message' => 'Course rejected', 'notes' => $request->notes]);
}

public function getPendingCourses()
{
    $courses = Course::where('status', 'pending')->get();
    return response()->json($courses);
}
    
}