<?php

namespace App\Http\Controllers;

use App\Models\Instructors;
use App\Models\User;
use App\Models\Violation;
use App\Notifications\ViolationNotification;
use Illuminate\Http\Request;

class ViolationController extends Controller
{
    public function getUserViolations($userId)
{
    $violations = Violation::where('user_id', $userId)
        ->orderByDesc('created_at')
        ->get();

    return response()->json($violations);
}

    public function handleUserViolation(Request $request)
{
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
        'action_taken' => 'required|in:warning,suspension,ban',
        'admin_notes'=> 'nullable|string|max:1000',
        'suspended_until' => 'nullable|date',
    ]);

    $user = User::findOrFail($validated['user_id']);
    $action = $validated['action_taken'];
    $suspendedUntil = null;
     // Kiểm tra nếu action là 'ban' và user là instructor
    if ($action === 'ban' && $user->role === 'instructor') {
        // Lấy instructor_id từ bảng instructors
        $instructor = Instructors::where('user_id', $user->id)->first();

        if ($instructor) {
            // Kiểm tra số lượng khóa học bị banned của instructor
            $bannedCoursesCount = \App\Models\Course::where('instructor_id', $instructor->id)
                ->where('status', 'banned')
                ->count();

            if ($bannedCoursesCount === 0) {
                return response()->json([
                    'message' => 'Cannot ban this instructor because they do not have any banned courses.'
                ], 422);
            }
        }
    }
    // Áp dụng hành động
    switch ($action) {
        case 'suspension':
            $suspendedUntil = $validated['suspended_until'] ?? now()->addDays(7);
            $user->update([
                'status' => 'suspended',
            ]);
            break;

        case 'ban':
            $user->update([
                'status' => 'banned',
                'suspended_until' => null,
            ]);
            break;

        case 'warning':
        default:
            // Không cần cập nhật user
            break;
    }

    // Lưu vi phạm
    $violation = Violation::create([
        'user_id' => $user->id,
        'action_taken' => $action,
        'admin_notes' => $validated['admin_notes'] ?? null,
        'suspended_until' => $suspendedUntil,
    ]);

    // Gửi thông báo
    $user->notify(new ViolationNotification($violation));

    return response()->json([
        'message' => 'User violation handled successfully',
        'violation' => $violation
    ]);
}
}
