<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonProgress\StoreLessonProgressRequest;
use App\Http\Requests\LessonProgress\UpdateLessonProgressRequest;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;

class LessonProgressController extends Controller
{
    public function index(): JsonResponse
    {
        $lessonProgresses = LessonProgress::all();
        return response()->json(['data' => $lessonProgresses]);
    }

    public function show($id): JsonResponse
    {
        $lessonProgress = LessonProgress::findOrFail($id);
        return response()->json(['data' => $lessonProgress]);
    }

    public function store(StoreLessonProgressRequest $request): JsonResponse
    {
        $lessonProgress = LessonProgress::create($request->validated());
        return response()->json(['message' => 'Lesson progress created successfully', 'data' => $lessonProgress], 201);
    }

    public function update(UpdateLessonProgressRequest $request, $id): JsonResponse
    {
        $lessonProgress = LessonProgress::findOrFail($id);
        $lessonProgress->update($request->validated());
        return response()->json(['message' => 'Lesson progress updated successfully', 'data' => $lessonProgress]);
    }

    public function destroy($id): JsonResponse
    {
        $lessonProgress = LessonProgress::findOrFail($id);
        $lessonProgress->delete();
        return response()->json(['message' => 'Lesson progress deleted successfully']);
    }
}