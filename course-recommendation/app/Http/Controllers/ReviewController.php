<?php

namespace App\Http\Controllers;

use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Models\Review;
use Illuminate\Http\Request;;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $reviews = Review::paginate(10);
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
     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments =Review::onlyTrashed()->paginate(10);
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment =Review::onlyTrashed()->findOrFail($id);
        $enrollment->restore();
        return response()->json(['message' => 'Review restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        $enrollment =Review::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        return response()->json(['message' => 'Review permanently deleted'], 200);
    }

// Reviews
function search(Request $request) {
    return Review::query()
        ->when($request->filled('course_id'), fn($q) => $q->where('course_id', $request->input('course_id')))
        ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->input('user_id')))
        ->when($request->filled('rating'), fn($q) => $q->where('rating', $request->input('rating')))
        ->paginate(10);
}

public function storeStudent(Request $request, $course_id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string'
        ]);

        $user_id = Auth::id();

        // Kiểm tra tiến độ học
        $totalLessons = Lesson::where('course_id', $course_id)->count();

        $completed = LessonProgress::whereHas('lesson', function ($q) use ($course_id) {
            $q->where('course_id', $course_id);
        })->where('user_id', $user_id)->where('status', 'completed')->count();

        $progressPercent = $totalLessons > 0 ? ($completed / $totalLessons) * 100 : 0;

        if ($progressPercent < 30) {
            return response()->json([
                'message' => 'Bạn cần hoàn thành ít nhất 30% khóa học để đánh giá.'
            ], 403);
        }

        // Nếu đã review trước đó, cập nhật
        $review = Review::updateOrCreate(
            ['user_id' => $user_id, 'course_id' => $course_id],
            ['rating' => $request->rating, 'comment' => $request->comment]
        );

        return response()->json([
            'message' => 'Đánh giá thành công.',
            'review' => $review
        ]);
    }
}