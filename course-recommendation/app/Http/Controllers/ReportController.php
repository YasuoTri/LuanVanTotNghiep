<?php

namespace App\Http\Controllers;

use App\Mail\ReportHandledNotification;
use App\Models\Report;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\ForumPost;
use App\Models\Question;
use App\Models\User;
use App\Models\Violation;
use App\Notifications\ViolationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    // Người dùng gửi báo cáo
    public function submitReport(Request $request)
    {
        $userId = Auth::user()->id;
        
         if ($request->reason) {
            $response = Http::asForm()->post('https://neutrinoapi.net/bad-word-filter', [
                'user-id' => 'phamminhtri26102003',
                'api-key' => '2pHRUxWhHr0hVLDVGR8BPmF7lTGNPPSTeFTiVPsrHgIRnDXM',
                'content' => $request->reason,
                'censor-character' => '*' // Optional: dùng để thay từ vi phạm nếu cần
            ]);
            Log::info('Bad word filter response', ['response' => $response->body()]); 
            if ($response->successful()) {
                $result = $response->json();
                if ($result['is-bad']) {
                    return response()->json([
                        'message' => 'The reason contain inapproriate content'
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => "Unable to check content at this time. Please try again later."
                ], 500);
            }
        }
        $reason = strtolower($request->reason);
        $badWords = json_decode(file_get_contents(storage_path('app/vi_badwords.json')), true);

        foreach ($badWords as $category => $words) {
            foreach ($words as $word) {
                if (stripos($reason, $word) !== false) {
                    return response()->json([
                        'message' => 'Your reason contains inappropriate language: '
                    ], 422);
                }
            }
        }
        $courseId = $request->course_id;
         $existingReport = Report::where('user_id', $userId)
        ->where('course_id', $courseId)
        ->first();

        $course=Course::find($request->course_id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        if($course->status == 'rejected' || $course->status == 'banned'|| $course->status == 'draft') {
            return response()->json(['message' => 'Can not report course have status invalid'], 403);
        }

        if ($existingReport) {
            return response()->json([
                'message' => 'You have already submitted a report for this course. Please wait for the admin to review it.'
            ], 404); // Conflict
        }
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'reason' => 'required|string|max:1000'
        ]);

        $user = Auth::user();
        if (!in_array($user->role, ['student', 'instructor'])) {
            return response()->json(['message' => 'Only students or instructors can submit reports'], 403);
        }

        Report::create([
            'user_id' => $user->id,
            'course_id' => $request->course_id,
            'reason' => $request->reason,
        ]);

        return response()->json(['message' => 'Report submitted successfully']);
    }

    // Admin xem danh sách báo cáo
public function index(Request $request)
{
    $reports = Report::with(['user', 'course', 'user.student', 'user.instructor'])
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    return response()->json($reports);
}
public function searchCourseWithReportSummary($courseId)
{
    $course = Course::with('instructors.user')->findOrFail($courseId);

    // Nhóm và đếm số lượng report theo từng loại report_type
    $reportSummary = Report::where('course_id', $course->id)
        ->select('report_type', DB::raw('COUNT(*) as total'))
        ->groupBy('report_type')
        ->get();

    return response()->json([
        'course' => $course,
        'report_summary' => $reportSummary,
    ]);
}

public function viewReports(Request $request)
{
    $reports = Report::with(['user.student', 'user.instructor', 'course'])
                     ->orderBy('created_at', 'desc')
                     ->paginate(10);

    return response()->json($reports);
}

    // Admin xử lý báo cáo
    // public function handleReport(Request $request, Report $report)
    // {
    //     $request->validate([
    //         'status' => 'required|in:reviewed,resolved',
    //         'admin_notes' => 'nullable|string|max:1000',
    //         'action' => 'nullable|in:ban,delete,ignore',
    //     ]);

    //     $report->update([
    //         'status' => $request->status,
    //         'admin_id' => Auth::user()->admin->id,
    //         'admin_notes' => $request->admin_notes,
    //         'reviewed_at' => now(),
    //     ]);

    //     // Xử lý nội dung bị báo cáo dựa trên hành động
    //     $reportable = $report->reportable;
    //     if (!$reportable) {
    //         return response()->json(['message' => 'Reported content not found'], 404);
    //     }

    //     if ($request->action === 'ban') {
    //         if ($reportable instanceof Course) {
    //             $reportable->update(['status' => 'banned']);
    //         }
    //         // Có thể thêm logic cho các loại nội dung khác nếu cần (ví dụ: ẩn bài học, bài kiểm tra)
    //     } elseif ($request->action === 'delete') {
    //         $reportable->delete(); // Soft delete
    //     }

    //     return response()->json(['message' => 'Report handled successfully']);
    // }
