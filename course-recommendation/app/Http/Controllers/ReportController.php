<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\ForumPost;
use App\Models\Question;
use App\Models\Violation;
use App\Notifications\ViolationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    // Người dùng gửi báo cáo
    public function submitReport(Request $request)
    {
        $request->validate([
            'reportable_type' => 'required|in:Course,Lesson,Quiz,ForumPost,Question',
            'reportable_id' => 'required|integer',
            'reason' => 'required|string|max:1000',
            'report_type' => 'required|in:inappropriate_content,technical_issue,copyright_violation,spam,other',
        ]);

        $user = Auth::user();
        if (!in_array($user->role, ['student', 'instructor'])) {
            return response()->json(['message' => 'Only students or instructors can submit reports'], 403);
        }

        // Chuyển đổi reportable_type thành namespace đầy đủ
        $reportableType = 'App\\Models\\' . $request->reportable_type;

        // Kiểm tra xem nội dung có tồn tại không
        if (!class_exists($reportableType) || !$reportableType::find($request->reportable_id)) {
            return response()->json(['message' => 'Reported content not found'], 404);
        }

        Report::create([
            'user_id' => $user->id,
            'reportable_type' => $reportableType,
            'reportable_id' => $request->reportable_id,
            'reason' => $request->reason,
            'report_type' => $request->report_type,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Report submitted successfully']);
    }

    // Admin xem danh sách báo cáo
    public function index(Request $request)
    {
        $reports = Report::with(['user', 'reportable', 'user.student', 'user.instructor'])
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->report_type, function ($query, $reportType) {
                return $query->where('report_type', $reportType);
            })
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($reports);
    }

public function FindviewReports(Request $request)
    {
        // Validate input
        $request->validate([
            'status' => 'nullable|in:pending,reviewed,resolved',
            'report_type' => 'nullable|in:inappropriate_content,technical_issue,copyright_violation,spam,other',
            'reportable_type' => 'nullable|in:Course,Lesson,Quiz,ForumPost,Question',
        ]);

        try {
            $query = Report::with(['user.student', 'user.instructor', 'reportable']);

            // Filter by status
            if ($request->status) {
                $query->where('status', $request->status);
            }

            // Filter by report_type
            if ($request->report_type) {
                $query->where('report_type', $request->report_type);
            }

            // Filter by reportable_type
            if ($request->reportable_type) {
                $query->where('reportable_type', 'App\Models\\' . $request->reportable_type);
            }

            // Sort and paginate
            $reports = $query->orderBy('status', 'asc')
                             ->orderBy('created_at', 'desc')
                             ->paginate(10);

            return response()->json($reports);
        } catch (\Exception $e) {
            Log::error('Error in FindviewReports: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
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
   public function handleReport(Request $request, Report $report)
    {
        // Validate input
        $request->validate([
            'status' => 'required|in:reviewed,resolved',
            'admin_notes' => 'nullable|string|max:1000',
            'action' => 'nullable|in:ban,delete,ignore',
        ]);

        try {
            // Update report status
            $report->update([
                'status' => $request->status,
                'admin_id' => Auth::user()->admin->id,
                'admin_notes' => $request->admin_notes,
                'reviewed_at' => now(),
            ]);

            // Get reportable content
            $reportable = $report->reportable;
            if (!$reportable) {
                return response()->json(['message' => 'Reported content not found'], 404);
            }

            // Determine the owner of the content
            $owner = $this->getContentOwner($reportable);
            if (!$owner) {
                return response()->json(['message' => 'Content owner not found'], 404);
            }

            // Handle action on content
            if ($request->action === 'ban') {
                if ($reportable instanceof Course) {
                    $reportable->update(['status' => 'banned']);
                } elseif ($reportable instanceof Lesson) {
                    $reportable->update(['status' => 'banned']);
                } elseif ($reportable instanceof Quiz) {
                    $reportable->update(['status' => 'banned']);
                } elseif ($reportable instanceof Question) {
                    $reportable->update(['status' => 'banned']);
                } elseif ($reportable instanceof ForumPost) {
                    $reportable->update(['status' => 'banned']);
                }
            } elseif ($request->action === 'delete') {
                $reportable->delete(); // Soft delete
            }

            // Handle user violation (warning, suspension, ban)
            if ($request->action && $request->action !== 'ignore') {
                $this->handleUserViolation($owner, $report, $request->admin_notes);
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

    protected function getContentOwner($reportable)
    {
        if ($reportable instanceof Course) {
            // Get instructor's user via course_instructors
            $instructor = $reportable->instructors()->first();
            return $instructor ? $instructor->user : null;
        } elseif ($reportable instanceof Lesson) {
            // Get instructor's user via course
            $course = $reportable->course;
            $instructor = $course->instructors()->first();
            return $instructor ? $instructor->user : null;
        } elseif ($reportable instanceof Quiz) {
            // Get instructor's user via lesson and course
            $course = $reportable->lesson->course;
            $instructor = $course->instructors()->first();
            return $instructor ? $instructor->user : null;
        } elseif ($reportable instanceof Question) {
            // Questions are created by instructors, not students
            $course = $reportable->quiz->lesson->course;
            $instructor = $course->instructors()->first();
            return $instructor ? $instructor->user : null;
        } elseif ($reportable instanceof ForumPost) {
            // Forum posts are created by users (students or instructors)
            return $reportable->user;
        }
        return null;
    }

    protected function handleUserViolation($user, $report, $adminNotes)
    {
        // Count previous violations
        $violationCount = $user->violations()->count();

        // Determine action based on violation count
        $action = 'warning';
        $suspendedUntil = null;

        if ($violationCount == 1) {
            $action = 'suspension';
            $suspendedUntil = now()->addDays(7);
            $user->update([
                'status' => 'suspended',
                'suspended_until' => $suspendedUntil,
            ]);
        } elseif ($violationCount >= 2) {
            $action = 'ban';
            $user->update([
                'status' => 'banned',
                'suspended_until' => null,
            ]);
        }

        // Record the violation
        $violation = Violation::create([
            'user_id' => $user->id,
            'report_id' => $report->id,
            'action_taken' => $action,
            'admin_notes' => $adminNotes,
            'suspended_until' => $suspendedUntil,
        ]);

        // Send email notification
        $user->notify(new ViolationNotification($violation, $report));
    }
    public function store(Request $request)
    {
        $request->validate([
            'reportable_type' => 'required|in:Course,Lesson,Quiz,ForumPost,Question',
            'reportable_id' => 'required|integer',
            'reason' => 'required|string|max:1000',
            'report_type' => 'required|in:inappropriate_content,technical_issue,copyright_violation,spam,other',
        ]);

        $user = Auth::user();

        // Chuyển đổi reportable_type thành namespace đầy đủ
        $reportableType = 'App\\Models\\' . $request->reportable_type;

        // Kiểm tra xem nội dung có tồn tại không
        if (!class_exists($reportableType) || !$reportableType::find($request->reportable_id)) {
            return response()->json(['message' => 'Reported content not found'], 404);
        }

        Report::create([
            'user_id' => $user->id,
            'reportable_type' => $reportableType,
            'reportable_id' => $request->reportable_id,
            'reason' => $request->reason,
            'report_type' => $request->report_type,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Report submitted successfully']);
    }
    public function show(Report $report)
    {
        $report->load(['user', 'reportable', 'admin']);
        return response()->json($report);
    }
    public function update(Request $request, Report $report)
    {
        $request->validate([
            'status' => 'required|in:pending,reviewed,resolved',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $report->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Report updated successfully']);
    }
    public function destroy(Report $report)
    {
        $report->delete();
        return response()->json(['message' => 'Report deleted successfully']);
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
        $trashedReports = Report::onlyTrashed()->with(['user', 'reportable'])->paginate(10);
        return response()->json($trashedReports);
    }
    public function search(Request $request)
    {
        $query = Report::with(['user', 'reportable'])
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

}