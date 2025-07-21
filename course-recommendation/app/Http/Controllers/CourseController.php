<?php

namespace App\Http\Controllers;

use App\Http\Requests\Course\CreateCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Mail\CourseApprovedMail;
use App\Mail\CourseRejectedMail;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Course_Instructors;
use App\Models\CourseCategory;
use App\Models\CourseReview;
use App\Models\Category;
use App\Models\Enrollment;
use App\Models\Instructors;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\Quiz;
use App\Models\Student;
use App\Models\User;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\CloudinaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CourseController extends Controller
{
    protected $cloudinaryService;
     protected $payPalService;

    public function __construct(CloudinaryService $cloudinaryService, PayPalService $payPalService)
    {
        $this->cloudinaryService = $cloudinaryService;
        $this->payPalService = $payPalService;
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
    $courses = Course::with(['instructors', 'reviews', 'lessons'])
        ->where('status', 'approved')
        ->has('lessons', '>', 1) // Chỉ lấy các khóa học có hơn 1 bài học
        ->get()
        ->map(function ($course) {
            return [
                'id' => $course->id,
                'course_name' => $course->course_name,
                'difficulty_level' => $course->difficulty_level,
                'course_rating' => $course->course_rating,
                'course_url' => $course->course_url,
                'image' => $course->image,
                'course_description' => $course->course_description,
                'price' => $course->price,
                'skills' => $course->skills,
                'status' => $course->status,
                'instructor' =>$course->instructors,
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
    return response()->json($paginatedCourses);
}

    public function getAllCoursesForAdmin()
    {
        try {
            // Fetch all courses, including soft-deleted ones
            $courses = Course::with(['instructors', 'reviews',])
                ->withTrashed() // Include soft-deleted courses
                ->paginate(10); // No pagination for admin view, or use paginate(10) if preferred
            $courses->loadCount(['lessons', 'reviews']);
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
            if (!Auth::user()->id) {
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
    $course = Course::with(['instructors', 'reviews', 'lessons', 'categories'])
        ->where('status', 'approved')
        ->findOrFail($id);
    return response()->json($course);
}

 public function showSlug($slug)
    {
        try {
            $course = Course::with(['instructors', 'reviews', 'reviews.user','lessons', 'categories','enrollments','instructors.user'])
                ->where('status', 'approved')
                ->where('course_url', $slug)
                ->firstOrFail();
            // Kiểm tra xem khóa học có tồn tại không
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }
            // Trả về thông tin khóa học
            $course->loadCount(['enrollments','reports']);
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
                        // Gán danh mục cho khóa học
            foreach ($validated['category_ids'] as $categoryId) {
                CourseCategory::create([
                    'course_id' => $course->id,
                    'category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // // Gán instructor cho khóa học
            // Course_Instructors::create([
            //     'course_id' => $course->id,
            //     'instructor_id' => $validated['instructor_id'],
            // ]);
        // Refresh model và load relationships
        $course = $course->fresh(['categories', 'instructors']);
        
        return response()->json($course, 200);
        } catch (\Exception $e) {
            Log::error("Failed to create course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to create course:'.$e->getMessage()], 500);
        }
    }

    public function update(UpdateCourseRequest $request, $id)
    {
        try {     
            $course = Course::findOrFail($id);
            $validated = $request->validated();
            $validated['course_url'] = Str::slug($validated['course_name']);
            $flag=false;
            // Xử lý upload hình ảnh
            if ($request->hasFile('image')) {
            $newFile = $request->file('image');

            // Nếu ảnh cũ tồn tại và giống ảnh mới thì bỏ qua upload
            if ($course->image && $this->isSameImage($course->image, $newFile)) {
                return response()->json(['message' => 'No changes detected in the image'], 200);
            } else {
                // Xóa ảnh cũ nếu có
                if ($course->image && str_contains($course->image, 'cloudinary.com')) {
                    $this->cloudinaryService->deleteByUrl($course->image);
                }
                $flag=true;
                // Upload ảnh mới
                $validated['image'] = $this->cloudinaryService->uploadImage($newFile, 'courses');
            }
            }
            
            $courseCategory = CourseCategory::where('course_id', $course->id)->pluck('category_id')->toArray();
            CourseCategory::where('course_id', $course->id)->delete(); // Xóa danh mục cũ
            foreach ($validated['category_ids'] as $categoryId) {
                CourseCategory::create([
                    'course_id' => $course->id,
                    'category_id' => $categoryId,
                    'updated_at' => now(),
                ]);
            }
            $courseCategoryCurrent=CourseCategory::where('course_id', $course->id)->pluck('category_id')->toArray();
            
            // // Gán instructor cho khóa học
            // Course_Instructors::where('course_id', $course->id)->delete(); // Xóa instructor cũ
            // Course_Instructors::create([
            //     'course_id' => $course->id,
            //     'instructor_id' => $validated['instructor_id'],
            // ]);
            $course->fill($validated);
            if(!$course->isDirty() && !$flag && $courseCategoryCurrent === $courseCategory) {          
                return response()->json(['message' => 'No changes detected'], 200);
            }
            $course->update($validated);
            $course->load('categories'); // Load categories for the response
            $course->load('instructors'); // Load instructors for the response
            return response()->json($course, 200);
        } catch (\Exception $e) {
            Log::error("Failed to update course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update course:'.$e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {            
            $course = Course::findOrFail($id);
              // Kiểm tra xem đã có ai đăng ký khóa học chưa
            $hasEnrollment = Enrollment::where('course_id', $course->id)->exists();

            if ($hasEnrollment) {
                return response()->json(['message' => 'Cannot delete course: There are existing enrollments'], 400);
            }
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
            $courses = $instructor->courses()
                ->with('coursereview', 'categories')
                ->withCount('lessons')
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc') // Sắp xếp thêm theo thời gian tạo (tùy chọn)
                ->get();
            return response()->json($courses, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch instructor courses: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to fetch courses'], 500);
        }
    }
//     public function indexCourseInstructor()
// {
//     try {
//         $instructor = Auth::user()->instructor;
//         $courses = $instructor->courses()
//             ->with('coursereview', 'categories')
//             ->orderByRaw("CASE WHEN status = 'draft' THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END")
//             ->orderBy('created_at', 'desc')
//             ->paginate(10);
//         return response()->json($courses, 200);
//     } catch (\Exception $e) {
//         Log::error("Failed to fetch instructor courses: {$e->getMessage()}");
//         return response()->json(['message' => 'Failed to fetch courses'], 500);
//     }
// }
//     public function indexAvailableCourseInstructor()
// {
//     try {
//         $instructor = Auth::user()->instructor;
//         $courses = $instructor->courses()->where('status', '!=', 'unavailable')->paginate(10);

//         return response()->json($courses, 200);
//     } catch (\Exception $e) {
//         Log::error("Failed to fetch available instructor courses: {$e->getMessage()}");
//         return response()->json(['message' => 'Failed to fetch available courses'], 500);
//     }
// }
public function indexAvailableCourseInstructor()
{
    try {
        $instructor = Auth::user()->instructor;
        $courses = $instructor->courses()
            ->whereNotIn('status', ['unavailable', 'draft', 'archived'])
            ->paginate(10);
        return response()->json($courses, 200);
    } catch (\Exception $e) {
        Log::error("Failed to fetch available instructor courses: {$e->getMessage()}");
        return response()->json(['message' => 'Failed to fetch available courses'], 500);
    }
}
     public function indexAvailableCourseInstructorGetBan()
{
    try {
        $instructor = Auth::user()->instructor;
        $courses = $instructor->courses()->where('status', '=', 'banned')->paginate(10);

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
            $user=Auth::user();
            $validated = $request->validated();
            $contains=Str::contains($validated['course_name'], ['  ','..']);
            if($contains){
                return response()->json(['message' => 'Course name cannot contain spaces'], 422);
            }
            if ($user->role === 'student' && !$user->instructor) {
                return response()->json([
                    'message' => 'Instructor profile not found. Please complete your instructor profile.',
                ], 200);
            }
            if($user->instructor->email_paypal==null){
                return response()->json([
                    'message' => 'Please complete your instructor profile. you need to add your paypal email.',
                ], 200);
            }
            // Check if user is not an instructor
            if ($user->role !== 'instructor') {
                return response()->json([
                    'message' => 'Only users with instructor role can create courses.'
                ], 403);
            }

            $validated = $request->validated();
            
            // Kiểm tra category_ids
            if (!isset($validated['category_ids']) || empty($validated['category_ids'])) {
                return response()->json(['message' => 'At least one category must be selected'], 422);
            }

            $instructor = Auth::user()->instructor;
            $validated['course_url'] = Str::slug($validated['course_name']);
            $validated['status'] = 'draft';

            // Xử lý upload hình ảnh
            if ($request->hasFile('image')) {
                $validated['image'] = $this->cloudinaryService->uploadImage($request->file('image'), 'courses');
                if (!$validated['image']) {
                    return response()->json(['message' => 'Image upload failed'], 422);
                }
                
            }
            $user=Auth::user()->instructor;
            // Tạo khóa học
            $course = Course::create([
                'course_name' => $validated['course_name'],
                'difficulty_level' => $validated['difficulty_level'] ?? null,
                'course_rating' => 0,
                'course_url' => $validated['course_url'],
                'image' => $validated['image'] ?? null,
                'course_description' => $validated['course_description'] ?? null,
                'price' => $validated['price'] ?? 0,
                'skills' => $validated['skills'] ?? null,
                'status' => $validated['status'],
                'is_certificate_enabled' => $validated['is_certificate_enabled'] ?? false,
                'instructor_id' => $user->id,
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
            // Course_Instructors::create([
            //     'course_id' => $course->id,
            //     'instructor_id' => $instructor->id,
            // ]);

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
// public function storeCourseInstructor(CreateCourseRequest $request)
// {
//     try {
//         $validated = $request->validated();
//         $courseNamededupplicate = Course::where('course_name', $validated['course_name'])->where('status', '!=', 'archived')
//            ->where('status', '!=', 'draft')
//             ->first();
//         if ($courseNamededupplicate) {
//             return response()->json(['message' => 'Course name already exists'], 422);
//         }
//         // Kiểm tra từ khóa cấm
//         $bannedWords = ['inappropriate', 'offensive'];
//         if (isset($validated['course_description']) &&
//             preg_match('/\b(' . implode('|', $bannedWords) . ')\b/i', $validated['course_description'])) {
//             return response()->json(['message' => 'Course description contains banned words'], 422);
//         }

//         // Kiểm tra category_ids
//         if (!isset($validated['category_ids']) || empty($validated['category_ids'])) {
//             return response()->json(['message' => 'At least one category must be selected'], 422);
//         }

//         $instructor = Auth::user()->instructor;
//         $validated['course_url'] = Str::slug($validated['course_name']);
//         $validated['status'] = 'draft'; // Khóa học mới ở trạng thái draft
//         $validated['version'] = 1; // Phiên bản đầu tiên

//         // Xử lý upload hình ảnh
//         if ($request->hasFile('image')) {
//             $validated['image'] = $this->cloudinaryService->uploadImage($request->file('image'), 'courses');
//             if (!$validated['image']) {
//                 return response()->json(['message' => 'Image upload failed'], 422);
//             }
//         }

//         // Tạo khóa học
//         $course = Course::create([
//             'course_name' => $validated['course_name'],
//             'university' => $validated['university'] ?? null,
//             'difficulty_level' => $validated['difficulty_level'] ?? null,
//             'course_rating' => 0,
//             'course_url' => $validated['course_url'],
//             'image' => $validated['image'] ?? null,
//             'course_description' => $validated['course_description'] ?? null,
//             'price' => $validated['price'] ?? 0,
//             'skills' => $validated['skills'] ?? null,
//             'status' => $validated['status'],
//             'version' => $validated['version'],
//             'origin_id' => null, // Khóa học mới không có origin
//         ]);

//         // Gán danh mục cho khóa học
//         foreach ($validated['category_ids'] as $categoryId) {
//             CourseCategory::create([
//                 'course_id' => $course->id,
//                 'category_id' => $categoryId,
//                 'created_at' => now(),
//                 'updated_at' => now(),
//             ]);
//         }

//         // Gán instructor cho khóa học
//         Course_Instructors::create([
//             'course_id' => $course->id,
//             'instructor_id' => $instructor->id,
//         ]);

//         return response()->json([
//             'success' => true,
//             'message' => 'Course created successfully as draft, pending admin review.',
//             'course' => $course->load('categories', 'instructors'),
//         ], 201);
//     } catch (\Exception $e) {
//         Log::error("Failed to create course: {$e->getMessage()}");
//         return response()->json(['message' => 'Failed to create course', 'error' => $e->getMessage()], 500);
//     }
// }

function isSameImage(string $cloudinaryUrl, \Illuminate\Http\UploadedFile $uploadedFile): bool
{
    try {
        // Tải ảnh Cloudinary tạm về
        $tempCloudinaryImage = tempnam(sys_get_temp_dir(), 'cloudinary_');
        file_put_contents($tempCloudinaryImage, file_get_contents($cloudinaryUrl));

        // Tính hash cả 2 ảnh
        $oldHash = md5_file($tempCloudinaryImage);
        $newHash = md5_file($uploadedFile->getRealPath());

        // Xóa file tạm
        unlink($tempCloudinaryImage);

        return $oldHash === $newHash;
    } catch (\Exception $e) {
        return false;
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
            $course = Course::findOrFail($id);

            if ($course->instructor_id !== $instructor->id) {
                return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
            }
            $hasEnrollment = Enrollment::where('course_id', $id)->exists();
            if ($hasEnrollment) {
                return response()->json(['message' => 'Cannot update course. There are students enrolled in this course.'], 403);
            }
                // Thay thế hai if cũ bằng 1 if duy nhất:
            if ($course->status !== 'pending' && $course->status !== 'draft') {
                return response()->json([
                    'message' => 'Cannot update course, its status must be draft or pending'
                ], 403);
            }

            $validated = $request->validated();
            if(isset($validated['course_name'])){
                $validated['course_url'] = Str::slug($validated['course_name']);
            }

            $flag=false;
            // Xử lý upload hình ảnh
            if ($request->hasFile('image')) {
            $newFile = $request->file('image');

            // Nếu ảnh cũ tồn tại và giống ảnh mới thì bỏ qua upload
            if ($course->image && $this->isSameImage($course->image, $newFile)) {
                return response()->json(['message' => 'No changes detected in the image'], 200);
            } else {
                // Xóa ảnh cũ nếu có
                if ($course->image && str_contains($course->image, 'cloudinary.com')) {
                    $this->cloudinaryService->deleteByUrl($course->image);
                }

                // Upload ảnh mới
                $validated['image'] = $this->cloudinaryService->uploadImage($newFile, 'courses');
            }
            }

            $courseCategory = CourseCategory::where('course_id', $course->id)->pluck('category_id')->toArray();
            Log::info("Current course categories before update: " . json_encode($courseCategory));
            // Cập nhật danh mục
            if(isset($validated['category_ids']) && is_array($validated['category_ids'])) {
                CourseCategory::where('course_id', $course->id)->delete(); // Xóa danh mục cũ
            foreach ($validated['category_ids'] as $categoryId) {
                CourseCategory::create([
                    'course_id' => $course->id,
                    'category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                }
            }
            $courseCategoryCurrent=CourseCategory::where('course_id', $course->id)->pluck('category_id')->toArray();
            // Cập nhật khóa học
            $course->fill($validated);        
            if((!$course->isDirty()) && $courseCategoryCurrent === $courseCategory&&!$flag) {          
                return response()->json(['message' => 'No changes detected'], 200);
            }

            $validated['status'] = 'draft'; // Cập nhật khóa học sẽ chuyển về trạng thái pending
            $course->update($validated);
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
// public function updateCourseInstructor(UpdateCourseRequest $request, $id)
// {
//     try {
//         $validated = $request->validated();
//         $courseNamededupplicate = Course::where('course_name', $validated['course_name'])->where('status', '!=', 'archived')
//            ->where('status', '!=', 'draft')
//             ->first();
//         if ($courseNamededupplicate) {
//             return response()->json(['message' => 'Course name already exists'], 422);
//         }
//         $originalCourse = Course::find($id);
//         if (!$originalCourse) {
//             return response()->json(['message' => 'Course not found'], 404);
//         }

//         $instructor = Auth::user()->instructor;
//         $courseInstructor = Course_Instructors::where('course_id', $id)
//             ->where('instructor_id', $instructor->id)
//             ->first();
//         if (!$courseInstructor) {
//             return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
//         }

//         $existingDraft = Course::where('origin_id', $originalCourse->id)
//             ->where('status', 'pending')
//             ->first();
//         if ($existingDraft) {
//             return response()->json(['message' => 'A draft version is already pending review'], 422);
//         }
        
//         $validated = $request->validated();
//         if (isset($validated['course_name'])) {
//             $validated['course_url'] = Str::slug($validated['course_name']);
//         } else {
//             $validated['course_url'] = $originalCourse->course_url;
//         }
//         $validated['status'] = 'draft';
//         $validated['version'] = $originalCourse->version + 1;
//         $validated['origin_id'] = $originalCourse->id;

//         // Xử lý hình ảnh
//         $imageUrl = $originalCourse->image;
//         if ($request->hasFile('image')) {
//             $newFile = $request->file('image');
//             if (!$originalCourse->image || !$this->isSameImage($originalCourse->image, $newFile)) {
//                 // Upload ảnh mới
//                 $imageUrl = $this->cloudinaryService->uploadImage($newFile, 'courses');
//             }
//         }
//         if($originalCourse->status=="draft"){
//             $originalCourse->update([
//             'course_name' => $validated['course_name'] ?? $originalCourse->course_name,
//             'university' => $validated['university'] ?? $originalCourse->university,
//             'difficulty_level' => $validated['difficulty_level'] ?? $originalCourse->difficulty_level,
//             'course_rating' => $originalCourse->course_rating,
//             'course_url' => $validated['course_url'],
//             'image' => $imageUrl,
//             'course_description' => $validated['course_description'] ?? $originalCourse->course_description,
//             'price' => $validated['price'] ?? $originalCourse->price,
//             'skills' => $validated['skills'] ?? $originalCourse->skills,
//             'status' => $validated['status']]);
//              // Gán danh mục
//         $categoryIds = $validated['category_ids'] ?? $originalCourse->categories->pluck('id')->toArray();
//         $originalCourse->categories()->detach();
//         foreach ($categoryIds as $categoryId) {   
//             CourseCategory::create([
//                 'course_id' => $originalCourse->id,
//                 'category_id' => $categoryId,
//                 'created_at' => now(),
//                 'updated_at' => now(),
//             ]);
//         }
//         $originalCourse->Course_Instructorss()->delete();
//         // Gán instructor
//         Course_Instructors::create([
//             'course_id' => $originalCourse->id,
//             'instructor_id' => $instructor->id,
//         ]);
//         return response()->json([
//             'success' => true,
//             'message' => 'Draft course created successfully, pending admin review.',
//             'course' => $originalCourse->load('categories', 'instructors'),
//         ], 200);
//         }else{
//         // Tạo bản sao khóa học
//         $newCourse = Course::create([
//             'course_name' => $validated['course_name'] ?? $originalCourse->course_name,
//             'university' => $validated['university'] ?? $originalCourse->university,
//             'difficulty_level' => $validated['difficulty_level'] ?? $originalCourse->difficulty_level,
//             'course_rating' => $originalCourse->course_rating,
//             'course_url' => $validated['course_url'],
//             'image' => $imageUrl,
//             'course_description' => $validated['course_description'] ?? $originalCourse->course_description,
//             'price' => $validated['price'] ?? $originalCourse->price,
//             'skills' => $validated['skills'] ?? $originalCourse->skills,
//             'status' => $validated['status'],
//             'version' => $validated['version'],
//             'origin_id' => $validated['origin_id'],
//         ]);
//          // Gán danh mục
//         $categoryIds = $validated['category_ids'] ?? $originalCourse->categories->pluck('id')->toArray();
//         foreach ($categoryIds as $categoryId) {
//             CourseCategory::create([
//                 'course_id' => $newCourse->id,
//                 'category_id' => $categoryId,
//                 'created_at' => now(),
//                 'updated_at' => now(),
//             ]);
//         }

//         // Gán instructor
//         Course_Instructors::create([
//             'course_id' => $newCourse->id,
//             'instructor_id' => $instructor->id,
//         ]);
//                 return response()->json([
//             'success' => true,
//             'message' => 'Draft course created successfully, pending admin review.',
//             'course' => $newCourse->load('categories', 'instructors'),
//         ], 200);
//     }

//     } catch (\Exception $e) {
//         Log::error("Failed to update course: {$e->getMessage()}");
//         return response()->json(['message' => 'Failed to update course', 'error' => $e->getMessage()], 500);
//     }
// }

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
//     public function destroyCourseInstructor($id)
//     {
//         try {
//             $course = Course::find($id);
//             if (!$course) {
//                 return response()->json(['message' => 'Course not found'], 404);
//             }

//             $instructor = Auth::user()->instructor;
//             $courseInstructor = Course_Instructors::where('course_id', $id)
//                 ->where('instructor_id', $instructor->id)
//                 ->first();
//             if (!$courseInstructor) {
//                 return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
//             }

//             if ($course->image) {
//                 $this->cloudinaryService->deleteByUrl($course->image);
//             }

//             $course->delete();
//             return response()->json(['message' => 'Course deleted successfully'], 200);
//         } catch (\Exception $e) {
//             Log::error("Failed to delete course: {$e->getMessage()}");
//             return response()->json(['message' => 'Failed to delete course'], 500);
//         }
//     }
   public function destroyCourseInstructor($id)
{
    try {
        $course = Course::find($id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        $instructor = Auth::user()->instructor;

        // Kiểm tra quyền instructor
        if ($course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized: You are not the owner of this course'], 403);
        }

        // Kiểm tra nếu có học viên đã đăng ký
        if (Enrollment::where('course_id', $course->id)->exists()) {
            return response()->json(['message' => 'Cannot delete course: There are students enrolled'], 400);
        }

        // Xóa ảnh nếu có
        if ($course->image) {
            $this->cloudinaryService->deleteByUrl($course->image);
        }

        // Soft delete
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
         $course = Course::findOrFail($id);

        if ($course->instructor_id !== $instructor->id) {
            return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
        }

            $course->update(['status' => 'unavailable']);
            return response()->json(['message' => 'Course marked as unavailable'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to update course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update course'], 500);
        }
    }
//     public function makeCourseUnavailableInstructor($id)
// {
//     try {
//         $course = Course::find($id);
//         if (!$course) {
//             return response()->json(['message' => 'Course not found'], 404);
//         }

//         if (in_array($course->status, ['draft', 'archived'])) {
//             return response()->json(['message' => 'Cannot modify draft or archived course'], 422);
//         }

//         $instructor = Auth::user()->instructor;
//         $courseInstructor = Course_Instructors::where('course_id', $id)
//             ->where('instructor_id', $instructor->id)
//             ->first();
//         if (!$courseInstructor) {
//             return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
//         }

//         $course->update(['status' => 'unavailable']);
//         return response()->json(['message' => 'Course marked as unavailable'], 200);
//     } catch (\Exception $e) {
//         Log::error("Failed to update course: {$e->getMessage()}");
//         return response()->json(['message' => 'Failed to update course'], 500);
//     }
// }
public function makeCourseAvailableInstructor($id)
    {
        try {
            $course = Course::find($id);
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }

            $instructor = Auth::user()->instructor;
          $course = Course::findOrFail($id);

if ($course->instructor_id !== $instructor->id) {
    return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
}


            $course->update(['status' => 'approved']);
            return response()->json(['message' => 'Course marked as available'], 200);
        } catch (\Exception $e) {
            Log::error("Failed to update course: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to update course'], 500);
        }
    }
// public function makeCourseAvailableInstructor($id)
// {
//     try {
//         $course = Course::find($id);
//         if (!$course) {
//             return response()->json(['message' => 'Course not found'], 404);
//         }

//         if (in_array($course->status, ['draft', 'archived'])) {
//             return response()->json(['message' => 'Cannot modify draft or archived course'], 422);
//         }

//         $instructor = Auth::user()->instructor;
//         $courseInstructor = Course_Instructors::where('course_id', $id)
//             ->where('instructor_id', $instructor->id)
//             ->first();
//         if (!$courseInstructor) {
//             return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
//         }

//         $course->update(['status' => 'approved']);
//         return response()->json(['message' => 'Course marked as available'], 200);
//     } catch (\Exception $e) {
//         Log::error("Failed to update course: {$e->getMessage()}");
//         return response()->json(['message' => 'Failed to update course'], 500);
//     }
// }
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
//     public function getUnavailableCourses(Request $request)
// {
//     try {
//         $instructor = Instructors::where('user_id', Auth::id())->first();
//         if (!$instructor) {
//             return response()->json(['message' => 'Instructor not found'], 404);
//         }

//         $courses = Course::whereHas('instructors', function ($query) use ($instructor) {
//             $query->where('instructor_id', $instructor->id);
//         })
//         ->where('status', 'unavailable')
//         ->paginate(10);

//         return response()->json([
//             'message' => 'Unavailable courses retrieved successfully.',
//             'data' => $courses
//         ], 200);
//     } catch (\Exception $e) {
//         Log::error("Failed to fetch unavailable courses: {$e->getMessage()}");
//         return response()->json(['message' => 'Failed to fetch unavailable courses'], 500);
//     }
// }
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
                ->get();

            return response()->json($courses, 200);
        } catch (\Exception $e) {
            Log::error("Failed to fetch deleted courses for instructor: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to fetch deleted courses'], 500);
        }
    }

public function approveCourse(Request $request, $id)
{
    $course = Course::findOrFail($id);
    if ($course->status !== 'pending') {
        return response()->json(['message' => 'Only pending courses can be approved'], 422);
    }
    $course->status = 'approved';
    $course->save();

    // Lưu lịch sử duyệt (nếu dùng bảng course_reviews)
    CourseReview::create([
        'course_id' => $course->id,
        'status' => 'approved',
        'user_id' => Auth::user()->id, // Giả sử admin_id là ID của người dùng đang đăng nhập
        'notes' => $request->input('notes'),
        'reviewed_at' => now(),
    ]);
     // Gửi mail instructor
    $instructors = $course->instructors;
    if ($instructors && $instructors->user) {
        Mail::to($instructors->user->email)
            ->send(new CourseApprovedMail($course));
    }
    return response()->json(['message' => 'Course approved successfully']);
}
// public function approveCourse(Request $request, $id)
// {
//     try {
//         $course = Course::findOrFail($id);
//         if ($course->status !== 'pending') {
//             return response()->json(['message' => 'Only pending courses can be approved'], 422);
//         }

//         // Tìm khóa học gốc
//         $originalCourse = Course::find($course->origin_id);
//         if ($originalCourse && $originalCourse->status === 'approved') {
//             // Chuyển khóa học gốc thành archived
//             $originalCourse->update(['status' => 'archived']);
//             $originalCourse->delete(); // Xóa khóa học gốc
//         }

//         // Cập nhật khóa học draft thành approved
//         $course->update(['status' => 'approved']);

//         // Lưu lịch sử duyệt
//         CourseReview::create([
//             'course_id' => $course->id,
//             'admin_id' => Auth::user()->admin->id,
//             'status' => 'approved',
//             'notes' => $request->input('notes'),
//         ]);
//         $Lesson=Lesson::where('course_id',$course->origin_id)->get();
//         foreach ($Lesson as $lesson) {
//             $lesson->course_id = $course->id; // Cập nhật ID khóa học cho các bài học
//             $lesson->save();
//         }
//         return response()->json(['message' => 'Course approved successfully']);
//     } catch (\Exception $e) {
//         Log::error("Failed to approve course: {$e->getMessage()}");
//         return response()->json(['message' => 'Failed to approve course'], 500);
//     }
// }

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
        'user_id'=>Auth::user()->id,
        'status' => 'rejected',
        'notes' => $request->notes,
        'reviewed_at' => now(),
    ]);
      // Gửi mail instructor
    $course->load('instructors.user');
    $instructor = $course->instructors;
    if ($instructor && $instructor->user) {
        Mail::to($instructor->user->email)
            ->send(new CourseRejectedMail($course, $request->notes));
    }
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
    public function submitCourseForReviewInstructor($id)
{
    try {
        // Tìm khóa học
        $course = Course::find($id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        // Kiểm tra trạng thái khóa học
        if ($course->status !== 'draft') {
            return response()->json(['message' => 'Only draft courses can be submitted for review'], 422);
        }

        // Kiểm tra quyền instructor
        $instructor = Auth::user()->instructor;
       $course = Course::findOrFail($id);

if ($course->instructor_id !== $instructor->id) {
    return response()->json(['message' => 'Unauthorized: Not assigned to this course'], 403);
}

        $course=Course::where("course_name",$course->course_name)->where('status', 'approved')->first();
        if ($course) {
            return response()->json(['message' => 'Course with this name already exists and is approved'], 422);
        }
        // Cập nhật trạng thái sang pending
        $course->update(['status' => 'pending']);

        // Ghi log hành động
        Log::info("Course ID {$id} submitted for review by instructor ID {$instructor->id}");

        return response()->json([
            'success' => true,
            'message' => 'Course submitted for admin review successfully.',
            'course' => $course->load('categories', 'instructors'),
        ], 200);
    } catch (\Exception $e) {
        Log::error("Failed to submit course for review: {$e->getMessage()}");
        return response()->json(['message' => 'Failed to submit course for review', 'error' => $e->getMessage()], 500);
    }
}
public function SearchCourse(Request $request){
    $results = Course::with(['instructors', 'categories'])
    ->where('status', 'approved')
    ->get()
    ->map(function ($course) {
        return [
            'id' => $course->id,
            'course_name' => $course->course_name,
            'course_url' => $course->course_url,
            'image' => $course->image,
            'category' => optional($course->categories->first())->name, // lấy category đầu tiên
            'instructor_name' => $course->instructors->user->fullname,
        ];
    });
    return response()->json($results);
}

public function searchCourseAdmin(Request $request)
{
    $query = Course::with(['instructors.user', 'categories'])
        ->when($request->q, function ($q) use ($request) {
            $keyword = $request->q;
            $q->where('course_name', 'like', "%{$keyword}%")
              ->orWhere('course_description', 'like', "%{$keyword}%")
              ->orWhereHas('instructors.user', function ($instructor) use ($keyword) {
                  $instructor->where('username', 'like', "%{$keyword}%");
              })
              ->orWhereHas('categories', function ($cat) use ($keyword) {
                  $cat->where('name', 'like', "%{$keyword}%");
              });
        })
        ->when($request->instructor_id, function ($q) use ($request) {
            $q->where('instructor_id', $request->instructor_id);
        })
        ->when($request->status, function ($q) use ($request) {
            $q->where('status', $request->status);
        })
        ->when($request->difficulty_level, function ($q) use ($request) {
            $q->where('difficulty_level', $request->difficulty_level);
        })
        ->when($request->price_min, function ($q) use ($request) {
            $q->where('price', '>=', $request->price_min);
        })
        ->when($request->price_max, function ($q) use ($request) {
            $q->where('price', '<=', $request->price_max);
        })
        ->when($request->is_certificate_enabled !== null, function ($q) use ($request) {
            $q->where('is_certificate_enabled', $request->is_certificate_enabled);
        })
        ->where('status', '!=', 'draft') // thêm dòng này để bỏ draft
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    return response()->json($query);
}

 public function courseClone(int $courseId): JsonResponse
    {
        $user = Auth::user();

        // Kiểm tra quyền của instructor
        $originalCourse = Course::findOrFail($courseId);
        if ($originalCourse->instructor_id !== $user->instructor->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Kiểm tra số lượng khóa học draft của instructor
        $draftCount = Course::where('instructor_id', $user->instructor->id)
            ->where('status', 'draft')
            ->count();

        if ($draftCount >= 100) {
            return response()->json([
                'message' => 'Cannot clone course. You have reached the limit of 100 draft courses.'
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Tạo tên khóa học mới và đảm bảo tính duy nhất
            $baseName = $originalCourse->course_name . ' (Clone)';
            $newCourseName = $baseName;
            $counter = 1;

            // Kiểm tra xem tên khóa học đã tồn tại chưa
            while (Course::where('course_name', $newCourseName)->exists()) {
                $newCourseName = $baseName . ' ' . $counter;
                $counter++;
            }

            // Clone course
            $clonedCourse = $originalCourse->replicate();
            $clonedCourse->status = 'draft';
            $clonedCourse->course_name = $newCourseName;
            $clonedCourse->course_rating = 0;
            $clonedCourse->save();

            // Clone lessons
            $originalLessons = Lesson::where('course_id', $originalCourse->id)->get();
            foreach ($originalLessons as $originalLesson) {
                $clonedLesson = $originalLesson->replicate();
                $clonedLesson->course_id = $clonedCourse->id;
                $clonedLesson->save();

                // Clone quizzes
                $originalQuizzes = Quiz::where('lesson_id', $originalLesson->id)->get();
                foreach ($originalQuizzes as $originalQuiz) {
                    $clonedQuiz = $originalQuiz->replicate();
                    $clonedQuiz->lesson_id = $clonedLesson->id;
                    $clonedQuiz->save();

                    // Clone questions
                    $originalQuestions = Question::where('quiz_id', $originalQuiz->id)->get();
                    foreach ($originalQuestions as $originalQuestion) {
                        $clonedQuestion = $originalQuestion->replicate();
                        $clonedQuestion->quiz_id = $clonedQuiz->id;
                        $clonedQuestion->save();

                        // Clone question choices
                        $originalChoices = QuestionChoice::where('question_id', $originalQuestion->id)->get();
                        foreach ($originalChoices as $originalChoice) {
                            $clonedChoice = $originalChoice->replicate();
                            $clonedChoice->question_id = $clonedQuestion->id;
                            $clonedChoice->save();
                        }
                    }
                }
            }

            DB::commit();

            // Load lại khóa học đã clone để trả về dữ liệu đầy đủ
            $clonedCourse = Course::findOrFail($clonedCourse->id);

            return response()->json([
                'message' => 'Course cloned successfully',
                'data' => $clonedCourse
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Course not found',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Course clone error:', [
                'course_id' => $courseId,
                'user_id' => Auth::id(),
                'message' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Clone failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
public function InstructorUpdateStatusToPending($course_id): JsonResponse
    {
        try {
            // Lấy user đang đăng nhập
            $user = Auth::user();

            // Kiểm tra xem user có tồn tại và có vai trò phù hợp không
            if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
                return response()->json([
                    'message' => 'Unauthorized. Only instructors or admins can update course status.'
                ], 403);
            }

            // Tìm khóa học
            $course = Course::findOrFail($course_id);

            // Nếu user là instructor, kiểm tra quyền sở hữu khóa học
            if ($user->role === 'instructor') {
                $instructor = Instructors::where('user_id', $user->id)->first();
                if (!$instructor || $course->instructor_id !== $instructor->id) {
                    return response()->json([
                        'message' => 'You are not the instructor for this course.'
                    ], 403);
                }
            }
            if ($course->status !== 'draft') {
                return response()->json([
                    'message' => 'Status is invalid,only draft can be updated to pending.'
                ], 422);
            }
            // Cập nhật status thành pending
            $course->update(['status' => 'pending']);

            // Tải lại khóa học để lấy dữ liệu mới nhất
            $updatedCourse = Course::findOrFail($course_id);

            return response()->json([
                'message' => 'Course status updated to pending successfully.',
                'data' => $updatedCourse
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Course not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Course status update error:', [
                'course_id' => $course_id,
                'user_id' => Auth::id(),
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'An error occurred while updating the course status.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
// * API: Lấy danh sách khóa học có rating cao
//      * Title: Lấy khóa học được đánh giá cao
   public function getHighRatedCourses(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $page = $request->query('page', 1);

    // Lấy course có rating cao, đã approved, có lesson
    $courses = Course::with(['instructors', 'reviews', 'lessons'])
        ->where('status', 'approved')
        ->where('course_rating', '>', 0)
        ->has('lessons', '>', 1)
        ->orderBy('course_rating', 'desc')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($course) {
            return [
                'id' => $course->id,
                'course_name' => $course->course_name,
                'difficulty_level' => $course->difficulty_level,
                'course_rating' => $course->course_rating,
                'course_url' => $course->course_url,
                'image' => $course->image,
                'course_description' => $course->course_description,
                'is_certificate_enabled' => $course->is_certificate_enabled,
                'price' => $course->price,
                'skills' => $course->skills,
                'status' => $course->status,
                'instructor' => $course->instructors,
                'category'=> $course->categories,
                'user' => $course->instructors->user,
                'total_lessons' => $course->lessons->count(),
                'total_time' => $course->lessons->sum('duration'),
                'number_of_ratings' => $course->reviews->count(),
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
            ];
        });

    // Phân trang thủ công
    $paginatedCourses = new \Illuminate\Pagination\LengthAwarePaginator(
        $courses->forPage($page, $perPage),
        $courses->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    return response()->json($paginatedCourses);
}

    // * API: Lấy danh sách khóa học đang có người học (active enrollments)
    //  * Title: Lấy khóa học đang có người học
public function getActiveCourses(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $page = $request->query('page', 1);

    // Lấy danh sách course có active enrollment
    $courseIds = Enrollment::whereNotNull('completed_at')
        ->pluck('course_id')
        ->unique();

    // Lấy chi tiết course + quan hệ (instructors, reviews, lessons)
    $courses = Course::with(['instructors', 'reviews', 'lessons'])
        ->where('status', 'approved')
        ->whereIn('id', $courseIds)
        ->get()
        ->map(function ($course) {
            return [
                'id' => $course->id,
                'course_name' => $course->course_name,
                'difficulty_level' => $course->difficulty_level,
                'course_rating' => $course->course_rating,
                'course_url' => $course->course_url,
                'image' => $course->image,
                'course_description' => $course->course_description,
                'is_certificate_enabled' => $course->is_certificate_enabled,
                'price' => $course->price,
                'skills' => $course->skills,
                'status' => $course->status,
                'instructor' => $course->instructors,
                'category'=> $course->categories,
                'user' => $course->instructors->user,
                'total_lessons' => $course->lessons->count(),
                'total_time' => $course->lessons->sum('duration'),
                'number_of_ratings' => $course->reviews->count(),
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
            ];
        });

    // Thực hiện phân trang thủ công vì đã map()
    $paginatedCourses = new \Illuminate\Pagination\LengthAwarePaginator(
        $courses->forPage($page, $perPage),
        $courses->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    return response()->json($paginatedCourses);
}

// * API: Lấy danh sách khóa học phổ biến (dựa trên số lượng đăng ký)
//      * Title: Lấy khóa học phổ biến
public function getPopularCourses(Request $request)
{
    $perPage = $request->query('per_page', 10);
    $page = $request->query('page', 1);

    // Lấy danh sách course cùng tổng số enrollments
    $popularCourses = Course::with(['instructors', 'reviews', 'lessons'])
        ->withCount(['enrollments as enrollments_count' => function ($query) {
            $query->whereNotNull('completed_at');
        }])
        ->where('status', 'approved')
        ->has('lessons', '>', 1)
        ->orderByDesc('enrollments_count')
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function ($course) {
            return [
                'id' => $course->id,
                'course_name' => $course->course_name,
                'difficulty_level' => $course->difficulty_level,
                'course_rating' => $course->course_rating,
                'course_url' => $course->course_url,
                'image' => $course->image,
                'course_description' => $course->course_description,
                'is_certificate_enabled' => $course->is_certificate_enabled,
                'price' => $course->price,
                'skills' => $course->skills,
                'status' => $course->status,
                'instructor' => $course->instructors,
                'category'=> $course->categories,
                'user' => $course->instructors->user,
                'total_lessons' => $course->lessons->count(),
                'total_time' => $course->lessons->sum('duration'),
                'number_of_ratings' => $course->reviews->count(),
                'enrollments_count' => $course->enrollments_count,
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
            ];
        });

    // Thực hiện phân trang thủ công
    $paginatedCourses = new \Illuminate\Pagination\LengthAwarePaginator(
        $popularCourses->forPage($page, $perPage),
        $popularCourses->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    return response()->json($paginatedCourses);
}

    
    //Danh sách khóa học mới nhất
  public function getCoursesByCriteria(Request $request)
{
    $criteria = $request->query('criteria', 'latest'); // Mặc định: mới nhất
    $perPage = $request->query('per_page', 10); // Số bản ghi mỗi trang
    $page = $request->query('page', 1); // Trang hiện tại

    // Query cơ bản
    $query = Course::with(['instructors', 'reviews', 'lessons'])
        ->where('status', 'approved')
        ->has('lessons', '>', 1);

    // Xử lý tiêu chí lọc
    switch ($criteria) {
        case 'price_low':
            $query->orderBy('price', 'asc');
            break;
        case 'price_high':
            $query->orderBy('price', 'desc');
            break;
        case 'latest':
        default:
            $query->orderBy('created_at', 'desc');
            break;
    }

    // Lấy kết quả phân trang
    $courses = $query->get()->map(function ($course) {
        return [
            'id' => $course->id,
            'course_name' => $course->course_name,
            'difficulty_level' => $course->difficulty_level,
            'course_rating' => $course->course_rating,
            'course_url' => $course->course_url,
            'image' => $course->image,
            'course_description' => $course->course_description,
            'price' => $course->price,
            'is_certificate_enabled' => $course->is_certificate_enabled,
            'skills' => $course->skills,
            'status' => $course->status,
            'instructor' => $course->instructors,
            'category'=> $course->categories,
            'user' => $course->instructors->user,
            'total_lessons' => $course->lessons->count(),
            'total_time' => $course->lessons->sum('duration'), // Tổng thời lượng
            'number_of_ratings' => $course->reviews->count(),
            'created_at' => $course->created_at,
            'updated_at' => $course->updated_at,
        ];
    });

    // Phân trang thủ công
    $paginatedCourses = new \Illuminate\Pagination\LengthAwarePaginator(
        $courses->forPage($page, $perPage),
        $courses->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    return response()->json($paginatedCourses);
}
public function getPopularCoursesInRandomCategory(Request $request)
{
    $perPage = $request->query('per_page', 10);

    $user = Auth::user();
    $student = Student::with('categories')->where('user_id', $user->id)->first();

    if (!$student || $student->categories->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'Student does not have any categories assigned.'
        ], 404);
    }

    // Lấy ngẫu nhiên 1 category từ danh sách category của sinh viên
    $category = $student->categories->random();

    // Lấy danh sách khóa học phổ biến trong danh mục này (phân trang SQL)
    $courses = Course::with(['instructors.user', 'reviews', 'lessons', 'categories'])
        ->whereHas('categories', function ($query) use ($category) {
            $query->where('categories.id', $category->id);
        })
        ->withCount(['enrollments as enrollments_count' => function ($q) {
            $q->whereNotNull('completed_at');
        }])
        ->where('status', 'approved')
        ->has('lessons', '>', 1)
        ->orderByDesc('enrollments_count')
        ->orderByDesc('created_at')
        ->paginate($perPage);

    // Map lại dữ liệu từng khóa học
    $courses->getCollection()->transform(function ($course) {
        return [
            'id' => $course->id,
            'course_name' => $course->course_name,
            'difficulty_level' => $course->difficulty_level,
            'course_rating' => $course->course_rating,
            'course_url' => $course->course_url,
            'image' => $course->image,
            'course_description' => $course->course_description,
            'is_certificate_enabled' => $course->is_certificate_enabled,
            'price' => $course->price,
            'skills' => $course->skills,
            'status' => $course->status,
            'instructor' => $course->instructors,
            'category' => $course->categories,
            'user' => $course->instructors->user ?? null,
            'total_lessons' => $course->lessons->count(),
            'total_time' => $course->lessons->sum('duration'),
            'number_of_ratings' => $course->reviews->count(),
            'enrollments_count' => $course->enrollments_count,
            'created_at' => $course->created_at,
            'updated_at' => $course->updated_at,
        ];
    });

    return response()->json([
        'success' => true,
        'message' => 'Popular courses in category "' . $category->name . '"',
        'category' => [
            'id' => $category->id,
            'name' => $category->name,
        ],
        'data' => $courses->items(),
        'meta' => [
            'current_page' => $courses->currentPage(),
            'last_page' => $courses->lastPage(),
            'per_page' => $courses->perPage(),
            'total' => $courses->total(),
        ]
    ]);
}



public function banAndRefundCourse($id)
{
    $admin = Auth::user();
    if (!$admin || $admin->role !== 'admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // Tìm course
    $course =Course::findOrFail($id);
    $course->status = 'unavailable';
    $course->save();

    // Tìm các enrollments
    $enrollments = Enrollment::where('course_id', $id)->get();
    $refunds = [];

    foreach ($enrollments as $enroll) {
        $payment = Payment::where('user_id', $enroll->user_id)
                        ->where('course_id', operator: $id)
                        ->where('status', 'completed')
                        ->first();

        if ($payment) {
            try {
                // Refund qua PayPalService
            $paypal = new PayPalService();
            $result=$paypal->refundTransaction($payment->transaction_code, $payment->amount);

                // Cập nhật payment
                $payment->status = 'refunded';
                $payment->save();

                // Ghi log
                AuditLog::create([
                    'payment_id' => $payment->id,
                    'action' => 'refunded',
                    'details' => json_encode($result),
                    'user_id' => $admin->id,
                ]);

                $refunds[] = [
                    'user_id' => $enroll->user_id,
                    'amount' => $payment->amount,
                    'status' => 'success'
                ];

            } catch (\Exception $e) {
                $refunds[] = [
                    'user_id' => $enroll->user_id,
                    'amount' => $payment->amount,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    return response()->json([
        'message' => 'Course banned and refund attempted',
        'refunds' => $refunds,
    ]);
}

public function banCourse($id)
{
    $admin = Auth::user();
    if (!$admin || $admin->role !== 'admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $course = Course::findOrFail($id);
    $course->status = 'banned';
    $course->save();

    return response()->json([
        'message' => 'Course banned successfully',
        'course_id' => $id
    ]);
}

public function unbanCourse($id)
{
    $admin = Auth::user();
    if (!$admin || $admin->role !== 'admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $course = Course::findOrFail($id);
    $course->status = 'approved';
    $course->save();

    return response()->json([
        'message' => 'Course unbanned successfully',
        'course_id' => $id
    ]);
}
public function refundCourse(Request $request, $id)
{
    $admin = Auth::user();
    if (!$admin || $admin->role !== 'admin') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $request->validate([
        'refund_amounts' => 'required|array', // ví dụ: [{"enrollment_id":123,"amount":100000}]
    ]);

    $refunds = [];
    foreach ($request->refund_amounts as $refund) {
        $enrollId = $refund['enrollment_id'];
        $amount = $refund['amount'];

        $enrollment = Enrollment::find($enrollId);
        if (!$enrollment || $enrollment->course_id != $id) {
            $refunds[] = [
                'enrollment_id' => $enrollId,
                'status' => 'failed',
                'error' => 'Enrollment not found or not part of this course'
            ];
            continue;
        }

        $payment = Payment::where('user_id', $enrollment->user_id)
                        ->where('course_id', $id)
                        ->where('status', 'completed')
                        ->first();

        if ($payment) {
            try {
                $paypal = new PayPalService();
                $result = $paypal->refundTransaction($payment->transaction_code, $amount);

                $payment->status = 'refunded';
                $payment->save();

                AuditLog::create([
                    'payment_id' => $payment->id,
                    'action' => 'refunded',
                    'details' => json_encode($result),
                    'user_id' => $admin->id,
                ]);

                $refunds[] = [
                    'enrollment_id' => $enrollId,
                    'amount' => $amount,
                    'status' => 'success'
                ];
            } catch (\Exception $e) {
                $refunds[] = [
                    'enrollment_id' => $enrollId,
                    'amount' => $amount,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    return response()->json([
        'message' => 'Refunds processed',
        'results' => $refunds
    ]);
}

public function refundCourseFromInstructor(Request $request): JsonResponse
    {
        // Xác thực dữ liệu đầu vào
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'payment_id' => 'required|exists:payments,id',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        // Kiểm tra quyền instructor
        if (!Auth::check() || Auth::user()->role !== 'instructor') {
            return response()->json([
                'message' => 'Unauthorized. Only instructors can initiate refunds.'
            ], 403);
        }

        // Tìm khóa học và kiểm tra trạng thái
        $course = Course::findOrFail($validated['course_id']);
        if ($course->status !== 'banned') {
            return response()->json([
                'message' => 'Cannot refund because the course is not banned.'
            ], 422);
        }

        // Kiểm tra instructor sở hữu khóa học
        $instructor = Instructors::where('user_id', Auth::id())->first();
        if (!$instructor || $course->instructor_id !== $instructor->id) {
            return response()->json([
                'message' => 'Unauthorized. You are not the instructor of this course.'
            ], 403);
        }

        // Tìm giao dịch thanh toán
        $payment = Payment::findOrFail($validated['payment_id']);
        if ($payment->course_id !== $course->id || $payment->status !== 'completed') {
            return response()->json([
                'message' => 'Invalid payment. Payment must belong to the course and be completed.'
            ], 422);
        }

        // Lấy email PayPal của instructor và email của học viên
        $instructorEmail = $instructor->email_paypal;
        $user = User::find($payment->user_id);
        if (!$instructorEmail || !$user->email) {
            return response()->json([
                'message' => 'Missing PayPal email for instructor or email for user.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Gửi Payout từ PayPalService
            $amount = number_format($payment->amount, 2, '.', ''); // Đảm bảo USD, 2 chữ số thập phân
            $note = $validated['admin_notes'] ?? 'Refund for banned course: ' . $course->course_name;
            $payoutResponse = $this->payPalService->sendPayout(
                $user->email, // Email của học viên
                $amount,
                'USD',
                $note
            );

            // Cập nhật trạng thái thanh toán
            $payment->update([
                'status' => 'refunded',
                'updated_at' => now(),
            ]);

            // Ghi log vào audit_logs
            AuditLog::create([
                'payment_id' => $payment->id,
                'action' => 'refunded',
                'details' => 'Refunded by instructor via PayPal Payouts: ' . $note,
                'user_id' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Refund processed successfully',
                'payment_id' => $payment->id,
                'course_id' => $course->id,
                'payout_batch_id' => $payoutResponse->result->batch_header->payout_batch_id,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process refund',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

     public function getCoursesByInstructor(Request $request): JsonResponse
    {
        try {
            $instructorId=Auth::user()->instructor->id;
            // Query courses for the instructor, excluding banned status
            $courses = Course::where('instructor_id',$instructorId )
                ->where('status', '!=', 'banned')
                ->select([
                    'id',
                    'course_name',
                    'difficulty_level',
                    'course_rating',
                    'course_url',
                    'image',
                    'course_description',
                    'price',
                    'skills',
                    'status',
                    'is_certificate_enabled',
                    'created_at',
                    'updated_at'
                ])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $courses,
                'message' => 'Courses retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while retrieving courses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function GetCourseHighestRevenu(){
        $courses=Course::all()->map(function($course){
            return [
                'course_id'=>$course->id,
                'course_name'=>$course->course_name,
                'revenue'=>$course->payments->sum('amount')
            ];
        })->filter(function($q){
            return $q['revenue']>0;
        })->sortByDesc('revenue') // <- FIX: dùng sortByDesc thay cho orderBy
        ->values()              // reset lại index sau khi sort
        ->take(10);
        return response()->json([
            'status'=>'success',
            'data'=>$courses,
            'message'=>'Courses retrieved successfully'
        ],200);
    }
}