<?php
namespace App\Mail;

use App\Models\Course;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReportHandledNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $course;
    public $report;
    public $status;
    public $adminNotes;

    public function __construct(Course $course, Report $report, $status, $adminNotes)
    {
        $this->course = $course;
        $this->report = $report;
        $this->status = $status;
        $this->adminNotes = $adminNotes;
        
    }

    public function build()
    {
        return $this->subject('Report Review Notification for Your Course')
                    ->view('emails.report_handled');
    }
}
