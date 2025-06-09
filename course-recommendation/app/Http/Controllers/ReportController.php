<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    // Người dùng gửi báo cáo
    public function submitReport(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'reason' => 'required|string|max:1000',
        ]);

        $user = Auth::user();
        if (!in_array($user->role, ['student', 'instructor'])) {
            return response()->json(['message' => 'Only students or instructors can submit reports'], 403);
        }

        Report::create([
            'user_id' => $user->id,
            'course_id' => $request->course_id,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Report submitted successfully']);
    }

    // Admin xem danh sách báo cáo
    public function viewReports(Request $request)
    {
        $reports = Report::with(['user', 'course','user.student','user.instructor','course.instructors'])
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate(10);


        return response()->json($reports);
    }

    // Admin xử lý báo cáo
    public function handleReport(Request $request, Report $report)
    {
            $request->validate([
            'status' => 'required|in:reviewed,resolved',
            'admin_notes' => 'nullable|string|max:1000',
            'action' => 'nullable|in:ban,delete,ignore',
        ]);

        $report->update([
            'status' => $request->status,
            'admin_id' => Auth::user()->admin->id, // Giả sử admin đã đăng nhập
            'admin_notes' => $request->admin_notes,
            'reviewed_at' => now(),
        ]);

        // Xử lý khóa học dựa trên hành động
        $course = Course::find($report->course_id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        if ($request->action === 'ban') {
            $course->update(['status' => 'banned']);
        } elseif ($request->action === 'delete') {
            $course->delete(); // Soft delete
        }

        return response()->json(['message' => 'Report handled successfully']);
    }
}