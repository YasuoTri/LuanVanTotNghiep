<?php

namespace App\Http\Controllers;

use App\Models\RevenueDistribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RevenueDistribute extends Controller
{
    
//     public function getInstructorRevenueDistributions(Request $request)
// {
//     $instructorId = Auth::user()->instructor->id;

//     $query = RevenueDistribution::with(['revenueSession', 'course'])
//         ->where('instructor_id', $instructorId);

//     // Filter theo status (pending/completed/failed)
//     if ($request->has('status')) {
//         $query->where('status', $request->input('status'));
//     }

//     // Filter theo năm
//     if ($request->has('year')) {
//         $query->whereHas('revenueSession', function ($q) use ($request) {
//             $q->where('year', $request->input('year'));
//         });
//     }

//     // Filter theo tháng
//     if ($request->has('month')) {
//         $query->whereHas('revenueSession', function ($q) use ($request) {
//             $q->where('month', $request->input('month'));
//         });
//     }

//     // Filter theo tên khoá học (LIKE hoặc ID)
//     if ($request->has('course')) {
//         $query->whereHas('course', function ($q) use ($request) {
//             $q->where('course_name', 'like', '%' . $request->input('course') . '%');
//         });
//     }

//     // Lấy và định dạng dữ liệu
//     $distributions = $query->get()->map(function ($dist) {
//         return [
//             'month' => $dist->revenueSession->month,
//             'year' => $dist->revenueSession->year,
//             'course_name' => $dist->course->course_name,
//             'revenue_amount' => round($dist->instructor_share*100/70,2),
//             'instructor_share' => round($dist->instructor_share,2),
//             'status' => $dist->status,
//             'transaction_code' => $dist->transaction_code,
//             'created_at' => $dist->created_at,
//         ];
//     })->sortByDesc('year')->sortByDesc('month')->values();

//     return response()->json([
//         'message' => 'Instructor revenue distributions',
//         'data' => $distributions
//     ]);
// }
   public function getInstructorRevenueDistributions(Request $request)
    {
        $user = Auth::user();

        // Check if user is an instructor
        if (!$user->instructor) {
            return response()->json([
                'message' => 'User is not an instructor',
                'data' => []
            ], 403);
        }

        $instructorId = $user->instructor->id;

        // Build query for revenue distributions
        $query = RevenueDistribution::with(['revenueSession', 'course'])
            ->whereHas('course', function ($q) use ($instructorId) {
                $q->where('instructor_id', $instructorId); // Filter by instructor_id in courses table
            });

        // Filter by status (pending/completed/failed)
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by year
        if ($request->has('year')) {
            $query->whereHas('revenueSession', function ($q) use ($request) {
                $q->where('year', $request->input('year'));
            });
        }

        // Filter by month
        if ($request->has('month')) {
            $query->whereHas('revenueSession', function ($q) use ($request) {
                $q->where('month', $request->input('month'));
            });
        }

        // Filter by course name (LIKE)
        if ($request->has('course')) {
            $query->whereHas('course', function ($q) use ($request) {
                $q->where('course_name', 'like', '%' . $request->input('course') . '%');
            });
        }

        // Retrieve and format data
        $distributions = $query->get()->map(function ($dist) {
            return [
                'month' => $dist->revenueSession->month,
                'year' => $dist->revenueSession->year,
                'course_name' => $dist->course->course_name,
                'revenue_amount' => round($dist->instructor_share * 100 / 70, 2),
                'instructor_share' => round($dist->instructor_share, 2),
                'status' => $dist->status,
                'transaction_code' => $dist->transaction_code,
                'created_at' => $dist->created_at,
            ];
        })->sortByDesc('year')->sortByDesc('month')->values();

        return response()->json([
            'message' => 'Instructor revenue distributions',
            'data' => $distributions
        ]);
    }
}

