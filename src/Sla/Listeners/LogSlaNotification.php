<?php

namespace Boi\Backend\Sla\Listeners;

use Boi\Backend\Sla\Models\SlaNotificationLog;
use Boi\Backend\Sla\Notifications\SlaEscalationNotification;
use Boi\Backend\Sla\Notifications\SlaReminderNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records every reminder and escalation attempt (BRD §5.4-01: notifications and
 * escalations shall be time-stamped and logged).
 *
 * The online portal grew this because staff reported never receiving escalation
 * mail and nobody could show what had been sent. It listens to the framework's own
 * events rather than being called from the engine, so the trail reflects delivery
 * rather than intention: a notification that throws is recorded as failed, with the
 * reason.
 *
 * Registered by the package's service provider. Laravel would also discover it from
 * the handle() signature, so a portal registering it again writes every row twice.
 */
class LogSlaNotification
{
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        $notification = $event->notification;

        $type = match (true) {
            $notification instanceof SlaReminderNotification => 'reminder',
            $notification instanceof SlaEscalationNotification => 'escalation_'.$notification->level,
            default => null,
        };

        if ($type === null || ! config('boi_sla.log_notifications', true)) {
            return;
        }

        $failed = $event instanceof NotificationFailed;
        $notifiable = $event->notifiable;

        try {
            SlaNotificationLog::query()->create([
                'app' => config('boi_sla.app'),
                'tracker_id' => $notification->trackerId,
                'case_type' => $notification->caseType,
                'case_id' => null,
                'notification_type' => $type,
                'channel' => class_basename((string) $event->channel),
                'user_id' => is_numeric($notifiable->id ?? null) ? (int) $notifiable->id : null,
                'recipient_name' => $notifiable->name ?? null,
                'recipient_email' => $notifiable->email ?? null,
                'status' => $failed ? 'failed' : 'sent',
                'error' => $failed ? $this->reason($event) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // The audit row must never take the notification down with it.
            Log::warning('Could not record an SLA notification', ['error' => $e->getMessage()]);
        }
    }

    private function reason(NotificationFailed $event): string
    {
        $data = $event->data ?? [];
        $exception = is_array($data) ? ($data['exception'] ?? null) : null;

        return mb_substr(
            (string) ($exception instanceof Throwable ? $exception->getMessage() : json_encode($data)),
            0,
            1000
        );
    }
}
