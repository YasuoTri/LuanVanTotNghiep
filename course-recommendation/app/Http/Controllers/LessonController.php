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
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        // Kiểm tra xem user hiện tại có phải là instructor của course này không
        $course = Course::where('id', $course_id)
            ->where('instructor_id', $user->instructor->id)
            ->first();

        if (!$course) {
            return response()->json(['message' => 'You are not the instructor for this course'], 403);
        }

        // Lấy danh sách bài học (bao gồm soft deleted nếu muốn)
        $lessons = Lesson::withTrashed() // 👉 nếu bạn muốn instructor thấy cả bài đã xoá mềm
            ->where('course_id', $course_id)
            ->orderBy('sort_order') // nếu có
            ->get();

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
        // Kiểm tra xem user hiện tại có phải là instructor của course này không
        $course = Course::where('id', $course_id)
            ->where('instructor_id', $user->instructor->id)
            ->first();

        if (!$course) {
            return response()->json(['message' => 'You are not the instructor for this course'], 403);
        }

        $lesson = Lesson::with(['quizzes', 'course'])
            ->where('id', $lesson_id)
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
       $course = Course::where('id', $course_id)
    ->where('instructor_id', $user->instructor->id) // hoặc 'instructor_id' nếu tên cột khác
    ->first();

if (!$course) {
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

                // 1. Lấy origin ID (nếu là bản gốc thì chính nó)
        $originId = $lesson->origin_id ?? $lesson->id;

        // 2. Kiểm tra nếu có bản đang chờ duyệt (pending)
        $hasPending = Lesson::where('origin_id', $originId)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'message' => 'This lesson has already been edited and is pending approval.'
            ], 409);
        }

        // 3. Kiểm tra xem lesson hiện tại có phải là bản mới nhất không
        $latestLesson = Lesson::where('origin_id', $originId)
            ->orWhere('id', $originId)
            ->orderByDesc('version')
            ->first();

        if ($lesson->id !== $latestLesson->id) {
            return response()->json([
                'message' => 'Only the latest version of the lesson can be updated.'
            ], 400);
        }

        $data = $request->validated();
        $data['course_id'] = $course_id;
        $data['version']= $lesson->version + 1;
        $data['origin_id']= $lesson->origin_id ?? $lesson->id;
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

        $newLesson = $lesson->create($data);
         // thêm lesson_progress not_started cho tất cả user đã enroll
        $enrolledUsers = Enrollment::where('course_id', $course_id)
            ->pluck('user_id');

        foreach ($enrolledUsers as $userId) {
            LessonProgress::firstOrCreate(
                [
                    'user_id' => $userId,
                    'lesson_id' => $newLesson->id,
                ],
                [
                    'status' => 'not_started'
                ]
            );
        }
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
            $course = Course::where('id', $course_id)
            ->where('instructor_id', $user->instructor->id) // hoặc 'instructor_id' nếu tên cột khác
            ->first();

            if (!$course) {
                return response()->json(['message' => 'You are not an instructor for this course'], 403);
            }

            $lesson = Lesson::where('id', $lesson_id)
                ->where('course_id', $course_id)
                ->first();

            if (!$lesson) {
                return response()->json(['message' => 'Lesson not found'], 404);
            }
            // Kiểm tra có học viên nào đã enroll chưa
            $hasEnrollment = Enrollment::where('course_id', $course_id)->exists();
            if ($hasEnrollment) {
                return response()->json(['message' => 'Cannot delete lesson. There are students enrolled in this course.'], 403);
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
        $enrollment = Enrollment::with('course.instructors')->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $course = Course::where('id', $enrollment->course_id)
        ->whereIn('status', ['approved', 'unavailable'])
        ->first();

        if (!$course) {
            return response()->json(['message' => 'Course is not found '], 403);
        }
        $review= Review::with('user','user.student')->where('course_id', $course->id)->get();
        $baseLessons = Lesson::withTrashed()
    ->where('course_id', $course->id)
    ->whereNull('origin_id') // chỉ lấy bài học gốc
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
        'lessons.deleted_at',
        'lessons.status as progress_status',
        'lesson_progress.completed_at',
        'lesson_progress.status as progress'
    )
    ->get();

    $finalLessons = collect();

    foreach ($baseLessons as $lesson) {
        // 1. Bỏ qua nếu bị xóa sau khi học viên đăng ký và không có progress
        if ($lesson->deleted_at !== null && $enrollment->enrolled_at > $lesson->deleted_at) {
            if (!($lesson->progress && $lesson->progress !== 'not_started')) {
                continue;
            }
        }

        // 2. Thêm bài học gốc
        $finalLessons->push((object)[
            'id' => $lesson->id,
            'title' => $lesson->title,
            'video_url' => $lesson->video_url,
            'duration' => $lesson->duration,
            'is_preview' => $lesson->is_preview,
            'sort_order' => $lesson->sort_order,
            'version_of' => null, // gốc
            'progress_status' => $lesson->progress_status,
            'completed_at' => $lesson->completed_at,
            'progress' => $lesson->progress
        ]);

        // 3. Lấy tối đa 2 phiên bản mới nhất đã approved
        $versions = Lesson::where('origin_id', $lesson->id)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->limit(2)
            ->get();

        foreach ($versions as $version) {
            $progress = DB::table('lesson_progress')
                ->where('lesson_id', $version->id)
                ->where('user_id', $user->id)
                ->first();

            $finalLessons->push((object)[
                'id' => $version->id,
                'title' => $version->title,
                'video_url' => $version->video_url,
                'duration' => $version->duration,
                'is_preview' => $version->is_preview,
                'sort_order' => $lesson->sort_order, // giữ thứ tự từ bài gốc
                'version_of' => $lesson->id,
                'progress_status' => $version->status,
                'completed_at' => $progress->completed_at ?? null,
                'progress' => $progress->status ?? null
            ]);
        }
    }

    $finalLessons = $finalLessons->sortBy('sort_order')->values();
        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'course' => $course,
                'lessons' => $finalLessons,
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
    try {
        $user = Auth::user();

        if (!$user || $user->role !== 'instructor') {
            return response()->json([
                'message' => 'Unauthorized. Only instructors can access this endpoint.'
            ], 403);
        }

        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor) {
            return response()->json([
                'message' => 'Instructor profile not found.'
            ], 404);
        }

        $course = Course::where('id', $courseId)
            ->where('instructor_id', $instructor->id)
            ->first();

        if (!$course) {
            return response()->json([
                'message' => 'Course not found or you are not authorized to view lessons for this course.'
            ], 404);
        }

        // Lấy số lượng mỗi trang, mặc định là 10 nếu không có trong request
        $perPage = $request->input('per_page', 10);

        // Paginate lessons
        $paginatedLessons = Lesson::where('course_id', $courseId)
            ->select('id', 'title', 'video_url', 'duration', 'is_preview', 'sort_order', 'status', 'created_at', 'updated_at')
            ->orderBy('sort_order', 'asc')
            ->paginate($perPage);

        // Thống kê theo status (toàn bộ lesson, không paginate)
        $allLessons = Lesson::where('course_id', $courseId)->paginate(10);
        return response()->json($allLessons);
        // $lessonsByStatus = $allLessons->groupBy('status');

        // return response()->json([
        //     'message' => 'Lessons retrieved successfully.',
        //     'data' => [
        //         'course_id' => $courseId,
        //         'course_name' => $course->course_name,
        //         'course_status' => $course->status,
        //         'total_lessons' => $allLessons->count(),
        //         'total_duration' => $allLessons->sum('duration'),
        //         'lessons_by_status' => [
        //             'approved' => $lessonsByStatus->get('approved', collect())->count(),
        //             'pending' => $lessonsByStatus->get('pending', collect())->count(),
        //             'rejected' => $lessonsByStatus->get('rejected', collect())->count(),
        //         ],
        //         'lessons' => $paginatedLessons->items(), // chỉ nội dung bài học trong trang hiện tại
        //         'pagination' => [
        //             'current_page' => $paginatedLessons->currentPage(),
        //             'last_page' => $paginatedLessons->lastPage(),
        //             'per_page' => $paginatedLessons->perPage(),
        //             'total' => $paginatedLessons->total(),
        //         ]
        //     ]
        // ], 200);

    } catch (\Exception $e) {
        Log::error('Get course lessons for instructor error:', [
            'course_id' => $courseId,
            'user_id' => Auth::id(),
            'message' => $e->getMessage()
        ]);

        return response()->json([
            'message' => 'An error occurred while retrieving the lessons.',
            'error' => $e->getMessage()
        ], 500);
    }
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