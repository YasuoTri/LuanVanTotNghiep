<?php

namespace App\Http\Controllers;

use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Models\Course;
use App\Models\Review;
use Illuminate\Http\Request;;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Container\Attributes\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log as FacadesLog;

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
        $review->fill($request->validated());
        if (!$review->isDirty()) {
            return response()->json(['message' => 'No changes detected'], 200);
        }
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

        // if ($progressPercent < 30) {
        //     return response()->json([
        //         'message' => 'Bạn cần hoàn thành ít nhất 30% khóa học để đánh giá.'
        //     ], 403);
        // }
          // Kiểm tra từ ngữ phản cảm nếu có comment
        if ($request->comment) {
            $response = Http::asForm()->post('https://neutrinoapi.net/bad-word-filter', [
                'user-id' => 'phamminhtri26102003',
                'api-key' => '2pHRUxWhHr0hVLDVGR8BPmF7lTGNPPSTeFTiVPsrHgIRnDXM',
                'content' => $request->comment,
                'censor-character' => '*' // Optional: dùng để thay từ vi phạm nếu cần
            ]);
            FacadesLog::info('Bad word filter response', ['response' => $response->body()]); 
            if ($response->successful()) {
                $result = $response->json();
                if ($result['is-bad']) {
                    return response()->json([
                        'message' => 'The comment contain inapproriate content'
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => "Unable to check content at this time. Please try again later."
                ], 500);
            }
        }
        $comment = strtolower($request->comment);
        $badWords = json_decode(file_get_contents(storage_path('app/vi_badwords.json')), true);

        foreach ($badWords as $category => $words) {
            foreach ($words as $word) {
                if (stripos($comment, $word) !== false) {
                    return response()->json([
                        'message' => 'Your comment contains inappropriate language: '
                    ], 422);
                }
            }
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
   public function commentStatistics(Request $request, $courseId): JsonResponse
{
    $user = Auth::user();

    // 1. Kiểm tra quyền instructor
    $course = Course::where('id', $courseId)
        ->where('instructor_id', $user->instructor->id ?? null)
        ->first();

    if (!$course) {
        return response()->json([
            'status' => 'error',
            'message' => 'Bạn không có quyền truy cập khóa học này.'
        ], 403);
    }

    // 2. Nhận tham số lọc types (nếu có)
    $types = $request->query('types'); // ?types[]=instructor...

    // Danh sách loại mặc định trong hệ thống
    $allTypes = ['content_quality', 'instructor', 'not_interested'];

    // Nếu không truyền types → dùng toàn bộ loại
    $selectedTypes = (is_array($types) && count($types)) ? $types : $allTypes;

    // 3. Lấy danh sách comment theo course & feedback_type
    $query = Review::where('course_id', $course->id)
        ->whereIn('feedback_type', $selectedTypes)
        ->orderByDesc('created_at');

    $reviews = $query->get();

    // 4. Group comment theo feedback_type
    $groupedComments = collect($selectedTypes)->mapWithKeys(function ($type) use ($reviews) {
        $comments = $reviews->where('feedback_type', $type)->values()->map(function ($review) {
            return [
                'id'         => $review->id,
                'user_id'    => $review->user_id,
                'comment'    => $review->comment,
                'created_at' => $review->created_at->toDateTimeString(),
            ];
        });

        return [$type => $comments];
    });

    // 5. Đếm số lượng comment theo loại
    $counts = collect($selectedTypes)->mapWithKeys(function ($type) use ($reviews) {
        return [$type => $reviews->where('feedback_type', $type)->count()];
    });

    return response()->json([
        'status' => 'success',
        'message' => 'Thống kê và danh sách comment theo loại',
        'data' => [
            'counts'   => $counts,
            'comments' => $groupedComments
        ]
    ]);
}

}