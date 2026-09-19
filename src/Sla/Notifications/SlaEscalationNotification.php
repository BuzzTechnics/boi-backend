<?php

namespace Boi\Backend\Sla\Notifications;

use Boi\Backend\Sla\Support\SlaCaseSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The Escalation letters, at 100%, 150% and 200% of a stage's SLA.
 *
 * Wording follows the document's Escalation 1 and Escalation 2. It gives no template
 * for the 200% level named in its rules table, so level 3 reuses the same letter
 * with its own ordinal; replace this when BOI issues one.
 *
 * Each level copies everyone below it, so the record of who already knew travels
 * with the mail.
 */
class SlaEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<int, string>  $copyTo */
    public function __construct(
        public SlaCaseSummary $case,
        public int $trackerId,
        public string $caseType,
        public int $level,
        public array $copyTo = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $portal = config('boi_sla.notifications.portal_name', 'BOI Portal');
        $ordinal = match ($this->level) {
            1 => '1st',
            2 => '2nd',
            default => '3rd',
        };

        $message = (new MailMessage)
            ->subject("Escalation Notification on {$this->case->taskName} – Application ID: {$this->case->reference}")
            ->greeting('Dear '.($notifiable->name ?? 'Colleague').',')
            ->line("A task on the {$this->case->taskName} on the {$portal} has not been done. Your assistance is urgently required.")
            ->line("This is the {$ordinal} level escalation for an application for:")
            ->line("Process: {$this->case->taskName}");

        if ($this->case->companyName) {
            $message->line("Customer: {$this->case->companyName}");
        }

        if ($this->case->registrationNumber) {
            $message->line("Registration No: {$this->case->registrationNumber}");
        }

        if ($this->case->url) {
            $message->action("Open the {$portal}", $this->case->url);
        }

        if ($this->copyTo !== []) {
            $message->cc($this->copyTo);
        }

        return $message->line('Thank you.');
    }
}
