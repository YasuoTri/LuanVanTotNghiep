<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ExportController extends Controller
{
   public function saveCoursesToFile()
{
    $path = storage_path('app/exports/courses.csv');
    $dir = dirname($path);

    // Tạo thư mục nếu chưa tồn tại
    if (!File::exists($dir)) {
        File::makeDirectory($dir, 0755, true);
    }

    $handle = fopen($path, 'w');
    fputcsv($handle, ['title', 'level', 'category']);

    $courses = DB::table('courses')
        ->join('course_category', 'courses.id', '=', 'course_category.course_id')
        ->join('categories', 'course_category.category_id', '=', 'categories.id')
        ->select('courses.course_name as title', 'courses.difficulty_level as level', 'categories.name as category')
        ->where('courses.status', '=', 'approved')
        ->get();

    foreach ($courses as $course) {
        fputcsv($handle, [$course->title, $course->level, $course->category]);
    }

    fclose($handle);
//   return response()->download(storage_path("app/{$path}"));
    return response()->json([
        'message' => '✅ courses.csv đã được lưu.',
        'path' => $path
    ]);
}

    public function saveEnrollmentsToFile()
    {
        $path = 'exports/enrollments.csv';
        Storage::makeDirectory('exports');

        $handle = fopen(storage_path("app/{$path}"), 'w');
        fputcsv($handle, ['user_id', 'course_id', 'comment', 'rating', 'enrolled_at']);

        $data = DB::table('enrollments')
            ->leftJoin('reviews', function ($join) {
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

        foreach ($data as $row) {
            fputcsv($handle, [
                $row->user_id,
                $row->course_id,
                $row->comment ?? '',
                $row->rating ?? '',
                $row->enrolled_at,
            ]);
        }

        fclose($handle);

        return response()->json(['message' => '✅ enrollments.csv đã được lưu.', 'path' => storage_path("app/{$path}")]);
    }
}
