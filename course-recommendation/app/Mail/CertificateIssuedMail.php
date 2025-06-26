<?php
namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CertificateIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Certificate $certificate;

    /**
     * Create a new message instance.
     */
    public function __construct(Certificate $certificate)
    {
        $this->certificate = $certificate;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        return $this->subject('🎓 Chúc mừng! Bạn đã nhận được chứng chỉ khóa học')
            ->view('emails.certificate_issued') // 👈 View thực tế
            ->with([
                'certificate' => $this->certificate,
                'user' => $this->certificate->user,
                'course' => $this->certificate->course,
                'downloadUrl' => $this->certificate->download_url,
            ]);
    }
}
