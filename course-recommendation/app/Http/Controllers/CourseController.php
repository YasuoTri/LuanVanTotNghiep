<?php

namespace App\Http\Controllers;

use App\Http\Requests\Course\CreateCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Course_Instructors;
use App\Models\CourseReview;
use App\Models\Instructors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\CloudinaryService;

class CourseController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }
      public function index()
    {
        // Fetch only non-deleted, approved courses (SoftDeletes ensures deleted_at is null)
        $courses = Course::with(['instructors', 'reviews'])
            ->where('status', 'approved')
            ->paginate(10);
        return response()->json($courses);
    }

    public function getAllCoursesForAdmin()
    {
        try {
            // Fetch all courses, including soft-deleted ones
            $courses = Course::with(['instructors', 'reviews'])
                ->withTrashed() // Include soft-deleted courses
                ->paginate(10); // No pagination for admin view, or use paginate(10) if preferred
            return response()->json($courses, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch all courses for admin: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to fetch courses'], 500);
        }
    }
    public function getDeletedCoursesForAdmin()
    {
        try {
            // Check if user is admin
            if (!Auth::user()->admin) {
                return response()->json(['message' => 'Unauthorized: Admin access required'], 403);
            }

            // Fetch only soft-deleted courses
            $courses = Course::onlyTrashed()
                ->with(['instructors', 'reviews'])
                ->paginate(10); // Paginate for consistency with index
            return response()->json($courses, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch deleted courses: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to fetch deleted courses'], 500);
        }
    }

public function show($id)
{
    $course = Course::with(['instructors', 'reviews', 'lessons'])
        ->where('status', 'approved')
        ->findOrFail($id);
    return response()->json($course);
}
 public function showSlug($slug)
    {
        try {
            $course = Course::with(['instructors', 'reviews', 'lessons'])
                ->where('status', 'approved')
                ->where('course_url', $slug)
                ->firstOrFail();
            return response()->json($course);
        } catch (\Exception $e) {
            Log::error("Failed to fetch course with slug {$slug}: {$e->getMessage()}");
            return response()->json(['message' => 'Course not found'], 404);
        }
    }


  public function store(CreateCourseRequest $request)
    {
        try {
            $validated = $request->validated();
            $validated['course_url'] = Str::slug($validated['course_name']);

            if ($request->hasFile('image')) {
                $validated['image'] = $this->cloudinaryService->uploadImage($request->file('image'), 'courses');
            }

            $course = Course::create($validated);
            return response()->json($course, 201);
        } catch (\Exception $e) {
            Log::error("Failed to create course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to create course'], 500);
        }
    }

    public function update(UpdateCourseRequest $request, $id)
    {
        try {
            $course = Course::findOrFail($id);
            $validated = $request->validated();
            $validated['course_url'] = Str::slug($validated['course_name']);

            if ($request->hasFile('image')) {
                // Xóa hình ảnh cũ trên Cloudinary nếu có
                if ($course->image) {
                    $this->cloudinaryService->deleteByUrl($course->image);
                }
                $validated['image'] = $this->cloudinaryService->uploadImage($request->file('image'), 'courses');
            }

            $course->update($validated);
            return response()->json($course, 200);
        } catch (\Exception $e) {
            Log::error("Failed to update course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update course'], 500);
        }
    }
public function destroy($id)
    {
        try {
            $course = Course::findOrFail($id);
            if ($course->image) {
                $this->cloudinaryService->deleteByUrl($course->image);
            }
            $course->delete();
            return response()->json(['message' => 'Course soft deleted'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to delete course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to delete course'], 500);
        }
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
            $courses = $instructor->courses()->paginate(10);

            return response()->json($courses, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch instructor courses: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to fetch courses'], 500);
        }
    }
    public function indexAvailableCourseInstructor()
{
    try {
        $instructor = Auth::user()->instructor;
        $courses = $instructor->courses()->where('status', '!=', 'unavailable')->paginate(10);

        return response()->json($courses, 200);
    } catch (\Exception $e) {
        Log::error("Failed to fetch available instructor courses: {$e->getMessage()}");
        return response()->json(['message' => 'Failed to fetch available courses'], 500);
    }
}
   

// public function storeCourseInstructor(CreateCourseRequest $request)
// {
//     try {
//         $validated = $request->validated();

//         // Kiểm tra từ khóa cấm
//         $bannedWords = ['inappropriate', 'offensive'];
//         if (isset($validated['course_description']) &&
//             preg_match('/\b(' . implode('|', $bannedWords) . ')\b/i', $validated['course_description'])) {
//             return response()->json(['message' => 'Course description contains banned words'], 422);
//         }

//         $instructor = Auth::user()->instructor;

//         $course = Course::create($validated);

//         Course_Instructors::create([
//             'course_id' => $course->id,
//             'instructor_id' => $instructor->id,
//         ]);

//         return response()->json($course, 201);
//     } catch (\Exception $e) {
//         Log::error("Failed to create course: {$e->getMessage()}");
//         return response()->json(['message' => 'Failed to create course'], 500);
//     }
// }

public function storeCourseInstructor(CreateCourseRequest $request)
    {
        try {
            $validated = $request->validated();

            $bannedWords = ['inappropriate', 'offensive'];
            if (isset($validated['course_description']) &&
                preg_match('/\b(' . implode('|', $bannedWords) . ')\b/i', $validated['course_description'])) {
                return response()->json(['message' => 'Course description contains banned words'], 422);
            }

            $instructor = Auth::user()->instructor;
            $validated['course_url'] = Str::slug($validated['course_name']);

            if ($request->hasFile('image')) {
                $validated['image'] = $this->cloudinaryService->uploadImage($request->file('image'), 'courses');
            }

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

            $validated = $request->validated();
            $validated['course_url'] = Str::slug($validated['course_name']);
            $validated['status'] = 'pending';

            if ($request->hasFile('image')) {
                if ($course->image) {
                    $this->cloudinaryService->deleteByUrl($course->image);
                }
                $validated['image'] = $this->cloudinaryService->uploadImage($request->file('image'), 'courses');
            }

            $course->update($validated);
            return response()->json($course, 200);
        } catch (\Exception $e) {
            Log::error("Failed to update course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update course'], 500);
        }
    }

    // public function destroyCourseInstructor($id)
    // {
    //     try {
    //         $course = Course::find($id);
    //         if (!$course) {
    //             return response()->json(['message' => 'Course not found'], 404);
    //         }

    //         $instructor = Auth::user()->instructor;
    //         $courseInstructor = Course_Instructors::where('course_id', $id)
    //                                             ->where('instructor_id', $instructor->id)
    //                                             ->first();
    //         if (!$courseInstructor) {
    //             return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
    //         }

    //         $course->delete();
    //         return response()->json(['message' => 'Course deleted successfully'], 200);
    //     } catch (\Exception $e) {
    //         Log::error("Failed to delete course: {$e->getMessage()}");
    //         return response()->json(['message' => 'Failed to delete course'], 500);
    //     }
    // }
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

            if ($course->image) {
                $this->cloudinaryService->deleteByUrl($course->image);
            }

            $course->delete();
            return response()->json(['message' => 'Course deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to delete course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to delete course'], 500);
        }
    }
      public function makeCourseUnavailableInstructor($id)
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

            $course->update(['status' => 'unavailable']);
            return response()->json(['message' => 'Course marked as unavailable'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to update course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update course'], 500);
        }
    }
      public function makeCourseAvailableInstructor($id)
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

            $course->update(['status' => 'approved']);
            return response()->json(['message' => 'Course marked as available'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to update course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update course'], 500);
        }
    }
    public function getUnavailableCourses(Request $request)
    {
        // Lấy instructor dựa trên user_id của người dùng đang đăng nhập
        $instructor = Instructors::where('user_id', Auth::id())->first();

        if (!$instructor) {
            return response()->json([
                'message' => 'Instructor not found.'
            ], 404);
        }

        // Lấy các khóa học của instructor có status là unavailable
        $courses = Course::whereHas('instructors', function ($query) use ($instructor) {
            $query->where('instructor_id', $instructor->id);
        })
        ->where('status', 'unavailable')
        ->paginate(10);

        return response()->json([
            'message' => 'Unavailable courses retrieved successfully.',
            'data' => $courses
        ], 200);
    }
     public function getDeletedCoursesForInstructor()
    {
        try {
            $instructor = Auth::user()->instructor;
            if (!$instructor) {
                return response()->json(['message' => 'Unauthorized: Instructor access required'], 403);
            }

            $courses = Course::onlyTrashed()
                ->with(['instructors', 'reviews'])
                ->whereHas('instructors', function ($query) use ($instructor) {
                    $query->where('instructor_id', $instructor->id);
                })
                ->paginate(10);

            return response()->json($courses, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch deleted courses for instructor: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to fetch deleted courses'], 500);
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
    $courses = Course::where('status', 'pending')->paginate(10);
    return response()->json($courses);
}
     public function restoreCourse($id)
    {
        try {

            $course = Course::withTrashed()->find($id);
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }

            $course->restore();
            return response()->json(['message' => 'Course restored successfully'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to restore course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to restore course'], 500);
        }
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    // public function forceDelete($id): JsonResponse
    // {
    //     try{
    //     $enrollment = Course::onlyTrashed()->findOrFail($id);
    //     $enrollment->forceDelete();
    //     } catch (\Exception $e) {
    //         Log::error("Failed to permanently delete course: {$e->getMessage()}");
    //         return response()->json(['message' => 'Failed to permanently delete course'], 500);
    //     }
    //     return response()->json(['message' => 'Course permanently deleted'], 200);
    // }
    public function forceDelete($id): JsonResponse
    {
        try {
            $course = Course::onlyTrashed()->findOrFail($id);
            if ($course->image) {
                $this->cloudinaryService->deleteByUrl($course->image);
            }
            $course->forceDelete();
            return response()->json(['message' => 'Course permanently deleted'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to permanently delete course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to permanently delete course'], 500);
        }
    }
}