//    public function handleReport(Request $request, Report $report)
// {
//     // Validate input
//     $request->validate([
//         'status' => 'required|in:reviewed,resolved',
//         'admin_notes' => 'nullable|string|max:1000',
//         'action' => 'nullable|in:ban,delete,ignore',
//     ]);

//     try {
//         // Update report status
//         $report->update([
//             'status' => $request->status,
//             'admin_id' => Auth::user()->admin->id,
//             'admin_notes' => $request->admin_notes,
//             'reviewed_at' => now(),
//         ]);

//         // Get the reported course
//         $course = $report->course;
//         if (!$course) {
//             return response()->json(['message' => 'Reported course not found'], 404);
//         }

//         // Get the instructor (owner) of the course
//         $owner = $course->instructors;
//         if (!$owner) {
//             return response()->json(['message' => 'Instructor (owner) not found'], 404);
//         }

//         // Handle action on course
//         if ($request->action === 'ban') {

//         } elseif ($request->action === 'delete') {
//             $course->delete(); // soft delete
//         }
//         if ($request->action === 'ignore') {
//             // Do nothing, just ignore the report
//             return response()->json(['message' => 'Report ignored successfully']);
//         }
//         // Handle user violation if action is not "ignore"
//         if ($request->action && $request->action !== 'ignore') {
//             $this->handleUserViolation($owner->user, $report, $request->admin_notes);
//         }

//         return response()->json(['message' => 'Report handled successfully']);
//     } catch (\Exception $e) {
//         Log::error('Error in handleReport: ' . $e->getMessage());
//         return response()->json([
//             'message' => 'Error: ' . $e->getMessage(),
//             'data' => [],
//         ], 500);
//     }
// }


public function handleReport(Request $request, Report $report)
{
    try {
        // Cập nhật trạng thái report
        $report->update([
            'status' => $request->status,
            'reviewed_at' => now(),
        ]);
        Report::where('course_id', $report->course_id)
        ->where('status', 'pending')
        ->update([
            'status' =>  $request->status,
            'reviewed_at' => now(),
        ]);

        $course = $report->course;

        if (!$course) {
            return response()->json(['message' => 'Reported course not found'], 404);
        }

        // Tùy hành động
        switch ($request->action) {
            case 'ban':
                $course->update( [
                    'status' => 'banned',
                ]);
                // $this->handleUserViolation($course->instructor->user, $report, $request->admin_notes);
                break;
            case 'ignore':
            default:
                break;
        }
            // ✅ Gửi email tới instructor
        if ($course->instructors && $course->instructors->user) {
            $instructorUser = $course->instructors->user;
            Mail::to($instructorUser->email)->send(new ReportHandledNotification($course, $report, $request->status, ""));
        }

        return response()->json(['message' => 'Report handled successfully']);
    } catch (\Exception $e) {
        Log::error('Error in handleReport: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error: ' . $e->getMessage(),
            'data' => [],
        ], 500);
    }
}

