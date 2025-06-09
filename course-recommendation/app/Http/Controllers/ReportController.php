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
        $reports = Report::with(['user', 'course'])
            ->where('status', $request->status ?? 'pending')
            ->get();

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
            'admin_id' => Auth::id(),
            'admin_notes' => $request->admin_notes,
            'reviewed_at' => now(),
        ]);

        // Xử lý khóa học dựa trên hành động
        $course = Course::find($report->course_id);
        if ($request->action === 'ban') {
            $course->update(['status' => 'banned']);
        } elseif ($request->action === 'delete') {
            $course->delete(); // Soft delete
        }

        return response()->json(['message' => 'Report handled successfully']);
    }
}