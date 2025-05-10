<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizResult\StoreQuizResultRequest;
use App\Http\Requests\QuizResult\UpdateQuizResultRequest;
use App\Models\QuizResult;
use Illuminate\Http\JsonResponse;

class QuizResultController extends Controller
{
    public function index(): JsonResponse
    {
        $quizResults = QuizResult::all();
        return response()->json(['data' => $quizResults]);
    }

    public function show($id): JsonResponse
    {
        $quizResult = QuizResult::findOrFail($id);
        return response()->json(['data' => $quizResult]);
    }

    public function store(StoreQuizResultRequest $request): JsonResponse
    {
        $quizResult = QuizResult::create($request->validated());
        return response()->json(['message' => 'Quiz result created successfully', 'data' => $quizResult], 201);
    }

    public function update(UpdateQuizResultRequest $request, $id): JsonResponse
    {
        $quizResult = QuizResult::findOrFail($id);
        $quizResult->update($request->validated());
        return response()->json(['message' => 'Quiz result updated successfully', 'data' => $quizResult]);
    }

    public function destroy($id): JsonResponse
    {
        $quizResult = QuizResult::findOrFail($id);
        $quizResult->delete();
        return response()->json(['message' => 'Quiz result deleted successfully']);
    }
}