<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InstructorApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $instructorRequest;

    public function __construct($user, $instructorRequest)
    {
        $this->user = $user;
        $this->instructorRequest = $instructorRequest;
    }

    public function build()
    {
        return $this->subject('Chúc mừng! Bạn đã trở thành giảng viên')
                    ->view('emails.instructor_approved');
    }
}
