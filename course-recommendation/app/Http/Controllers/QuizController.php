<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quiz\StoreQuizRequest;
use App\Http\Requests\Quiz\UpdateQuizRequest;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;

class QuizController extends Controller
{
    public function index(): JsonResponse
    {
        $quizzes = Quiz::all();
        return response()->json(['data' => $quizzes]);
    }

    public function show($id): JsonResponse
    {
        $quiz = Quiz::findOrFail($id);
        return response()->json(['data' => $quiz]);
    }

    public function store(StoreQuizRequest $request): JsonResponse
    {
        $quiz = Quiz::create($request->validated());
        return response()->json(['message' => 'Quiz created successfully', 'data' => $quiz], 201);
    }

    public function update(UpdateQuizRequest $request, $id): JsonResponse
    {
        $quiz = Quiz::findOrFail($id);
        $quiz->update($request->validated());
        return response()->json(['message' => 'Quiz updated successfully', 'data' => $quiz]);
    }

    public function destroy($id): JsonResponse
    {
        $quiz = Quiz::findOrFail($id);
        $quiz->delete();
        return response()->json(['message' => 'Quiz deleted successfully']);
    }
}