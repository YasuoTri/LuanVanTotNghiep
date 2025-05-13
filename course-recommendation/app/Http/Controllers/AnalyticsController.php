<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use App\Models\Interaction;
use App\Models\Enrollment;
use App\Models\QuizResult;
use App\Models\ForumPost;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
   public function courseAnalytics(Request $request, $course_id): JsonResponse
    {
        $user = Auth::user();
        $course = Course::findOrFail($course_id);

        // Verify instructor ownership
      $isInstructor = $course->instructors()
    ->where('user_id', $user->id)
    ->exists();


        if (!$isInstructor) {
            return response()->json(['message' => 'You are not an instructor for this course'], 403);
        }

        // Enrollment stats
        $enrollments = $course->enrollments()->count();
        $completions = $course->enrollments()->where('status', 'completed')->count();
        $completionRate = $enrollments > 0 ? ($completions / $enrollments) * 100 : 0;

        // Quiz performance
        $quizScores = QuizResult::whereHas('quiz.lesson', function ($q) use ($course_id) {
            $q->where('course_id', $course_id);
        })->selectRaw('AVG(score) as average_score, MAX(score) as highest_score, MIN(score) as lowest_score')
          ->first();

        // Forum activity
        $forumPosts = ForumPost::where('course_id', $course_id)->count();

        // Engagement metrics
        $interactions = Interaction::where('course_id', $course_id)
            ->selectRaw('
                SUM(nplay_video) as total_video_plays,
                SUM(nforum_posts) as total_forum_posts,
                AVG(ndays_act) as avg_active_days,
                SUM(nevents) as total_events
            ')
            ->first();

        // Recent activity
        $recentActivity = Interaction::where('course_id', $course_id)
            ->with('user')
            ->orderBy('last_event', 'desc')
            ->take(10)
            ->get(['user_id', 'rating', 'viewed', 'explored', 'certified', 'last_event']);

        return response()->json([
            'message' => 'Course analytics retrieved successfully',
            'data' => [
                'course_id' => $course_id,
                'enrollments' => $enrollments,
                'completions' => $completions,
                'completion_rate' => round($completionRate, 2),
                'quiz_performance' => [
                    'average_score' => round($quizScores->average_score ?? 0, 2),
                    'highest_score' => $quizScores->highest_score ?? 0,
                    'lowest_score' => $quizScores->lowest_score ?? 0,
                ],
                'forum_posts' => $forumPosts,
                'engagement' => [
                    'total_video_plays' => $interactions->total_video_plays ?? 0,
                    'total_forum_posts' => $interactions->total_forum_posts ?? 0,
                    'avg_active_days' => round($interactions->avg_active_days ?? 0, 2),
                    'total_events' => $interactions->total_events ?? 0,
                ],
                'recent_activity' => $recentActivity
            ]
        ]);
    }

    public function adminCourseAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized: Only admins can access this endpoint'], 403);
        }

        $courses = Course::select('id', 'course_name', 'course_rating')
            ->withCount('enrollments')
            ->withCount(['enrollments as completed_enrollments' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->get();

        $analytics = $courses->map(function ($course) {
            $completionRate = $course->enrollments_count > 0
                ? ($course->completed_enrollments / $course->enrollments_count) * 100
                : 0;

            $interactions = Interaction::where('course_id', $course->id)
                ->selectRaw('SUM(nplay_video) as total_video_plays, SUM(nevents) as total_events')
                ->first();

            return [
                'course_id' => $course->id,
                'course_name' => $course->course_name,
                'enrollments' => $course->enrollments_count,
                'completion_rate' => round($completionRate, 2),
                'average_rating' => round($course->course_rating, 2),
                'total_video_plays' => $interactions->total_video_plays ?? 0,
                'total_events' => $interactions->total_events ?? 0,
            ];
        });

        $totalEnrollments = Enrollment::count();
        $totalCompletions = Enrollment::where('status', 'completed')->count();
        $totalUsers = User::count();
        $activeUsers = Interaction::distinct('user_id')->count('user_id');

        return response()->json([
            'message' => 'Platform analytics retrieved successfully',
            'data' => [
                'courses' => $analytics,
                'platform_stats' => [
                    'total_enrollments' => $totalEnrollments,
                    'total_completions' => $totalCompletions,
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                ]
            ]
        ]);
    }

    public function adminUserAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized: Only admins can access this endpoint'], 403);
        }

        $users = User::where('role', 'student')
            ->select('id', 'final_cc_cname_DI')
            ->withCount('enrollments')
            ->withCount(['enrollments as completed_enrollments' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->get();

        $analytics = $users->map(function ($user) {
            $interactions = Interaction::where('user_id', $user->id)
                ->selectRaw('
                    SUM(nplay_video) as total_video_plays,
                    SUM(nforum_posts) as total_forum_posts,
                    AVG(ndays_act) as avg_active_days
                ')
                ->first();

            return [
                'user_id' => $user->id,
                'name' => $user->final_cc_cname_DI,
                'enrollments' => $user->enrollments_count,
                'completed_courses' => $user->completed_enrollments,
                'engagement' => [
                    'total_video_plays' => $interactions->total_video_plays ?? 0,
                    'total_forum_posts' => $interactions->total_forum_posts ?? 0,
                    'avg_active_days' => round($interactions->avg_active_days ?? 0, 2),
                ]
            ];
        });

        return response()->json([
            'message' => 'User analytics retrieved successfully',
            'data' => $analytics
        ]);
    }
}