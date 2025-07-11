<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lesson\StoreLessonRequest;
use App\Http\Requests\Lesson\UpdateLessonRequest;
use App\Models\Admins;
use App\Models\Review;
use App\Services\CloudinaryService;
use App\Models\Lesson;
use App\Models\Course;
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
        // Chỉ lấy bài học có is_visible = true cho người dùng thông thường
        $lessons = Lesson::where('is_visible', true)->paginate(10);
        return response()->json(['data' => $lessons]);
    }

    public function show($id): JsonResponse
    {
        // Chỉ hiển thị bài học nếu is_visible = true hoặc người dùng là instructor của khóa học
        $lesson = Lesson::findOrFail($id);
        $user = Auth::user();

        if (!$lesson->is_visible && (!$user || !$this->isCourseInstructor($user, $lesson->course_id))) {
            return response()->json(['message' => 'Lesson not visible or you are not authorized'], 403);
        }

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
                $data['video_url'] = $apiResponse['secure_url'] ?? '';
                if (isset($apiResponse['duration'])) {
                    $data['duration'] = round($apiResponse['duration'] / 60, 2);
                } else {
                    Log::warning('Cloudinary response missing duration', ['upload_result' => $apiResponse]);
                    $data['duration'] = 0;
                }

                // Xóa file tạm
                $disk->delete($path);

                // Lưu lesson với is_visible = false (chờ duyệt)
                $data['is_visible'] = false;
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
                    $apiResponse = $this->cloudinaryService->upload(
                        $uploadedFile,
                        'lessons/course_' . $lesson->course_id
                    );
                    $data['video_url'] = $apiResponse['secure_url'] ?? '';
                    if (isset($apiResponse['duration'])) {
                        $data['duration'] = round($apiResponse['duration'] / 60, 2);
                    } else {
                        Log::warning('Cloudinary response missing duration', ['upload_result' => $apiResponse]);
                        $data['duration'] = 0;
                    }

                    // Xóa file tạm
                    $disk->delete($path);

                    // Đặt is_visible = false khi có video mới
                    $data['is_visible'] = false;
                } else {
                    // Trả về tiến trình upload chunk
                    $handler = $save->handler();
                    return response()->json([
                        'done' => $handler->getPercentageDone(),
                        'status' => true
                    ]);
                }
            }

            // Đặt is_visible = false nếu có thay đổi metadata quan trọng
            if (isset($data['title']) || isset($data['duration']) || isset($data['is_preview']) || isset($data['sort_order'])) {
                $data['is_visible'] = false;
            }

            $lesson->update($data);
            return response()->json([
                'message' => 'Lesson updated successfully',
                'data' => $lesson
            ]);
        } catch (UploadMissingFileException $e) {
            return response()->json([
                'status' => 400,
                'error' => 'No file uploaded.'
            ], 400);
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
            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course_id)
                ->first();

            if (!$enrollment) {
                return response()->json(['message' => 'You are not enrolled in this course'], 403);
            }

            // Kiểm tra course đã approved
            $course = Course::findOrFail($course_id);
            if ($course->status !== 'approved') {
                return response()->json(['message' => 'Course is not approved yet'], 403);
            }

            // Chỉ trả về lesson có is_visible = true
            $lesson = Lesson::where('id', $lesson_id)
                ->where('course_id', $course_id)
                ->where('is_visible', true)
                ->first();

            if (!$lesson) {
                return response()->json(['message' => 'Lesson not found or not visible'], 404);
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

        // Kiểm tra xem user có phải là instructor không
        if (!$user || $user->role !== 'instructor') {
            return response()->json(['message' => 'Unauthorized. Only instructors can access this endpoint.'], 403);
        }

        $instructor = Instructors::where('user_id', $user->id)->first();
        if (!$instructor) {
            return response()->json(['message' => 'Instructor profile not found.'], 404);
        }

        // Kiểm tra khóa học tồn tại
        $course = Course::findOrFail($course_id);

        // Kiểm tra xem user là instructor sở hữu khóa học
        $isCourseOwner = $course->instructor_id === $instructor->id;

        // Nếu user là instructor của khóa học
        if ($isCourseOwner) {
            // Lấy tất cả bài học (bao gồm cả is_visible = false và bài đã xóa mềm)
            $lessons = Lesson::withTrashed()
                ->where('course_id', $course_id)
                ->orderBy('sort_order')
                ->get();
        } else {
            // Kiểm tra xem instructor có enrolled vào khóa học không
            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course_id)
                ->first();

            if (!$enrollment) {
                return response()->json(['message' => 'You are not enrolled in this course or not the instructor of this course'], 403);
            }

            // Nếu enrolled, chỉ trả về bài học có is_visible = true
            $lessons = Lesson::where('course_id', $course_id)
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->get();
        }

        return response()->json($lessons);
    } catch (Exception $e) {
        Log::error('Index lessons for instructor error:', [
            'course_id' => $course_id,
            'user_id' => Auth::id(),
            'message' => $e->getMessage()
        ]);
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

            // Lấy bài học (bao gồm cả is_visible = false) cho instructor
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
                ->where('instructor_id', $user->instructor->id)
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
                $apiResponse = $this->cloudinaryService->upload(
                    $uploadedFile,
                    'lessons/course_' . $course_id
                );
                $data['video_url'] = $apiResponse['secure_url'] ?? '';
                if (isset($apiResponse['duration'])) {
                    $data['duration'] = round($apiResponse['duration'] / 60, 2);
                } else {
                    Log::warning('Cloudinary response missing duration', ['upload_result' => $apiResponse]);
                    $data['duration'] = 0;
                }

                // Xóa file tạm
                $disk->delete($path);

                // Start transaction
                DB::beginTransaction();
                try {
                    // Lưu lesson với is_visible = false
                    $data['course_id'] = $course_id;
                    $data['is_visible'] = false;
                    $lesson = Lesson::create($data);

                    // Create lesson progress for all enrolled users
                    $enrolledUsers = Enrollment::where('course_id', $course_id)
                        ->pluck('user_id');

                    foreach ($enrolledUsers as $userId) {
                        LessonProgress::firstOrCreate(
                            [
                                'user_id' => $userId,
                                'lesson_id' => $lesson->id,
                            ],
                            [
                                'status' => 'not_started',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }

                    DB::commit();

                    return response()->json([
                        'message' => 'Lesson created successfully',
                        'data' => $lesson
                    ], 201);
                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error('Lesson creation transaction failed:', ['message' => $e->getMessage()]);
                    return response()->json([
                        'status' => 500,
                        'error' => 'An error occurred while creating lesson progress.',
                        'message' => $e->getMessage()
                    ], 500);
                }
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
    // public function updateForInstructor(UpdateLessonRequest $request, $course_id, $lesson_id): JsonResponse
    // {
    //     try {
    //         $user = Auth::user();
    //         $lesson = Lesson::where('id', $lesson_id)
    //             ->where('course_id', $course_id)
    //             ->first();

    //         if (!$lesson) {
    //             return response()->json(['message' => 'Lesson not found'], 404);
    //         }

    //         // Kiểm tra trạng thái course
    //         $course = Course::findOrFail($course_id);
    //         if ($course->status === 'rejected') {
    //             return response()->json(['error' => 'Cannot update lesson for rejected course'], 403);
    //         }

    //         // Kiểm tra xem user hiện tại có phải là instructor của course này không
    //         if ($course->instructor_id !== $user->instructor->id) {
    //             return response()->json(['message' => 'You are not the instructor for this course'], 403);
    //         }

    //         // Kiểm tra phiên bản bài học
    //         $originId = $lesson->origin_id ?? $lesson->id;
    //         $latestLesson = Lesson::where('origin_id', $originId)
    //             ->orWhere('id', $originId)
    //             ->orderByDesc('version')
    //             ->first();

    //         if ($lesson->id !== $latestLesson->id) {
    //             return response()->json([
    //                 'message' => 'Only the latest version of the lesson can be updated.'
    //             ], 400);
    //         }

    //         // Kiểm tra xem có learner nào đã học lesson này chưa
    //         $hasProgress = LessonProgress::where('lesson_id', $lesson_id)->where('status','in_progress')->exists();

    //         $data = $request->validated();
    //         $data['course_id'] = $course_id;

    //         // Xử lý chunked upload nếu có video mới
    //         if ($request->hasFile('video')) {
    //             $receiver = new FileReceiver('video', $request, HandlerFactory::classFromRequest($request));

    //             if ($receiver->isUploaded() === false) {
    //                 throw new UploadMissingFileException();
    //             }

    //             $save = $receiver->receive();

    //             if ($save->isFinished()) {
    //                 // File hoàn chỉnh
    //                 $file = $save->getFile();
    //                 $fileName = $this->createFilename($file);

    //                 // Lưu tạm file
    //                 $disk = Storage::disk('local');
    //                 $path = $disk->putFileAs('videos', $file, $fileName);

    //                 // Tạo UploadedFile từ file tạm
    //                 $uploadedFile = new \Illuminate\Http\UploadedFile(
    //                     storage_path('app/private/' . $path),
    //                     $fileName,
    //                     $file->getClientMimeType(),
    //                     null,
    //                     true
    //                 );

    //                 // Xóa video cũ trên Cloudinary nếu có
    //                 if ($lesson->video_url) {
    //                     $this->cloudinaryService->deleteByUrl($lesson->video_url);
    //                 }

    //                 // Upload lên Cloudinary
    //                 $apiResponse = $this->cloudinaryService->upload(
    //                     $uploadedFile,
    //                     'lessons/course_' . $course_id
    //                 );
    //                 $data['video_url'] = $apiResponse['secure_url'] ?? '';
    //                 if (isset($apiResponse['duration'])) {
    //                     $data['duration'] = round($apiResponse['duration'] / 60, 2);
    //                 } else {
    //                     Log::warning('Cloudinary response missing duration', ['upload_result' => $apiResponse]);
    //                     $data['duration'] = 0;
    //                 }

    //                 // Xóa file tạm
    //                 $disk->delete($path);

    //                 // Đặt is_visible = false khi có video mới
    //                 // $data['is_visible'] = false;
    //             } else {
    //                 // Trả về tiến trình upload chunk
    //                 $handler = $save->handler();
    //                 return response()->json([
    //                     'done' => $handler->getPercentageDone(),
    //                     'status' => true
    //                 ]);
    //             }
    //         }

    //         // Đặt is_visible = false nếu có thay đổi metadata quan trọng
    //         // if (isset($data['title']) || isset($data['duration']) || isset($data['is_preview']) || isset($data['sort_order'])) {
    //         //     $data['is_visible'] = false;
    //         // }

    //         if ($hasProgress) {
    //             // Nếu đã có learner học, tạo lesson mới với version mới
    //             $data['version'] = $lesson->version + 1;
    //             $data['origin_id'] = $lesson->origin_id ?? $lesson->id;

    //             $newLesson = Lesson::create($data);

    //             // Thêm lesson_progress not_started cho tất cả user đã enroll
    //             $enrolledUsers = Enrollment::where('course_id', $course_id)
    //                 ->pluck('user_id');
                
    //             $newLesson->is_visible=true;
    //             $lesson->is_visible=false;
    //             foreach ($enrolledUsers as $userId) {
    //                 LessonProgress::firstOrCreate(
    //                     [
    //                         'user_id' => $userId,
    //                         'lesson_id' => $newLesson->id,
    //                     ],
    //                     [
    //                         'status' => 'not_started'
    //                     ]
    //                 );
    //             }

    //             return response()->json([
    //                 'message' => 'Lesson updated successfully' . (isset($data['is_visible']) && $data['is_visible'] === false ? ', awaiting approval' : ''),
    //                 'data' => $newLesson
    //             ]);
    //         } else {
    //             // Nếu chưa có learner học, cập nhật trực tiếp lesson hiện tại
    //             $lesson->update($data);

    //             return response()->json([
    //                 'message' => 'Lesson updated successfully' . (isset($data['is_visible']) && $data['is_visible'] === false ? ', awaiting approval' : ''),
    //                 'data' => $lesson
    //             ]);
    //         }
    //     } catch (UploadMissingFileException $e) {
    //         return response()->json([
    //             'status' => 400,
    //             'error' => 'No file uploaded.'
    //         ], 400);
    //     } catch (\Exception $e) {
    //         Log::error('Lesson update for instructor error:', [
    //             'course_id' => $course_id,
    //             'lesson_id' => $lesson_id,
    //             'message' => $e->getMessage()
    //         ]);
    //         return response()->json([
    //             'status' => 500,
    //             'error' => 'An error occurred while updating the lesson.',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }
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

        // Kiểm tra xem user hiện tại có phải là instructor của course này không
        if ($course->instructor_id !== $user->instructor->id) {
            return response()->json(['message' => 'You are not the instructor for this course'], 403);
        }

        // Kiểm tra phiên bản bài học
        $originId = $lesson->origin_id ?? $lesson->id;
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
        $isVideoUpdate = $request->hasFile('video');

        // Xử lý chunked upload nếu có video mới
        if ($isVideoUpdate) {
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
                $apiResponse = $this->cloudinaryService->upload(
                    $uploadedFile,
                    'lessons/course_' . $course_id
                );
                $data['video_url'] = $apiResponse['secure_url'] ?? '';
                if (isset($apiResponse['duration'])) {
                    $data['duration'] = round($apiResponse['duration'] / 60, 2);
                } else {
                    Log::warning('Cloudinary response missing duration', ['upload_result' => $apiResponse]);
                    $data['duration'] = 0;
                }

                // Xóa file tạm
                $disk->delete($path);
            } else {
                // Trả về tiến trình upload chunk
                $handler = $save->handler();
                return response()->json([
                    'done' => $handler->getPercentageDone(),
                    'status' => true
                ]);
            }
        }

        // Nếu có cập nhật video, tạo lesson mới với version mới
        if ($isVideoUpdate) {
            $data['version'] = $lesson->version + 1;
            $data['origin_id'] = $lesson->origin_id ?? $lesson->id;
            $data['is_visible'] = true; // New version is visible
            $lesson->is_visible = false; // Old version is hidden

            $newLesson = Lesson::create($data);

            // Thêm lesson_progress not_started cho tất cả user đã enroll
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
                'message' => 'Lesson updated successfully with new version',
                'data' => $newLesson
            ]);
        } else {
            // Nếu không có video, cập nhật trực tiếp lesson hiện tại
            $lesson->update($data);

            return response()->json([
                'message' => 'Lesson updated successfully',
                'data' => $lesson
            ]);
        }
    } catch (UploadMissingFileException $e) {
        return response()->json([
            'status' => 400,
            'error' => 'No file uploaded.'
        ], 400);
    } catch (\Exception $e) {
        Log::error('Lesson update for instructor error:', [
            'course_id' => $course_id,
            'lesson_id' => $lesson_id,
            'message' => $e->getMessage()
        ]);
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
                ->where('instructor_id', $user->instructor->id)
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
            $hasEnrollment = LessonProgress::where('lesson_id', $lesson_id)->exists();
            if ($hasEnrollment) {
                return response()->json(['message' => 'Cannot delete lesson. There are students learn this course.'], 403);
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
        $enrollment = Enrollment::with('course.instructors')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $course = Course::where('id', $enrollment->course_id)
            ->whereIn('status', ['approved', 'unavailable'])
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Course not found'], 403);
        }

        $reviews = Review::with('user', 'user.student')
            ->where('course_id', $course->id)
            ->get();

        // Fetch base lessons with progress for the authenticated user
        $baseLessons = Lesson::with(['lessonProgress' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])
            ->withTrashed()
            ->where('course_id', $course->id)
            ->whereNull('origin_id')
            ->where('is_visible', true)
            ->select([
                'id',
                'title',
                'video_url',
                'duration',
                'is_preview',
                'sort_order',
                'deleted_at',
                'is_visible as visibility',
            ])
            ->orderBy('sort_order', 'asc')
            ->get();

        $finalLessons = collect();

        foreach ($baseLessons as $lesson) {
            // Skip soft-deleted lessons with no meaningful progress
            if ($lesson->deleted_at !== null && $enrollment->enrolled_at > $lesson->deleted_at) {
                $progress = $lesson->lessonProgress->first();
                if (!$progress || $progress->status === 'not_started') {
                    continue;
                }
            }

            // Fetch up to 2 latest visible versions, ordered by version ascending
            $versions = Lesson::with(['lessonProgress' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
                ->where('origin_id', $lesson->id)
                ->where('is_visible', true)
                ->orderBy('version', 'asc')
                ->get();

            // Prepare the parent lesson
            $progress = $lesson->lessonProgress->first();
            $parentLesson = (object)[
                'id' => $lesson->id,
                'title' => $lesson->title,
                'video_url' => $lesson->video_url,
                'duration' => $lesson->duration,
                'is_preview' => $lesson->is_preview,
                'sort_order' => $lesson->sort_order,
                'version_of' => null,
                'visibility' => $lesson->visibility,
                'completed_at' => $progress ? $progress->completed_at : null,
                'progress' => $progress ? $progress->status : 'not_started',
                'versions' => [],
            ];

            // Add versions to the parent lesson
            foreach ($versions as $version) {
                $versionProgress = $version->lessonProgress->first();
                $parentLesson->versions[] = (object)[
                    'id' => $version->id,
                    'title' => $version->title,
                    'video_url' => $version->video_url,
                    'duration' => $version->duration,
                    'is_preview' => $version->is_preview,
                    'sort_order' => $lesson->sort_order,
                    'version_of' => $version->version,
                    'visibility' => $version->is_visible,
                    'completed_at' => $versionProgress ? $versionProgress->completed_at : null,
                    'progress' => $versionProgress ? $versionProgress->status : 'not_started',
                ];
            }

            $finalLessons->push($parentLesson);
        }

        // Sort by sort_order of parent lessons
        $finalLessons = $finalLessons->sortBy('sort_order')->values();

        return response()->json([
            'data' => [
                'enrollment_id' => $enrollment->id,
                'course' => $course,
                'lessons' => $finalLessons,
                'reviews' => $reviews,
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
            $lesson->update(['is_visible' => true]);

            // Kiểm tra xem tất cả lesson của course đã visible
            $course = Course::findOrFail($course_id);
            $allVisible = $course->lessons()->where('is_visible', false)->count() === 0;

            if ($allVisible) {
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
            $lesson->update(['is_visible' => false]);

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

    protected function isCourseInstructor($user, $course_id): bool
    {
        if (!$user || !$user->instructor) {
            return false;
        }
        return Course::where('id', $course_id)
            ->where('instructor_id', $user->instructor->id)
            ->exists();
    }

public function getCourseLessonsInstructor(Request $request, $courseId): JsonResponse
{
    try {
        $user = Auth::user();

        // Kiểm tra xem user có phải là instructor không
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

        // Kiểm tra khóa học tồn tại
        $course = Course::findOrFail($courseId);

        // Kiểm tra xem user là instructor sở hữu khóa học
        $isCourseOwner = $course->instructor_id === $instructor->id;

        // Xây dựng query cho lessons
        $query = Lesson::where('course_id', $courseId)
            ->select(
                'id',
                'origin_id',
                'version',
                'course_id',
                'title',
                'video_url',
                'duration',
                'is_preview',
                'sort_order',
                'is_visible',
                'created_at',
                'updated_at',
                'deleted_at'
            );

        // Nếu user là instructor của khóa học
        if ($isCourseOwner) {
            // Lấy tất cả bài học (bao gồm cả bài đã xóa mềm và is_visible = false)
            $query->withTrashed();
        } else {
            // Kiểm tra xem instructor có enrolled vào khóa học không
            $enrollment = Enrollment::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->first();

            if (!$enrollment) {
                return response()->json([
                    'message' => 'You are not enrolled in this course or not the instructor of this course.'
                ], 403);
            }

            // Chỉ lấy các bài học có is_visible = true cho instructor không sở hữu hoặc learner
            $query->where('is_visible', true);
        }

        // Lấy danh sách bài học và nhóm theo origin_id để sắp xếp
      $query->orderBy('sort_order', 'asc')
              ->orderBy('version', 'desc');

        // Áp dụng phân trang
        $lessons = $query->paginate(10);

        return response()->json($lessons);
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
            $query->where('title', 'like', '%' . $request->input('title') . '%');
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }

        if ($request->filled('is_visible')) {
            $query->where('is_visible', $request->input('is_visible'));
        }

        // Chỉ cho phép instructor của khóa học hoặc admin thấy bài học is_visible = false
        $user = Auth::user();
        if (!$user || (!$this->isCourseInstructor($user, $request->input('course_id')) && $user->role !== 'admin')) {
            $query->where('is_visible', true);
        }

        return response()->json($query->paginate(10));
    }

    public function getPendingLessons(Request $request, $courseId)
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);

        // Chỉ cho phép instructor của khóa học hoặc admin thấy bài học pending
        if (!$user || (!$this->isCourseInstructor($user, $courseId) && $user->role !== 'admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $lessons = Lesson::where('course_id', $courseId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($lessons);
    }

    public function approveLesson(Request $request, $lessonId)
    {
        try {
            // Tìm lesson
            $lesson = Lesson::findOrFail($lessonId);

            // Validate trạng thái mới
            $newStatus = $request->input('is_visible');
            if (!in_array($newStatus, [true, false])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ivalid status.'
                ], 400);
            }

            // Cập nhật trạng thái lesson
            $lesson->is_visible = $newStatus;
            $lesson->updated_at = now();
            $lesson->save();

            return response()->json([
                'success' => true,
                'message' => "Lesson is updated the status into'" . ($newStatus ? 'visible' : 'hidden') . "'.",
                'data' => $lesson
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error when review lesson' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error occur,please try again later.',
            ], 500);
        }
    }
}