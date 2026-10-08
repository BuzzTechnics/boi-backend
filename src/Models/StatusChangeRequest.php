<?php

namespace Boi\Backend\Models;

use Boi\Backend\Enums\StatusChangeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A maker-checker request to change an application's status. Created and
 * processed only through {@see \Boi\Backend\Services\DeclineReversalService};
 * the row doubles as the audit record of the request and its outcome.
 *
 * @property int $id
 * @property string $requestable_type
 * @property int $requestable_id
 * @property string $type
 * @property string $status
 * @property string $from_status
 * @property string $to_status
 * @property string $reason
 * @property array|null $payload
 * @property array|null $snapshot
 * @property int $requested_by
 * @property int|null $reviewed_by
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $review_comment
 * @property string|null $pending_key
 */
class StatusChangeRequest extends Model
{
    public const TYPE_DECLINE_REVERSAL = 'decline_reversal';

    protected $table = 'status_change_requests';

    /**
     * Nothing is mass assignable: rows are written field-by-field by the
     * service so a stray fill() can never approve a request.
     */
    protected $guarded = ['*'];

    protected $casts = [
        'payload' => 'array',
        'snapshot' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function requestable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === StatusChangeRequestStatus::PENDING;
    }

    public function scopePending($query)
    {
        return $query->where('status', StatusChangeRequestStatus::PENDING);
    }

    public static function pendingKeyFor(string $type, Model $requestable): string
    {
        return $type.':'.$requestable->getMorphClass().':'.$requestable->getKey();
    }
}
