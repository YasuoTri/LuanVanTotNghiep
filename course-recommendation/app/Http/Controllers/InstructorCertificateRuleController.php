<?php

namespace App\Http\Controllers;

use App\Models\CertificateRule;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstructorCertificateRuleController extends Controller
{
public function store(Request $request)
{
    $request->validate([
        'course_id' => 'required|exists:courses,id',
        'lesson_completion_percent' => 'required|integer|min:0|max:100',
        'lesson_version_rule' => 'required|in:latest,any',
        'quiz_min_score' => 'required|integer|min:0|max:100',
        'quiz_version_rule' => 'required|in:latest,any',
    ]);

    $user = Auth::user();

    // instructor chỉ update rule cho course mình sở hữu
    $course = Course::where('id', $request->course_id)
        ->where('instructor_id', $user->instructor->id)
        ->firstOrFail();

    $rule = CertificateRule::updateOrCreate(
        ['course_id' => $request->course_id],
        [
            'lesson_completion_percent' => $request->lesson_completion_percent,
            'lesson_version_rule' => $request->lesson_version_rule,
            'quiz_min_score' => $request->quiz_min_score,
            'quiz_version_rule' => $request->quiz_version_rule
        ]
    );

    // bật chức năng cấp chứng chỉ
    $course->update(['is_certificate_enabled' => true]);

    return response()->json([
        'message' => 'Certificate rule saved successfully.',
        'rule' => $rule
    ]);
}
}
