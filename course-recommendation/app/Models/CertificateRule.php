<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateRule extends Model
{
    protected $fillable = [
        'course_id',
        'instructor_id',
        'lesson_completion_percent',
        'lesson_version_rule',
        'quiz_min_score',
        'quiz_version_rule',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Instructors::class);
    }
}
