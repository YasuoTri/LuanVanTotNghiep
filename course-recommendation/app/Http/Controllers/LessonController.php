<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lesson\StoreLessonRequest;
use App\Http\Requests\Lesson\UpdateLessonRequest;
use App\Models\Admins;
use App\Models\Review;
use App\Services\CloudinaryService;
use App\Models\Lesson;
use App\Models\Course;
use App\Models\Course_Instructors;
use App\Models\Enrollment;
use App\Models\Instructors;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Exception;
use Illuminate\Http\Request;;

class LessonController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    public function index(): JsonResponse
    {
        $lessons = Lesson::paginate(10);
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

        // Kiểm tra trạng thái course
        $course = Course::findOrFail($data['course_id']);
        if ($course->status === 'rejected') {
            return response()->json(['error' => 'Cannot create lesson for rejected course'], 403);
        }

        // Xử lý chunked upload
        $receiver = new FileReceiver('video', $request, HandlerFactory::classFromRequest($request));

        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $save = $receiver->receive();

        if ($save->isFinished()) {
            // File hoàn chỉnh
            $file = $save->getFile();
            $fileName = $this->createFilename($file);

            // Lưu tạm file vào storage
            $disk = Storage::disk('local');
            $path = $disk->putFileAs('videos', $file, $fileName);

            // Tạo UploadedFile từ file tạm
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                storage_path('app/private/' . $path),
                $fileName,
                $file->getClientMimeType(),
                null,
                true
            );

            // Upload lên Cloudinary
            $apiResponse = $this->cloudinaryService->upload(
                $uploadedFile,
                'lessons/course_' . $data['course_id']
            );
            // Log::info('Cloudinary upload response:', ['response' => $apiResponse]);
            // return response()->json([
            //     'message' => 'Video uploaded successfully',
            //     'data' => $apiResponse
            // ], 201);
            $data['video_url'] = $apiResponse['secure_url'] ?? '';
            if (isset($apiResponse['duration'])) {
                $data['duration'] = round($apiResponse['duration'] / 60, 2); // Chuyển từ giây sang phút
            } else {
                // Nếu không có duration, ghi log lỗi hoặc đặt giá trị mặc định
                Log::warning('Cloudinary response missing duration', ['upload_result' => $apiResponse]);
                $data['duration'] = 0; // Hoặc xử lý khác tùy yêu cầu
            }
            // Xóa file tạm
            $disk->delete($path);

            // Lưu lesson với status=pending
            $data['status'] = 'pending';
            $lesson = Lesson::create($data);

            return response()->json([
                'message' => 'Lesson created successfully, awaiting approval',
                'data' => $lesson
            ], 201);
        }

        // Trả về tiến trình upload chunk
        $handler = $save->handler();
        return response()->json([
            'done' => $handler->getPercentageDone(),
            'status' => true
        ]);
    } catch (UploadMissingFileException $e) {
        return response()->json([
            'status' => 400,
            'error' => 'No file uploaded.'
        ], 400);
    } catch (Exception $e) {
        Log::error('Lesson creation error:', ['message' => $e->getMessage()]);
        return response()->json([
            'status' => 500,
            'error' => 'An error occurred while creating the lesson.',
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function update(UpdateLessonRequest $request, $id): JsonResponse
{
    try {
        $lesson = Lesson::findOrFail($id);
        $data = $request->validated();

        // Kiểm tra trạng thái course
        $course = Course::findOrFail($lesson->course_id);
        if ($course->status === 'rejected') {
            return response()->json(['error' => 'Cannot update lesson for rejected course'], 403);
        }

        // Xử lý chunked upload nếu có video mới
        if ($request->hasFile('video')) {
            $receiver = new FileReceiver('video', $request, HandlerFactory::classFromRequest($request));

            if ($receiver->isUploaded() === false) {
                throw new UploadMissingFileException();
            }

            $save = $receiver->receive();

            if ($save->isFinished()) {
                // File hoàn chỉnh
                $file = $save->getFile();
                $fileName = $this->createFilename($file);

                // Lưu tạm file
                $disk = Storage::disk('local');
                $path = $disk->putFileAs('videos', $file, $fileName);

                // Tạo UploadedFile từ file tạm
                $uploadedFile = new \Illuminate\Http\UploadedFile(
                     storage_path('app/private/' . $path),
                    $fileName,
                    $file->getClientMimeType(),
                    null,
                    true
                );

                // Xóa video cũ trên Cloudinary nếu có
                if ($lesson->video_url) {
                    $this->cloudinaryService->deleteByUrl($lesson->video_url);
                }

                // Upload lên Cloudinary
                // $data['video_url'] = $this->cloudinaryService->upload(
                //     $uploadedFile,
                //     'lessons/course_' . $lesson->course_id
                // );
                 // Upload lên Cloudinary
            $apiResponse = $this->cloudinaryService->upload(
                $uploadedFile,
                'lessons/course_' . $data['course_id']
            );
            // Log::info('Cloudinary upload response:', ['response' => $apiResponse]);
            // return response()->json([
            //     'message' => 'Video uploaded successfully',
            //     'data' => $apiResponse
            // ], 201);
            $data['video_url'] = $apiResponse['secure_url'] ?? '';
            if (isset($apiResponse['duration'])) {
                $data['duration'] = round($apiResponse['duration'] / 60, 2); // Chuyển từ giây sang phút
            } else {
                // Nếu không có duration, ghi log lỗi hoặc đặt giá trị mặc định
                Log::warning('Cloudinary response missing duration', ['upload_result' => $apiResponse]);
                $data['duration'] = 0; // Hoặc xử lý khác tùy yêu cầu
            }

                // Xóa file tạm
                $disk->delete($path);

                // Đặt status=pending khi có video mới
                $data['status'] = 'pending';
            } else {
                // Trả về tiến trình upload chunk
                $handler = $save->handler();
                return response()->json([
                    'done' => $handler->getPercentageDone(),
                    'status' => true
                ]);
            }
        }

        // Đặt status=pending nếu có thay đổi metadata quan trọng
        if (isset($data['title']) || isset($data['duration']) || isset($data['is_preview']) || isset($data['sort_order'])) {
            $data['status'] = 'pending';
        }

        $lesson->update($data);
        return response()->json([
            'message' => 'Lesson updated successfully' . (isset($data['status']) ? ', awaiting approval' : ''),
            'data' => $lesson
        ]);
    } catch (UploadMissingFileException $e) {
        return response()->json([
            'status' => 400,
            'error' => 'No file uploaded.'
        ], 400);
    } catch (Exception $e) {
        Log::error('Lesson update error:', ['message' => $e->getMessage()]);
        return response()->json([
            'status' => 500,
            'error' => 'An error occurred while updating the lesson.',
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function destroy($id): JsonResponse
    {
        try {
            $lesson = Lesson::findOrFail($id);

            // Xóa video trên Cloudinary nếu có
            if ($lesson->video_url) {
                $this->cloudinaryService->deleteByUrl($lesson->video_url);
            }

            $lesson->delete();
            return response()->json(['message' => 'Lesson deleted successfully']);
        } catch (Exception $e) {
            Log::error('Lesson deletion error:', ['message' => $e->getMessage()]);
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while deleting the lesson.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showForStudent($course_id, $lesson_id): JsonResponse
    {
        try {
            // Kiểm tra student đã enroll course
            $user = Auth::user();
            $enrollmentCheck = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course_id)
                ->where('status', 'completed')
                ->first();
            if ($enrollmentCheck) {
                return response()->json(['message' => 'You have completed this course'], 403);
            }
            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course_id)
                ->where('status', 'active')
                ->first();

            if (!$enrollment) {
                return response()->json(['message' => 'You are not enrolled in this course'], 403);
            }

            // Kiểm tra course đã approved
            $course = Course::findOrFail($course_id);
            if ($course->status !== 'approved') {
                return response()->json(['message' => 'Course is not approved yet'], 403);
            }

            // Chỉ trả về lesson đã approved
            $lesson = Lesson::where('id', $lesson_id)
                ->where('course_id', $course_id)
                ->where('status', 'approved')
                ->first();

            if (!$lesson) {
                return response()->json(['message' => 'Lesson not found or not approved'], 404);
            }

            return response()->json($lesson);
        } catch (Exception $e) {
            Log::error('Show lesson for student error:', ['message' => $e->getMessage()]);
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while retrieving the lesson.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function indexForInstructor($course_id): JsonResponse
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

            $lessons = Lesson::where('course_id', $course_id)->paginate(10);
            return response()->json($lessons);
        } catch (Exception $e) {
            Log::error('Index lessons for instructor error:', ['message' => $e->getMessage()]);
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while retrieving lessons.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function showForInstructor($course_id, $lesson_id): JsonResponse
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

            $lesson = Lesson::with(['quizzes','course'])->where('id', $lesson_id)
                ->where('course_id', $course_id)
                ->paginate(10);

            if (!$lesson) {
                return response()->json(['message' => 'Lesson not found'], 404);
            }

            return response()->json($lesson);
        } catch (Exception $e) {
            Log::error('Show lesson for instructor error:', ['message' => $e->getMessage()]);
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while retrieving the lesson.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function storeForInstructor(StoreLessonRequest $request, $course_id): JsonResponse
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

        // Kiểm tra trạng thái course
        $course = Course::findOrFail($course_id);
        if ($course->status === 'rejected') {
            return response()->json(['error' => 'Cannot create lesson for rejected course'], 403);
        }

        $data = $request->validated();
        $data['course_id'] = $course_id;

        // Xử lý chunked upload
        $receiver = new FileReceiver('video', $request, HandlerFactory::classFromRequest($request));

        if ($receiver->isUploaded() === false) {
            throw new UploadMissingFileException();
        }

        $save = $receiver->receive();

        if ($save->isFinished()) {
            // File hoàn chỉnh
            $file = $save->getFile();
            $fileName = $this->createFilename($file);

            // Lưu tạm file
            $disk = Storage::disk('local');
            $path = $disk->putFileAs('videos', $file, $fileName);

            // Tạo UploadedFile từ file tạm
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                storage_path('app/private/' . $path),
                $fileName,
                $file->getClientMimeType(),
                null,
                true
            );

            // Upload lên Cloudinary
            // $data['video_url'] = $this->cloudinaryService->upload(
            //     $uploadedFile,
            //     'lessons/course_' . $course_id
            // );
             // Upload lên Cloudinary
            $apiResponse = $this->cloudinaryService->upload(
                $uploadedFile,
                'lessons/course_' . $data['course_id']
            );
            // Log::info('Cloudinary upload response:', ['response' => $apiResponse]);
            // return response()->json([
            //     'message' => 'Video uploaded successfully',
            //     'data' => $apiResponse
            // ], 201);
            $data['video_url'] = $apiResponse['secure_url'] ?? '';
            if (isset($apiResponse['duration'])) {
                $data['duration'] = round($apiResponse['duration'] / 60, 2); // Chuyển từ giây sang phút
            } else {
                // Nếu không có duration, ghi log lỗi hoặc đặt giá trị mặc định
                Log::warning('Cloudinary response missing duration', ['upload_result' => $apiResponse]);
                $data['duration'] = 0; // Hoặc xử lý khác tùy yêu cầu
            }

            // Xóa file tạm
            $disk->delete($path);

            // Lưu lesson với status=pending
            $data['course_id'] = $course_id;
            $data['status'] = 'pending';
            $lesson = Lesson::create($data);

            return response()->json([
                'message' => 'Lesson created successfully, awaiting approval',
                'data' => $lesson
            ], 201);
        }

        // Trả về tiến trình upload chunk
        $handler = $save->handler();
        return response()->json([
            'done' => $handler->getPercentageDone(),
            'status' => true
        ]);
    } catch (UploadMissingFileException $e) {
        return response()->json([
            'status' => 400,
            'error' => 'No file uploaded.'
        ], 400);
    } catch (Exception $e) {
        Log::error('Lesson creation for instructor error:', ['message' => $e->getMessage()]);
        return response()->json([
            'status' => 500,
            'error' => 'An error occurred while creating the lesson.',
            'message' => $e->getMessage()
        ], 500);
    }
}
public function updateForInstructor(UpdateLessonRequest $request, $course_id, $lesson_id): JsonResponse
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

        // Kiểm tra trạng thái course
        $course = Course::findOrFail($course_id);
        if ($course->status === 'rejected') {
            return response()->json(['error' => 'Cannot update lesson for rejected course'], 403);
        }

        $data = $request->validated();
        $data['course_id'] = $course_id;

        // Xử lý chunked upload nếu có video mới
        if ($request->hasFile('video')) {
            $receiver = new FileReceiver('video', $request, HandlerFactory::classFromRequest($request));

            if ($receiver->isUploaded() === false) {
                throw new UploadMissingFileException();
            }

            $save = $receiver->receive();

            if ($save->isFinished()) {
                // File hoàn chỉnh
                $file = $save->getFile();
                $fileName = $this->createFilename($file);

                // Lưu tạm file
                $disk = Storage::disk('local');
                $path = $disk->putFileAs('videos', $file, $fileName);

                // Tạo UploadedFile từ file tạm
                $uploadedFile = new \Illuminate\Http\UploadedFile(
                 storage_path('app/private/' . $path),
                    $fileName,
                    $file->getClientMimeType(),
                    null,
                    true
                );

                // Xóa video cũ trên Cloudinary nếu có
                if ($lesson->video_url) {
                    $this->cloudinaryService->deleteByUrl($lesson->video_url);
                }

                // Upload lên Cloudinary
                // $data['video_url'] = $this->cloudinaryService->upload(
                //     $uploadedFile,
                //     'lessons/course_' . $course_id
                // );
                 // Upload lên Cloudinary
            $apiResponse = $this->cloudinaryService->upload(
                $uploadedFile,
                'lessons/course_' . $data['course_id']
            );
            // Log::info('Cloudinary upload response:', ['response' => $apiResponse]);
            // return response()->json([
            //     'message' => 'Video uploaded successfully',
            //     'data' => $apiResponse
            // ], 201);
            $data['video_url'] = $apiResponse['secure_url'] ?? '';
            if (isset($apiResponse['duration'])) {
                $data['duration'] = round($apiResponse['duration'] / 60, 2); // Chuyển từ giây sang phút
            } else {
                // Nếu không có duration, ghi log lỗi hoặc đặt giá trị mặc định
                Log::warning('Cloudinary response missing duration', ['upload_result' => $apiResponse]);
                $data['duration'] = 0; // Hoặc xử lý khác tùy yêu cầu
            }

                // Xóa file tạm
                $disk->delete($path);

                // Đặt status=pending khi có video mới
                $data['status'] = 'pending';
            } else {
                // Trả về tiến trình upload chunk
                $handler = $save->handler();
                return response()->json([
                    'done' => $handler->getPercentageDone(),
                    'status' => true
                ]);
            }
        }

        // Đặt status=pending nếu có thay đổi metadata quan trọng
        if (isset($data['title']) || isset($data['duration']) || isset($data['is_preview']) || isset($data['sort_order'])) {
            $data['status'] = 'pending';
        }

        $lesson->update($data);
        return response()->json([
            'message' => 'Lesson updated successfully' . (isset($data['status']) ? ', awaiting approval' : ''),
            'data' => $lesson
        ]);
    } catch (UploadMissingFileException $e) {
        return response()->json([
            'status' => 400,
            'error' => 'No file uploaded.'
        ], 400);
    } catch (Exception $e) {
        Log::error('Lesson update for instructor error:', ['message' => $e->getMessage()]);
        return response()->json([
            'status' => 500,
            'error' => 'An error occurred while updating the lesson.',
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function destroyForInstructor($course_id, $lesson_id): JsonResponse
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

            // Xóa video trên Cloudinary nếu có
            if ($lesson->video_url) {
                $this->cloudinaryService->deleteByUrl($lesson->video_url);
            }

            $lesson->delete();
            return response()->json(['message' => 'Lesson deleted successfully']);
        } catch (Exception $e) {
            Log::error('Lesson deletion for instructor error:', ['message' => $e->getMessage()]);
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while deleting the lesson.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

 public function getCourseLessons($id): JsonResponse
{
    try {
        $user = Auth::user();

        if ($user->role !== 'student') {
            return response()->json(['message' => 'Unauthorized: Only students can access this endpoint'], 403);
        }

        $enrollment = Enrollment::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $course = Course::where('id', $enrollment->course_id)
            ->where('status', 'approved')
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course is not approved yet'], 403);
        }
        $review= Review::where('course_id', $course->id)->get();
        $lessons = Lesson::where('course_id', $course->id)
            ->where('lessons.status', 'approved')
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
                'lessons.status as progress_status',
                'lesson_progress.completed_at'
            )
            ->orderBy('lessons.sort_order', 'asc')
            ->get();

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'course' => $course,
                'lessons' => $lessons,
                'reviews' => $review
            ]
        ]);
    } catch (Exception $e) {
        Log::error('Get course lessons error:', ['message' => $e->getMessage()]);
        return response()->json([
            'status' => 500,
            'error' => 'An error occurred while retrieving lessons.',
            'message' => $e->getMessage()
        ], 500);
    }
}


    public function approve($course_id, $lesson_id): JsonResponse
    {
        try {
            $lesson = Lesson::where('course_id', $course_id)->findOrFail($lesson_id);
            $lesson->update(['status' => 'approved']);

            // Kiểm tra xem tất cả lesson của course đã approved
            $course = Course::findOrFail($course_id);
            $allApproved = $course->lessons()->where('status', '!=', 'approved')->count() === 0;

            if ($allApproved) {
                $course->update(['status' => 'approved']);
            }

            return response()->json([
                'message' => 'Lesson approved successfully',
                'lesson' => $lesson,
                'course_status' => $course->status
            ]);
        } catch (Exception $e) {
            Log::error('Lesson approval error:', ['message' => $e->getMessage()]);
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while approving the lesson.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function reject($course_id, $lesson_id): JsonResponse
    {
        try {
            $lesson = Lesson::where('course_id', $course_id)->findOrFail($lesson_id);
            $lesson->update(['status' => 'rejected']);

            // Cập nhật course thành rejected nếu có lesson bị từ chối
            $course = Course::findOrFail($course_id);
            $course->update(['status' => 'rejected']);

            return response()->json([
                'message' => 'Lesson rejected',
                'lesson' => $lesson,
                'course_status' => $course->status
            ]);
        } catch (Exception $e) {
            Log::error('Lesson rejection error:', ['message' => $e->getMessage()]);
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while rejecting the lesson.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    protected function createFilename($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = str_replace('.' . $extension, '', $file->getClientOriginalName());
        $filename .= '_' . md5(time()) . '.' . $extension;
        return $filename;
    }


      public function getCourseLessonsInstructor(Request $request, $courseId): JsonResponse
    {
        // Get the authenticated user
        $user = Auth::user();

        // Check if the user exists and has the 'instructor' role
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

        // Check if the course exists
        $course = Course::find($courseId);
        if (!$course) {
            return response()->json([
                'message' => 'Course not found.'
            ], 404);
        }

        // Verify that the instructor is associated with the course
        $isInstructorOfCourse = Course_Instructors::where('course_id', $courseId)
            ->where('instructor_id', $instructor->id)
            ->exists();

        if (!$isInstructorOfCourse) {
            return response()->json([
                'message' => 'You are not authorized to view lessons for this course.'
            ], 403);
        }

        // Fetch all lessons for the course (only approved lessons)
        $lessons = Lesson::where('course_id', $courseId)
            ->where('status', 'approved')
            ->select('id', 'title', 'video_url', 'duration', 'is_preview', 'sort_order', 'created_at', 'updated_at')
            ->orderBy('sort_order', 'asc')
            ->get();

        // Return the lessons in a JSON response
        return response()->json([
            'message' => 'Lessons retrieved successfully.',
            'data' => [
                'course_id' => $courseId,
                'course_name' => $course->course_name,
                'lessons' => $lessons
            ]
        ], 200);
    }

    public function search(Request $request)
{
    $query = Lesson::query();

    if ($request->filled('title')) {
        $query->where('title', 'like', '%' . $request->input('title')
 . '%');
    }

    if ($request->filled('course_id')) {
        $query->where('course_id', $request->input('course_id'));
    }

    if ($request->filled('status')) {
        $query->where('status', $request->input('status')
);
    }

    return response()->json($query->paginate(10));
}

public function getPendingLessons(Request $request, $courseId)
{
    $lessons = Lesson::where('course_id', $courseId)
        ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json($lessons);
}
/**
     * Admin duyệt trạng thái của lesson
     *
     * @param Request $request
     * @param int $lessonId
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveLesson(Request $request, $lessonId)
    {
        try {
            // Kiểm tra quyền admin
            $admin = Admins::where('user_id', Auth::id())->first();

            if (!$admin || !in_array($admin->admin_level, ['program', 'organization'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền duyệt lesson.'
                ], 403);
            }

            // Tìm lesson
            $lesson = Lesson::findOrFail($lessonId);

            // Validate trạng thái mới
            $newStatus = $request->input('status');
            $validStatuses = ['pending', 'approved', 'rejected'];

            if (!in_array($newStatus, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trạng thái không hợp lệ. Chỉ chấp nhận: ' . implode(', ', $validStatuses)
                ], 400);
            }

            // Cập nhật trạng thái lesson
            $lesson->status = $newStatus;
            $lesson->updated_at = now();
            $lesson->save();

            // Ghi log hoạt động vào activity_log
            $activityLog = json_decode($admin->activity_log, true) ?? [];
            $activityLog[] = [
                'action' => 'update_lesson_status',
                'lesson_id' => $lesson->id,
                'new_status' => $newStatus,
                'timestamp' => now()->toDateTimeString()
            ];
            $admin->activity_log = json_encode($activityLog);
            $admin->save();

            return response()->json([
                'success' => true,
                'message' => "Lesson đã được cập nhật trạng thái thành '$newStatus'.",
                'data' => $lesson
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy lesson.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Lỗi khi duyệt lesson: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra. Vui lòng thử lại.'
            ], 500);
        }
    }
}