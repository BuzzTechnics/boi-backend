<?php

namespace Boi\Backend\DocumentLibrary\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A generic customer notification for the document library (request received, or a
 * document returned for re-upload). Kept plain so every fund can use it; a fund that
 * wants its own house style can listen for the engine's events instead.
 */
class DocumentLibraryNotification extends Notification
{
    use Queueable;

    /** @param array<int,string> $lines */
    public function __construct(
        public string $subject,
        public array $lines,
        public ?string $actionUrl = null,
        public string $actionText = 'Open the portal',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject);

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        if ($this->actionUrl) {
            $mail->action($this->actionText, $this->actionUrl);
        }

        return $mail;
    }
}