public function confirmFix(Report $report)
{
    $report->update([
        'instructor_confirmed_at' => now(),
    ]);

    return response()->json(['message' => 'Instructor confirmed fix, please review again']);
}

   public function store(Request $request)
    {
    $request->validate([
        'course_id' => 'required|exists:courses,id',
        'reason' => 'required|string|max:1000'
    ]);

    $user = Auth::user();

    $course = Course::find($request->course_id);
    if (!$course) {
        return response()->json(['message' => 'Course not found'], 404);
    }

    Report::create([
        'user_id'     => $user->id,
        'course_id'   => $course->id,
        'reason'      => $request->reason,
        'status'      => 'pending',
    ]);

    return response()->json(['message' => 'Report submitted successfully']);
}

    public function show(Report $report)
    {
        $report->load(['user', 'course', 'admin']);
        return response()->json($report);
    }
    public function update(Request $request, Report $report)
    {
        $request->validate([
            // 'status' => 'required|in:pending,reviewed,resolved',
            // 'admin_notes' => 'nullable|string|max:1000',
        ]);

        $report->update([
            'status' => $request->status,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Report updated successfully']);
    }
    public function destroy(Report $report)
    {
        $report->delete();
        return response()->json(['message' => 'Report deleted successfully']);
    }
 public function cancelReport(Request $request, int $reportId)
{
    try {
        $userId = Auth::id();

        $report = Report::where('id', $reportId)
            ->where('user_id', $userId)
            ->first();

        if (!$report) {
            return response()->json([
                'message' => 'Report not found or cannot be canceled'
            ], 404);
        }
        // soft delete
        $report->delete();
        return response()->json([
            'message' => 'Report has been canceled successfully.'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while canceling the report.',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function forcedelete($id)
    {
        $report = Report::withTrashed()->findOrFail($id);
        $report->forceDelete();
        return response()->json(['message' => 'Report permanently deleted']);
    }
    public function restore($id)
    {
        $report = Report::withTrashed()->findOrFail($id);
        $report->restore();
        return response()->json(['message' => 'Report restored successfully']);
    }
    public function trashed()
    {
        $trashedReports = Report::onlyTrashed()->with(['user', 'course'])->paginate(10);
        return response()->json($trashedReports);
    }
    public function search(Request $request)
    {
        $query = Report::with(['user', 'course'])
            ->when($request->search, function ($q) use ($request) {
                $q->where('reason', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', function ($q2) use ($request) {
                      $q2->where('username', 'like', '%' . $request->search . '%');
                  });
            })
            ->orderBy('created_at', 'desc');

        $reports = $query->paginate(10);
        return response()->json($reports);
    }

public function resolveAllReports($courseId)
{
    $reports = Report::where('course_id', $courseId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'resolved',
                    'reviewed_at' => now()
                ]);

    return response()->json([
        'message' => 'All reports resolved',
        'count' => $reports
    ]);
}
public function checkThreshold($courseId)
{
    $pendingCount = Report::where('course_id', $courseId)
                    ->where('status', 'pending')
                    ->count();

    if ($pendingCount >= 10) {
        Course::where('id', $courseId)
            ->update(['status' => 'unavailable']);

        // có thể auto resolve toàn bộ luôn
        $this->resolveAllReports($courseId);

        return response()->json([
            'message' => 'Course auto-suspended due to high number of reports',
            'pending_reports' => $pendingCount
        ]);
    }

    return response()->json([
        'message' => 'Threshold not reached',
        'pending_reports' => $pendingCount
    ]);
}


public function instructorViewReportsAll()
{
    $user = Auth::user();

    if (!$user || $user->role !== 'instructor') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    // ID của bản ghi Instructor profile
    $instructorId = $user->instructor->id;

    // Chỉ lấy report của các course do instructor này sở hữu
    $reports = Report::with([
            'user',
            'user.instructor',
            'user.student',
            'course'
        ])
        ->whereHas('course', function ($q) use ($instructorId) {
            $q->where('instructor_id', $instructorId);
        })
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'message' => 'Reports retrieved',
        'reports' => $reports
    ], 200);
}


public function instructorViewReports($courseId)
{
    $user = Auth::user();

    if (!$user || $user->role !== 'instructor') {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $course = Course::where('id', $courseId)
        ->where('instructor_id', $user->instructor->id)
        ->first();

    if (!$course) {
        return response()->json(['message' => 'You do not own this course'], 403);
    }

    $reports = Report::where('course_id', $courseId)
    ->with(['user','user.instructor','user.student','course'])
        // ->where('report_type', ['spam','technical_issue']) // nếu cần lọc theo loại báo cáo
        // ->select('id', 'user_id', 'reason', 'report_type', 'created_at') // nếu cần chọn trường cụ thể
        // ->orderBy('created_at', 'desc')
        // ->paginate(10);
        // ->get();
        // Nếu không cần phân trang thì bỏ paginate đi
        // ->paginate(10) hoặc ->get() tùy nhu cầu
    ->where('report_type', ['spam','technical_issue'])
        // ->select('id', 'user_id', 'reason', 'report_type', 'created_at')
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'message' => 'Reports retrieved',
        'reports' => $reports
    ]);
}

}