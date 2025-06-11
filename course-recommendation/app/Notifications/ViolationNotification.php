<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ViolationNotification extends Notification
{
    protected $violation;
    protected $report;

    public function __construct($violation, $report)
    {
        $this->violation = $violation;
        $this->report = $report;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $action = $this->violation->action_taken;
        $message = new MailMessage;

        $message->subject('Action Taken on Your Content')
                ->greeting('Hello ' . $notifiable->username . ',')
                ->line('We have reviewed a report regarding your content.');

        if ($action === 'warning') {
            $message->line('This is your first violation. Please review our community guidelines to avoid future issues.');
        } elseif ($action === 'suspension') {
            $suspendedUntil = $this->violation->suspended_until->toFormattedDateString();
            $message->line('Your account has been suspended until ' . $suspendedUntil . ' due to repeated violations.');
        } elseif ($action === 'ban') {
            $message->line('Your account has been permanently banned due to multiple violations.');
        }

        $message->line('Report Details: ' . $this->report->reason)
                ->line('Admin Notes: ' . ($this->violation->admin_notes ?? 'None'))
                ->action('Review Guidelines', url('/community-guidelines'))
                ->line('Thank you for your attention.');

        return $message;
    }
}