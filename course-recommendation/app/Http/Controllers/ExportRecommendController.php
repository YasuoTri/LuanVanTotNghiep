<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class ExportRecommendController extends Controller
{
    public function exportAndSend()
    {
        $exportDir = storage_path('app/exports');
        if (!File::exists($exportDir)) {
            File::makeDirectory($exportDir, 0755, true);
        }

        // 1. Export: courses.csv
        $coursesPath = $exportDir . '/courses.csv';
        $courseFile = fopen($coursesPath, 'w');
        fputcsv($courseFile, [
            'course_id', 'course_title', 'url', 'is_paid', 'price',
            'num_subscribers', 'num_reviews', 'num_lectures',
            'level', 'content_duration', 'published_timestamp', 'subject'
        ]);

        $courses = DB::table('courses')
            ->leftJoin('course_category', 'courses.id', '=', 'course_category.course_id')
            ->leftJoin('categories', 'course_category.category_id', '=', 'categories.id')
            ->leftJoin(DB::raw('(SELECT course_id, COUNT(*) as num_lectures, SUM(duration) as total_duration FROM lessons GROUP BY course_id) as lesson_stats'),
                'courses.id', '=', 'lesson_stats.course_id')
            ->leftJoin(DB::raw('(SELECT course_id, COUNT(*) as num_reviews FROM reviews GROUP BY course_id) as review_stats'),
                'courses.id', '=', 'review_stats.course_id')
            ->leftJoin(DB::raw('(SELECT course_id, COUNT(*) as num_subscribers FROM enrollments GROUP BY course_id) as enroll_stats'),
                'courses.id', '=', 'enroll_stats.course_id')
            ->select([
                'courses.id as course_id',
                'courses.course_name as course_title',
                'courses.course_url as url',
                'courses.price',
                DB::raw("CASE WHEN courses.price > 0 THEN 'True' ELSE 'False' END as is_paid"),
                DB::raw('IFNULL(enroll_stats.num_subscribers, 0) as num_subscribers'),
                DB::raw('IFNULL(review_stats.num_reviews, 0) as num_reviews'),
                DB::raw('IFNULL(lesson_stats.num_lectures, 0) as num_lectures'),
                'courses.difficulty_level as level',
                DB::raw('IFNULL(lesson_stats.total_duration, 0) as content_duration'),
                'courses.created_at as published_timestamp',
                'categories.name as subject'
            ])
            ->where('courses.status', '=', 'approved')
            ->get();

        foreach ($courses as $course) {
            fputcsv($courseFile, [
                $course->course_id,
                $course->course_title,
                $course->url,
                $course->is_paid,
                $course->price,
                $course->num_subscribers,
                $course->num_reviews,
                $course->num_lectures,
                $course->level,
                $course->content_duration,
                $course->published_timestamp,
                $course->subject ?? ''
            ]);
        }

        fclose($courseFile);

        // 2. Export: enrollments.csv
        $enrollmentsPath = $exportDir . '/enrollments.csv';
        $enrollFile = fopen($enrollmentsPath, 'w');
        fputcsv($enrollFile, ['user_id', 'course_id', 'comment', 'rating', 'enrolled_at']);

        $records = DB::table('enrollments')
        ->join('reviews', function ($join) {
            $join->on('enrollments.user_id', '=', 'reviews.user_id')
                ->on('enrollments.course_id', '=', 'reviews.course_id');
        })
        ->select(
            'enrollments.user_id',
            'enrollments.course_id',
            'reviews.comment',
            'reviews.rating',
            'enrollments.enrolled_at'
        )
        ->get();


        foreach ($records as $row) {
            fputcsv($enrollFile, [
                $row->user_id,
                $row->course_id,
                $row->comment ?? '',
                $row->rating ?? '',
                $row->enrolled_at,
            ]);
        }

        fclose($enrollFile);

        // 3. Gửi cả 2 file CSV sang API Python
        $response = Http::attach(
            'courses_file', file_get_contents($coursesPath), 'courses.csv'
        )->attach(
            'enrollments_file', file_get_contents($enrollmentsPath), 'enrollments.csv'
        )->post('http://127.0.0.1:9000/recommend/update-model');

        return response()->json([
            'message' => '✔️ Export and send to Python API successfully!',
            'python_response' => $response->json()
        ]);
    }
}
