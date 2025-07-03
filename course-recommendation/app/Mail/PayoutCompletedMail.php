<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayoutCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $instructor;
    public $amountUSD;
    public $revenueDistribution;

    public function __construct($instructor, $amountUSD, $revenueDistribution)
    {
        $this->instructor = $instructor;
        $this->amountUSD = $amountUSD;
        $this->revenueDistribution = $revenueDistribution;
    }

    public function build()
    {
        return $this->subject('Your payout has been completed')
                    ->view('emails.payout_completed');
    }
}
