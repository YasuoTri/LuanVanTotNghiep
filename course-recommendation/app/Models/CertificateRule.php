<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateRule extends Model
{
    protected $fillable = [
        'course_id',
        'lesson_completion_percent',
        'lesson_version_rule',
        'quiz_min_score',
        'quiz_version_rule',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // public function instructor()
    // {
    //     return $this->belongsTo(Instructors::class);
    // }
     public function instructor()
    {
        return $this->hasOneThrough(
            Instructors::class, // Model đích
            Course::class,      // Model trung gian
            'id',               // Khóa ngoại trên bảng trung gian (courses.id)
            'id',               // Khóa chính trên bảng đích (instructors.id)
            'course_id',        // Khóa ngoại trên bảng hiện tại (certificate_rules.course_id)
            'instructor_id'     // Khóa ngoại trên bảng trung gian liên kết với bảng đích (courses.instructor_id)
        );
    }
}
