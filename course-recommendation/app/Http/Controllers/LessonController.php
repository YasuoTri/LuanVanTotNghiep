<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lesson\StoreLessonRequest;
use App\Http\Requests\Lesson\UpdateLessonRequest;
use App\Services\CloudinaryService;
use App\Models\Lesson;
use App\Models\Course;
use App\Models\Course_Instructors;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Exception;

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
            $data['video_url'] = $this->cloudinaryService->upload(
                $uploadedFile,
                'lessons/course_' . $data['course_id']
            );

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
                    storage_path('app/' . $path),
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
                $data['video_url'] = $this->cloudinaryService->upload(
                    $uploadedFile,
                    'lessons/course_' . $lesson->course_id
                );

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

            $lessons = Lesson::where('course_id', $course_id)->get();
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

            $lesson = Lesson::where('id', $lesson_id)
                ->where('course_id', $course_id)
                ->first();

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
                storage_path('app/' . $path),
                $fileName,
                $file->getClientMimeType(),
                null,
                true
            );

            // Upload lên Cloudinary
            $data['video_url'] = $this->cloudinaryService->upload(
                $uploadedFile,
                'lessons/course_' . $course_id
            );

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
                    storage_path('app/' . $path),
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
                $data['video_url'] = $this->cloudinaryService->upload(
                    $uploadedFile,
                    'lessons/course_' . $course_id
                );

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

            // Kiểm tra enrollment
            $enrollment = Enrollment::where('id', $id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Kiểm tra course đã approved
            $course = Course::findOrFail($enrollment->course_id);
            if ($course->status !== 'approved') {
                return response()->json(['message' => 'Course is not approved yet'], 403);
            }

            // Lấy lessons đã approved
            $lessons = Lesson::where('course_id', $enrollment->course_id)
                ->where('status', 'approved')
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
}