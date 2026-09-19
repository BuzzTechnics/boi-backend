<?php

namespace Boi\Backend\Sla\Notifications;

use Boi\Backend\Sla\Support\SlaCaseSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Reminder letter of "SPAF Portal Project – Task Reminder and Escalation
 * Notifications", fired at 75% of a stage's SLA.
 *
 * It goes to the task owner alone. Nobody is late yet, and copying a supervisor at
 * 75% turns a reminder into a complaint.
 */
class SlaReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SlaCaseSummary $case,
        public int $trackerId,
        public string $caseType,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $portal = config('boi_sla.notifications.portal_name', 'BOI Portal');

        $message = (new MailMessage)
            ->subject("Reminder Notification on {$this->case->taskName} – Application ID: {$this->case->reference}")
            ->greeting('Dear '.($notifiable->name ?? 'Colleague').',')
            ->line("This is a reminder that you have a pending task for {$this->case->taskName}.");

        if ($this->case->companyName) {
            $message->line("Customer: {$this->case->companyName}");
        }

        if ($this->case->registrationNumber) {
            $message->line("Registration No: {$this->case->registrationNumber}");
        }

        $message->line("Process: {$this->case->taskName}")
            ->line("Please log into the {$portal} to execute this task.");

        if ($this->case->url) {
            $message->action("Open the {$portal}", $this->case->url);
        }

        return $message->line('Thank you.');
    }
}
