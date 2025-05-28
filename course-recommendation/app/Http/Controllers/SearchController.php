<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;


class SearchController extends Controller
{
//    public function search(Request $request): JsonResponse
//     {
//         $query = Course::query()->where('status', 'approved');

//         // Keyword search
//         if ($keyword = $request->query('keyword')) {
//             $query->where(function ($q) use ($keyword) {
//                 $q->where('course_name', 'like', "%{$keyword}%")
//                   ->orWhere('course_description', 'like', "%{$keyword}%")
//                   ->orWhere('skills', 'like', "%{$keyword}%");
//             });
//         }

//         // Category filter
//         if ($categories = $request->query('categories')) {
//             $categoryIds = is_array($categories) ? $categories : explode(',', $categories);
//             $query->whereHas('categories', function ($q) use ($categoryIds) {
//                 $q->whereIn('categories.id', $categoryIds);
//             });
//         }

//         // Difficulty filter
//         if ($difficulty = $request->query('difficulty')) {
//             $query->where('difficulty_level', $difficulty);
//         }

//         // Rating filter
//         if ($minRating = $request->query('min_rating')) {
//             $query->where('course_rating', '>=', $minRating);
//         }

//         // Price filter
//         if ($request->has('min_price') || $request->has('max_price')) {
//             $minPrice = $request->query('min_price', 0);
//             $maxPrice = $request->query('max_price', PHP_INT_MAX);
//             $query->whereBetween('price', [$minPrice, $maxPrice]);
//         }

//         // Sorting
//         $sortBy = $request->query('sort_by', 'course_rating');
//         $sortOrder = $request->query('sort_order', 'desc');
//         $allowedSorts = ['course_rating', 'price', 'enrollments'];
//         if (in_array($sortBy, $allowedSorts)) {
//             if ($sortBy === 'enrollments') {
//                 $query->withCount('enrollments')->orderBy('enrollments_count', $sortOrder);
//             } else {
//                 $query->orderBy($sortBy, $sortOrder);
//             }
//         }

//         // Pagination
//         $perPage = $request->query('per_page', 10);
//         $courses = $query->with(['instructors', 'reviews', 'categories'])
//                         ->paginate($perPage);

//         return response()->json([
//             'message' => 'Courses retrieved successfully',
//             'data' => $courses
//         ]);
//     }
/**
     * Search for approved courses with filters and additional computed fields
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
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

            // Fetch course counts per category
            $categoryCourseCounts = DB::table('course_category')
                ->join('courses', 'course_category.course_id', '=', 'courses.id')
                ->where('courses.status', 'approved')
                ->groupBy('course_category.category_id')
                ->pluck(DB::raw('count(*) as total_courses'), 'course_category.category_id')
                ->toArray();

            // Pagination
            $perPage = $request->query('per_page', 10);
            $courses = $query->with(['instructors', 'reviews', 'categories', 'lessons'])
                            ->paginate($perPage);

            // Transform the results to include additional fields
            $transformedCourses = $courses->getCollection()->map(function ($course) use ($categoryCourseCounts) {
                // Transform categories to include total_courses
                $categories = $course->categories->map(function ($category) use ($categoryCourseCounts) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
                        'total_courses' => isset($categoryCourseCounts[$category->id]) ? (int) $categoryCourseCounts[$category->id] : 0,
                    ];
                });

                return [
                    'id' => $course->id,
                    'course_name' => $course->course_name,
                    'university' => $course->university,
                    'difficulty_level' => $course->difficulty_level,
                    'course_rating' => $course->course_rating,
                    'course_url' => $course->course_url,
                    'image' => $course->image,
                    'course_description' => $course->course_description,
                    'price' => $course->price,
                    'skills' => $course->skills,
                    'status' => $course->status,
                    'created_at' => $course->created_at,
                    'updated_at' => $course->updated_at,
                    'instructors' => $course->instructors,
                    'reviews' => $course->reviews,
                    'categories' => $categories,
                    'total_lessons' => $course->lessons->count(),
                    'total_time' => $course->lessons->sum('duration'), // Sum of lesson durations in minutes
                    'number_of_ratings' => $course->reviews->count(),
                ];
            });

            // Update the paginated collection with transformed data
            $courses->setCollection($transformedCourses);

            return response()->json([
                'message' => 'Courses retrieved successfully',
                'data' => $courses
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving courses',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}