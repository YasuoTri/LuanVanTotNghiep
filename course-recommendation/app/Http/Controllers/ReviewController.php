<?php

namespace App\Http\Controllers;

use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $reviews = Review::all();
        return response()->json(['data' => $reviews]);
    }

    public function show($id): JsonResponse
    {
        $review = Review::findOrFail($id);
        return response()->json(['data' => $review]);
    }

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $review = Review::create($request->validated());
        return response()->json(['message' => 'Review created successfully', 'data' => $review], 201);
    }

    public function update(UpdateReviewRequest $request, $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->update($request->validated());
        return response()->json(['message' => 'Review updated successfully', 'data' => $review]);
    }

    public function destroy($id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->delete();
        return response()->json(['message' => 'Review deleted successfully']);
    }
}