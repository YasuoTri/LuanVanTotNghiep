<?php

namespace App\Http\Controllers;

use App\Http\Requests\Course\CreateCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Course_Instructors;
use App\Models\CourseCategory;
use App\Models\CourseReview;
use App\Models\Category;
use App\Models\Instructors;
use App\Models\Student;
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
    //   public function index()
    // {
    //     // Fetch only non-deleted, approved courses (SoftDeletes ensures deleted_at is null)
    //     $courses = Course::with(['instructors', 'reviews'])
    //         ->where('status', 'approved')
    //         ->paginate(10);
    //     return response()->json($courses);
    // }
    public function index()
{
    // Fetch approved courses with related instructors and reviews
    $courses = Course::with(['instructor', 'reviews', 'lessons'])
        ->where('status', 'approved')
        ->get()
        ->map(function ($course) {
            return [
                'id' => $course->id,
                'course_name' => $course->course_name,
                'university' => $course->university,
                'difficulty_level' => $course->difficulty_level,
                'course_rating' => $course->course_rating,
                'course_url' => $course->course_url,
                'image' => $course->image,
                'course_description' => $course->course_description,
                'price' => $course->price,
                'skills' => $course->skills,
                'status' => $course->status,
                'instructor' =>$course->instructor,
                'total_lessons' => $course->lessons->count(),
                'total_time' => $course->lessons->sum('duration'), // Sum of lesson durations in minutes
                'number_of_ratings' => $course->reviews->count(),
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
            ];
        });

    // Fetch count of courses per category
    $categoryCourseCounts = Category::withCount('courses')
        ->get()
        ->map(function ($category) {
            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'course_count' => $category->courses_count,
            ];
        });

    // Paginate the courses manually (since we used get() for mapping)
    $perPage = 10;
    $page = request()->get('page', 1);
    $paginatedCourses = new \Illuminate\Pagination\LengthAwarePaginator(
        $courses->forPage($page, $perPage),
        $courses->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    return response()->json([
        'courses' => $paginatedCourses,
        'category_course_counts' => $categoryCourseCounts,
    ]);
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

            // Kiểm tra từ khóa cấm
            $bannedWords = ['inappropriate', 'offensive'];
            if (isset($validated['course_description']) &&
                preg_match('/\b(' . implode('|', $bannedWords) . ')\b/i', $validated['course_description'])) {
                return response()->json(['message' => 'Course description contains banned words'], 422);
            }

            // Kiểm tra category_ids
            if (!isset($validated['category_ids']) || empty($validated['category_ids'])) {
                return response()->json(['message' => 'At least one category must be selected'], 422);
            }

            $instructor = Auth::user()->instructor;
            $validated['course_url'] = Str::slug($validated['course_name']);
            $validated['status'] = 'pending'; // Khóa học mới tạo ở trạng thái pending

            // Xử lý upload hình ảnh
            if ($request->hasFile('image')) {
                $validated['image'] = $this->cloudinaryService->uploadImage($request->file('image'), 'courses');
            }

            // Tạo khóa học
            $course = Course::create([
                'course_name' => $validated['course_name'],
                'university' => $validated['university'] ?? null,
                'difficulty_level' => $validated['difficulty_level'] ?? null,
                'course_rating' => 0,
                'course_url' => $validated['course_url'],
                'image' => $validated['image'] ?? null,
                'course_description' => $validated['course_description'] ?? null,
                'price' => $validated['price'] ?? 0,
                'skills' => $validated['skills'] ?? null,
                'status' => $validated['status'],
            ]);

            // Gán danh mục cho khóa học
            foreach ($validated['category_ids'] as $categoryId) {
                CourseCategory::create([
                    'course_id' => $course->id,
                    'category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Gán instructor cho khóa học
            Course_Instructors::create([
                'course_id' => $course->id,
                'instructor_id' => $instructor->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Course created successfully, pending review.',
                'course' => $course->load('categories'),
            ], 201);
        } catch (\Exception $e) {
            Log::error("Failed to create course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to create course', 'error' => $e->getMessage()], 500);
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
            $validated['status'] = 'pending'; // Cập nhật khóa học sẽ chuyển về trạng thái pending

            // Kiểm tra category_ids
            if (!isset($validated['category_ids']) || empty($validated['category_ids'])) {
                return response()->json(['message' => 'At least one category must be selected'], 422);
            }

            // Xử lý upload hình ảnh
            if ($request->hasFile('image')) {
                if ($course->image) {
                    $this->cloudinaryService->deleteByUrl($course->image);
                }
                $validated['image'] = $this->cloudinaryService->uploadImage($request->file('image'), 'courses');
            }

            // Cập nhật khóa học
            $course->update([
                'course_name' => $validated['course_name'],
                'university' => $validated['university'] ?? $course->university,
                'difficulty_level' => $validated['difficulty_level'] ?? $course->difficulty_level,
                'course_url' => $validated['course_url'],
                'image' => $validated['image'] ?? $course->image,
                'course_description' => $validated['course_description'] ?? $course->course_description,
                'price' => $validated['price'] ?? $course->price,
                'skills' => $validated['skills'] ?? $course->skills,
                'status' => $validated['status'],
            ]);

            // Cập nhật danh mục
            CourseCategory::where('course_id', $course->id)->delete(); // Xóa danh mục cũ
            foreach ($validated['category_ids'] as $categoryId) {
                CourseCategory::create([
                    'course_id' => $course->id,
                    'category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Course updated successfully, pending review.',
                'course' => $course->load('categories'),
            ], 200);
        } catch (\Exception $e) {
            Log::error("Failed to update course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update course', 'error' => $e->getMessage()], 500);
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

      public function getCoursesByStudentCategories(Request $request)
    {
        try {
            // Lấy thông tin người dùng đã xác thực
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized or user not found',
                ], 401);
            }

            // Kiểm tra vai trò người dùng
            if ($user->role !== 'student') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only students can access this feature',
                ], 403);
            }

            // Tìm học viên dựa trên user_id
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found',
                ], 404);
            }

            // Lấy danh sách category_id mà học viên đã chọn
            $categoryIds = $student->categories()->pluck('categories.id')->toArray();
            if (empty($categoryIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No categories selected by the student',
                    'courses' => [],
                ], 200);
            }

            // Tìm các khóa học thuộc các danh mục đã chọn
            $courses = Course::with('categories','instructors')->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('categories.id', $categoryIds);
            })
            ->where('status', 'approved') // Chỉ lấy khóa học đã được phê duyệt
            ->select([
                'id',
                'course_name',
                'university',
                'difficulty_level',
                'course_rating',
                'course_url',
                'image',
                'course_description',
                'price',
                'skills',
                'status',
                'created_at',
                'updated_at'
            ])
            ->get();

            return response()->json([
                'success' => true,
                'message' => 'Courses retrieved successfully',
                'courses' => $courses,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving courses by student categories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving courses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}