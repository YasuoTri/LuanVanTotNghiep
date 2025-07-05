<?php

namespace App\Mail;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CourseRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $course;
    public $notes;

    public function __construct(Course $course, $notes)
    {
        $this->course = $course;
        $this->notes = $notes;
    }

    public function build()
    {
        return $this->subject('Khóa học của bạn đã bị từ chối')
            ->view('emails.course_rejected')
            ->with([
                'courseName' => $this->course->course_name,
                'notes' => $this->notes
            ]);
    }
}
