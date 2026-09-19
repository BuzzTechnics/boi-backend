<?php

namespace Boi\Backend\Sla\Models;

use Boi\Backend\Sla\Models\Concerns\StoresSlaRecords;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per SLA notification attempt, per channel and recipient (BRD §5.4-01).
 *
 * "sent" means the channel accepted the message — for mail, that the mail server
 * took it. Whether it reached an inbox is not something an application can observe,
 * and the column does not pretend otherwise.
 */
class SlaNotificationLog extends Model
{
    use StoresSlaRecords;

    public $timestamps = false;

    protected $table = 'boi_sla_notification_logs';

    protected $fillable = [
        'app',
        'tracker_id',
        'case_type',
        'case_id',
        'notification_type',
        'channel',
        'user_id',
        'recipient_name',
        'recipient_email',
        'status',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
