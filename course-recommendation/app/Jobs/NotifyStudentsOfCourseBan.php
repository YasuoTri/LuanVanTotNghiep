<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Course;
use App\Mail\CourseBannedNotification;
use Illuminate\Support\Facades\Mail;

class NotifyStudentsOfCourseBan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
    }

    public function handle()
    {
        $students = $this->course->enrollments()
            ->join('users', 'enrollments.user_id', '=', 'users.id')
            ->where('users.role', 'student')
            ->pluck('users.email');

        foreach ($students as $email) {
            Mail::to($email)->queue(new CourseBannedNotification($this->course));
        }
    }
}