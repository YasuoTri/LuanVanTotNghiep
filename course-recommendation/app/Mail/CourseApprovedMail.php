<?php

namespace App\Mail;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CourseApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
    }

    public function build()
    {
        return $this->subject('Khóa học của bạn đã được phê duyệt')
            ->view('emails.course_approved')
            ->with([
                'courseName' => $this->course->course_name,
            ]);
    }
}
