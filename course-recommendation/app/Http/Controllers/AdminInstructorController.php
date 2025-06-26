<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminInstructorController extends Controller
{
  public function getSummary(Request $request)
{
    $month = $request->query('month'); // Ví dụ: 6
    $year = $request->query('year');   // Ví dụ: 2025

    $query = DB::table('instructors as i')
        ->leftJoin('courses as c', 'c.instructor_id', '=', 'i.id')
        ->leftJoin('enrollments as e', 'e.course_id', '=', 'c.id')
        ->leftJoin('reviews as r', 'r.course_id', '=', 'c.id')
        ->leftJoin('reports as rp', 'rp.course_id', '=', 'c.id')
        ->leftJoin('revenue_distributions as rd', 'rd.instructor_id', '=', 'i.id')
        ->leftJoin('revenue_sessions as rs', 'rd.revenue_session_id', '=', 'rs.id')
        ->select(
            'i.id as instructor_id',
            'i.name',
            DB::raw('COALESCE(SUM(rd.instructor_share), 0) as total_revenue'),
            DB::raw('COUNT(DISTINCT c.id) as total_courses'),
            DB::raw('COUNT(DISTINCT e.user_id) as total_students'),
            DB::raw('ROUND(COALESCE(AVG(r.rating), 0), 2) as average_rating'),
            DB::raw('COUNT(DISTINCT rp.id) as total_reports')
        );

    // Lọc theo tháng và năm nếu có
    if ($month) {
        $query->where('rs.month', '=', $month);
    }

    if ($year) {
        $query->where('rs.year', '=', $year);
    }

    $instructors = $query->groupBy('i.id', 'i.name')->get();

    return response()->json($instructors);
}
public function getInstructorDetail(Request $request, $id)
{
    $month = $request->query('month');
    $year = $request->query('year');

    $instructor = DB::table('instructors')->where('id', $id)->first();

    if (!$instructor) {
        return response()->json(['message' => 'Instructor not found'], 404);
    }

    $query = DB::table('courses as c')
        ->leftJoin('enrollments as e', 'e.course_id', '=', 'c.id')
        ->leftJoin('reviews as r', 'r.course_id', '=', 'c.id')
        ->leftJoin('reports as rp', 'rp.course_id', '=', 'c.id')
        ->leftJoin('revenue_distributions as rd', function ($join) {
            $join->on('rd.course_id', '=', 'c.id');
        })
        ->leftJoin('revenue_sessions as rs', 'rd.revenue_session_id', '=', 'rs.id')
        ->select(
            'c.id',
            'c.course_name',
            DB::raw('COUNT(DISTINCT e.user_id) as total_students'),
            DB::raw('ROUND(COALESCE(AVG(r.rating), 0), 2) as average_rating'),
            DB::raw('COUNT(DISTINCT rp.id) as total_reports'),
            DB::raw('COALESCE(SUM(rd.instructor_share), 0) as revenue')
        )
        ->where('c.instructor_id', $id);

    if ($month) {
        $query->where('rs.month', $month);
    }

    if ($year) {
        $query->where('rs.year', $year);
    }

    $courses = $query->groupBy('c.id', 'c.course_name')->get();

    return response()->json([
        'instructor_id' => $instructor->id,
        'name' => $instructor->name,
        'courses' => $courses
    ]);
}


}
