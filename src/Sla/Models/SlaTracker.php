<?php

namespace Boi\Backend\Sla\Models;

use Boi\Backend\Sla\Models\Concerns\StoresSlaRecords;
use Illuminate\Database\Eloquent\Model;

/**
 * One clock on one case.
 *
 * A case can carry more than one at a time: an 'assignment' tracker measures a pool
 * before anyone holds the task, and an 'owner' tracker measures the person who then
 * does. Keeping them as rows rather than columns on the case is what lets a portal
 * say afterwards how long a stage actually took — a stamp on the case itself is lost
 * the moment the case moves on.
 */
class SlaTracker extends Model
{
    use StoresSlaRecords;

    public const TYPE_OWNER = 'owner';

    public const TYPE_ASSIGNMENT = 'assignment';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_BREACHED = 'breached';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'boi_sla_trackers';

    protected $fillable = [
        'app',
        'type',
        'case_type',
        'case_id',
        'owner_id',
        'group_id',
        'start_time',
        'deadline',
        'completed_at',
        'status',
        'notes',
        'reminder_sent_at',
        'escalation1_sent_at',
        'escalation2_sent_at',
        'escalation3_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'deadline' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'escalation1_sent_at' => 'datetime',
            'escalation2_sent_at' => 'datetime',
            'escalation3_sent_at' => 'datetime',
        ];
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Clocks still running, breached ones included.
     *
     * A breach is not the end of a task — the whole point of the 150% and 200% levels
     * is that they come after it. Judging "still running" by status alone stopped the
     * engine looking at a tracker the moment escalation 1 marked it breached, so the
     * later levels never fired on any subsequent run.
     */
    public function scopeOpen($query)
    {
        return $query->whereNull('completed_at')
            ->whereIn('status', [self::STATUS_IN_PROGRESS, self::STATUS_BREACHED]);
    }

    public function scopeForApp($query, ?string $app)
    {
        return $query->where(fn ($q) => $q->whereNull('app')->orWhere('app', $app));
    }

    public function scopeForCase($query, string $caseType, int $caseId)
    {
        return $query->where('case_type', $caseType)->where('case_id', $caseId);
    }

    public function markCompleted(): void
    {
        $this->forceFill([
            // A breach already recorded stays recorded: the case being finished later
            // does not unsay that it was late.
            'status' => $this->status === self::STATUS_BREACHED ? self::STATUS_BREACHED : self::STATUS_COMPLETED,
            'completed_at' => now(),
        ])->save();
    }

    public function markBreached(): void
    {
        if ($this->status === self::STATUS_IN_PROGRESS) {
            $this->forceFill(['status' => self::STATUS_BREACHED])->save();
        }
    }

    /** Column that stamps a given level, so the engine can say "already sent". */
    public static function stampColumn(string $level): string
    {
        return match ($level) {
            'reminder' => 'reminder_sent_at',
            'escalation_1' => 'escalation1_sent_at',
            'escalation_2' => 'escalation2_sent_at',
            'escalation_3' => 'escalation3_sent_at',
        };
    }
}
