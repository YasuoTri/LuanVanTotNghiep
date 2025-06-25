<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Course;

class RefundPayments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $course;

    public function __construct(Course $course)
    {
        $this->course = $course;
    }

    public function handle()
    {
        $this->course->payments()
            ->where('status', 'completed')
            ->get()
            ->each(function ($payment) {
                $payment->update(['status' => 'refunded']);
                $payment->logAction($payment->id,'refunded', "Refunded due to course ban at " . now()->toDateTimeString());
            });
    }
}