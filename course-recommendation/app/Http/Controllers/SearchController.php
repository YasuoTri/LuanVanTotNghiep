<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class SearchController extends Controller
{
   public function search(Request $request): JsonResponse
    {
        $query = Course::query()->where('status', 'approved');

        // Keyword search
        if ($keyword = $request->query('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('course_name', 'like', "%{$keyword}%")
                  ->orWhere('course_description', 'like', "%{$keyword}%")
                  ->orWhere('skills', 'like', "%{$keyword}%");
            });
        }

        // Category filter
        if ($categories = $request->query('categories')) {
            $categoryIds = is_array($categories) ? $categories : explode(',', $categories);
            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        // Difficulty filter
        if ($difficulty = $request->query('difficulty')) {
            $query->where('difficulty_level', $difficulty);
        }

        // Rating filter
        if ($minRating = $request->query('min_rating')) {
            $query->where('course_rating', '>=', $minRating);
        }

        // Price filter
        if ($request->has('min_price') || $request->has('max_price')) {
            $minPrice = $request->query('min_price', 0);
            $maxPrice = $request->query('max_price', PHP_INT_MAX);
            $query->whereBetween('price', [$minPrice, $maxPrice]);
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'course_rating');
        $sortOrder = $request->query('sort_order', 'desc');
        $allowedSorts = ['course_rating', 'price', 'enrollments'];
        if (in_array($sortBy, $allowedSorts)) {
            if ($sortBy === 'enrollments') {
                $query->withCount('enrollments')->orderBy('enrollments_count', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        }

        // Pagination
        $perPage = $request->query('per_page', 10);
        $courses = $query->with(['instructors', 'reviews', 'categories'])
                        ->paginate($perPage);

        return response()->json([
            'message' => 'Courses retrieved successfully',
            'data' => $courses
        ]);
    }
}