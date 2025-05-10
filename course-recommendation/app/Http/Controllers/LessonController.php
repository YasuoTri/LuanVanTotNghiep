<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lesson\StoreLessonRequest;
use App\Http\Requests\Lesson\UpdateLessonRequest;
use App\Models\Lesson;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;

class LessonController extends Controller
{
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
        $data = $request->validated();

        // Upload video to Cloudinary
        if ($request->hasFile('video')) {
            $uploadedFile = Cloudinary::uploadVideo($request->file('video')->getRealPath(), [
                'folder' => 'lessons',
                'resource_type' => 'video',
                'public_id' => 'lesson_' . time(), // Đảm bảo public_id duy nhất
            ]);
            $data['video_url'] = $uploadedFile->getSecurePath();
        }

        $lesson = Lesson::create($data);
        return response()->json(['message' => 'Lesson created successfully', 'data' => $lesson], 201);
    }

    public function update(UpdateLessonRequest $request, $id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);
        $data = $request->validated();

        // Update video if provided
        if ($request->hasFile('video')) {
            // Delete old video from Cloudinary if exists
            if ($lesson->video_url) {
                $publicId = 'lessons/' . pathinfo($lesson->video_url, PATHINFO_FILENAME);
                Cloudinary::destroy($publicId, ['resource_type' => 'video']);
            }
            $uploadedFile = Cloudinary::uploadVideo($request->file('video')->getRealPath(), [
                'folder' => 'lessons',
                'resource_type' => 'video',
                'public_id' => 'lesson_' . time(),
            ]);
            $data['video_url'] = $uploadedFile->getSecurePath();
        }

        $lesson->update($data);
        return response()->json(['message' => 'Lesson updated successfully', 'data' => $lesson]);
    }

    public function destroy($id): JsonResponse
    {
        $lesson = Lesson::findOrFail($id);

        // Delete video from Cloudinary if exists
        if ($lesson->video_url) {
            $publicId = 'lessons/' . pathinfo($lesson->video_url, PATHINFO_FILENAME);
            Cloudinary::destroy($publicId, ['resource_type' => 'video']);
        }

        $lesson->delete();
        return response()->json(['message' => 'Lesson deleted successfully']);
    }
}