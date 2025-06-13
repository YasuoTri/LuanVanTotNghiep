<?php

namespace App\Observers;

use App\Models\Course;
use App\Jobs\RefundPayments;
use App\Jobs\NotifyStudentsOfCourseBan;

class CourseObserver
{
    public function updated(Course $course)
    {
        if ($course->wasChanged('status') && $course->status === 'banned') {
            RefundPayments::dispatch($course);
            NotifyStudentsOfCourseBan::dispatch($course);
        }
    }
}