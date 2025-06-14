<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InstructorRejectionMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $adminNotes;

    public function __construct($user, $adminNotes)
    {
        $this->user = $user;
        $this->adminNotes = $adminNotes;
    }

    public function build()
    {
        return $this->subject('Thông báo từ chối yêu cầu giảng viên')
                    ->view('emails.instructor_rejected');
    }
}
