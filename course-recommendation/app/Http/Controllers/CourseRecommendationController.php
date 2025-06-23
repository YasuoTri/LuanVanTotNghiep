<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Category;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CourseRecommendationController extends Controller
{
    /**
     * Get course recommendations similar to Udemy homepage
     */
    public function getRecommendations(Request $request)
    {
        $user = Auth::user();
        $recommendations = [];

        try {
            // 1. Recommended for you (based on user's categories and enrollments)
            $recommendations[] = [
                'title' => 'Recommended for you',
                'courses' => $this->getRecommendedForUser($user)
            ];

            // 2. Because you searched for (based on user's search history or popular searches)
            $searchTerm = $request->get('search_term', 'AI Agents');
            $recommendations[] = [
                'title' => "Because you searched for \"{$searchTerm}\"",
                'courses' => $this->getCoursesBySearch($searchTerm)
            ];

            // 3. Learners are viewing
            $recommendations[] = [
                'title' => 'Learners are viewing',
                'courses' => $this->getMostViewedCourses()
            ];

            // 4. Short and sweet courses for you
            $recommendations[] = [
                'title' => 'Short and sweet courses for you',
                'courses' => $this->getShortCourses()
            ];

            // 5. What people who learn [skill] take next
            $userSkill = $this->getUserPrimarySkill($user);
            $recommendations[] = [
                'title' => "What people who learn {$userSkill} take next",
                'courses' => $this->getNextCourses($userSkill)
            ];

            // 6. Newest courses in [category]
            $popularCategory = $this->getPopularCategory();
            $recommendations[] = [
                'title' => "Newest courses in {$popularCategory}",
                'courses' => $this->getNewestCoursesByCategory($popularCategory)
            ];

            // 7. Top rated courses
            $recommendations[] = [
                'title' => 'Top rated courses',
                'courses' => $this->getTopRatedCourses()
            ];

            // 8. Trending now
            $recommendations[] = [
                'title' => 'Trending now',
                'courses' => $this->getTrendingCourses()
            ];

            return response()->json([
                'success' => true,
                'data' => $recommendations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recommendations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recommended courses for user based on their categories and history
     */
    private function getRecommendedForUser($user)
    {
        if (!$user) {
            return $this->getPopularCourses();
        }

        $student = Student::where('user_id', $user->id)->first();
        if (!$student) {
            return $this->getPopularCourses();
        }

        // Get user's interested categories
        $categoryIds = $student->categories()->pluck('categories.id')->toArray();
        
        if (empty($categoryIds)) {
            return $this->getPopularCourses();
        }

        return Course::whereHas('categories', function($query) use ($categoryIds) {
                $query->whereIn('categories.id', $categoryIds);
            })
            ->where('status', 'approved')
            ->whereNotIn('id', function($query) use ($user) {
                $query->select('course_id')
                      ->from('enrollments')
                      ->where('user_id', $user->id);
            })
            ->orderBy('course_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->with(['instructor', 'categories'])
            ->get();
    }

    /**
     * Get courses based on search term
     */
    private function getCoursesBySearch($searchTerm)
    {
        return Course::where('status', 'approved')
            ->where(function($query) use ($searchTerm) {
                $query->where('course_name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('course_description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('skills', 'LIKE', "%{$searchTerm}%");
            })
            ->orderBy('course_rating', 'desc')
            ->limit(10)
            ->with(['instructor', 'categories'])
            ->get();
    }

    /**
     * Get most viewed courses (based on enrollments)
     */
    private function getMostViewedCourses()
    {
        return Course::select('courses.*', DB::raw('COUNT(enrollments.id) as enrollment_count'))
            ->leftJoin('enrollments', 'courses.id', '=', 'enrollments.course_id')
            ->where('courses.status', 'approved')
            ->groupBy('courses.id')
            ->orderBy('enrollment_count', 'desc')
            ->orderBy('courses.course_rating', 'desc')
            ->limit(10)
            ->with(['instructor', 'categories'])
            ->get();
    }

    /**
     * Get short courses (assuming courses with fewer lessons are shorter)
     */
    private function getShortCourses()
    {
        return Course::select('courses.*', DB::raw('COUNT(lessons.id) as lesson_count'))
            ->leftJoin('lessons', 'courses.id', '=', 'lessons.course_id')
            ->where('courses.status', 'approved')
            ->groupBy('courses.id')
            ->having('lesson_count', '<=', 5)
            ->orderBy('courses.course_rating', 'desc')
            ->limit(10)
            ->with(['instructor', 'categories'])
            ->get();
    }

    /**
     * Get user's primary skill based on enrollments
     */
    private function getUserPrimarySkill($user)
    {
        if (!$user) {
            return 'Programming';
        }

        $primaryCategory = DB::table('enrollments')
            ->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->join('course_category', 'courses.id', '=', 'course_category.course_id')
            ->join('categories', 'course_category.category_id', '=', 'categories.id')
            ->where('enrollments.user_id', $user->id)
            ->select('categories.name', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.name')
            ->orderBy('count', 'desc')
            ->first();

        return $primaryCategory ? $primaryCategory->name : 'Programming';
    }

    /**
     * Get courses that people take after learning a specific skill
     */
    private function getNextCourses($skill)
    {
        // Find users who have courses in this skill category
        $userIds = DB::table('enrollments')
            ->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->join('course_category', 'courses.id', '=', 'course_category.course_id')
            ->join('categories', 'course_category.category_id', '=', 'categories.id')
            ->where('categories.name', 'LIKE', "%{$skill}%")
            ->pluck('enrollments.user_id')
            ->unique();

        if ($userIds->isEmpty()) {
            return $this->getPopularCourses();
        }

        // Find what other courses these users enrolled in
        return Course::select('courses.*', DB::raw('COUNT(enrollments.id) as popularity'))
            ->join('enrollments', 'courses.id', '=', 'enrollments.course_id')
            ->whereIn('enrollments.user_id', $userIds)
            ->where('courses.status', 'approved')
            ->whereNotExists(function($query) use ($skill) {
                $query->select(DB::raw(1))
                      ->from('course_category')
                      ->join('categories', 'course_category.category_id', '=', 'categories.id')
                      ->whereColumn('course_category.course_id', 'courses.id')
                      ->where('categories.name', 'LIKE', "%{$skill}%");
            })
            ->groupBy('courses.id')
            ->orderBy('popularity', 'desc')
            ->orderBy('courses.course_rating', 'desc')
            ->limit(10)
            ->with(['instructor', 'categories'])
            ->get();
    }

    /**
     * Get popular category name
     */
    private function getPopularCategory()
    {
        $category = DB::table('course_category')
            ->join('categories', 'course_category.category_id', '=', 'categories.id')
            ->join('courses', 'course_category.course_id', '=', 'courses.id')
            ->where('courses.status', 'approved')
            ->select('categories.name', DB::raw('COUNT(*) as count'))
            ->groupBy('categories.name')
            ->orderBy('count', 'desc')
            ->first();

        return $category ? $category->name : 'Data Science';
    }

    /**
     * Get newest courses by category
     */
    private function getNewestCoursesByCategory($categoryName)
    {
        return Course::whereHas('categories', function($query) use ($categoryName) {
                $query->where('name', 'LIKE', "%{$categoryName}%");
            })
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->with(['instructor', 'categories'])
            ->get();
    }

    /**
     * Get top rated courses
     */
    private function getTopRatedCourses()
    {
        return Course::where('status', 'approved')
            ->where('course_rating', '>=', 4.0)
            ->orderBy('course_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->with(['instructor', 'categories'])
            ->get();
    }

    /**
     * Get trending courses (based on recent enrollments)
     */
    private function getTrendingCourses()
    {
        $lastWeek = Carbon::now()->subWeek();

        return Course::select('courses.*', DB::raw('COUNT(enrollments.id) as recent_enrollments'))
            ->leftJoin('enrollments', function($join) use ($lastWeek) {
                $join->on('courses.id', '=', 'enrollments.course_id')
                     ->where('enrollments.created_at', '>=', $lastWeek);
            })
            ->where('courses.status', 'approved')
            ->groupBy('courses.id')
            ->orderBy('recent_enrollments', 'desc')
            ->orderBy('courses.course_rating', 'desc')
            ->limit(10)
            ->with(['instructor', 'categories'])
            ->get();
    }

    /**
     * Get popular courses as fallback
     */
    private function getPopularCourses()
    {
        return Course::where('status', 'approved')
            ->orderBy('course_rating', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->with(['instructor', 'categories'])
            ->get();
    }

    /**
     * Get specific recommendation section
     */
    public function getRecommendationSection(Request $request, $section)
    {
        $user = Auth::user();
        
        try {
            $courses = match($section) {
                'recommended' => $this->getRecommendedForUser($user),
                'trending' => $this->getTrendingCourses(),
                'top-rated' => $this->getTopRatedCourses(),
                'newest' => $this->getNewestCoursesByCategory($request->get('category', 'Programming')),
                'short-courses' => $this->getShortCourses(),
                'most-viewed' => $this->getMostViewedCourses(),
                default => $this->getPopularCourses()
            };

            return response()->json([
                'success' => true,
                'data' => $courses
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recommendation section',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
