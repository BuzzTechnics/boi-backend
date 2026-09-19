<?php

namespace Boi\Backend\Sla;

use Boi\Backend\Sla\Contracts\SlaDirectory;
use Boi\Backend\Sla\Models\SlaTracker;
use Boi\Backend\Sla\Notifications\SlaEscalationNotification;
use Boi\Backend\Sla\Notifications\SlaReminderNotification;
use Boi\Backend\Sla\Support\SlaDefinitions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * The SLA timeline BOI applies across its intervention funds, in one place.
 *
 *   Reminder      75% of the SLA  -> the task owner
 *   Escalation 1 100%             -> the first-level supervisor, copying the owner
 *   Escalation 2 150%             -> the second level, copying those below
 *   Escalation 3 200%             -> as configured
 *
 * Elapsed time is working time ({@see BusinessCalendar}): weekends, public holidays
 * and the hours outside 08:00–16:00 do not count. Every fund had been reimplementing
 * some part of this — and getting the calendar wrong, so short SLAs escalated over
 * weekends — which is why it is here rather than in each portal.
 *
 * What stays with the portal is who its people are: {@see SlaDirectory}.
 */
class SlaEngine
{
    /** In the order they fire. */
    public const LEVELS = ['reminder', 'escalation_1', 'escalation_2', 'escalation_3'];

    public function __construct(
        private BusinessCalendar $calendar,
        private SlaDefinitions $definitions,
        private SlaDirectory $directory,
    ) {}

    /**
     * Open a clock, closing any of the same type already running on the case — a
     * case cannot be late twice over for the same thing.
     */
    public function start(
        string $caseType,
        int $caseId,
        string $type = SlaTracker::TYPE_OWNER,
        ?int $ownerId = null,
        ?int $groupId = null,
    ): SlaTracker {
        $this->complete($caseType, $caseId, $type);

        $minutes = $this->definitions->minutes($caseType, $type);
        $start = now();

        return SlaTracker::create([
            'app' => $this->definitions->app(),
            'type' => $type,
            'case_type' => $caseType,
            'case_id' => $caseId,
            'owner_id' => $ownerId,
            'group_id' => $groupId,
            'start_time' => $start,
            // Recorded for the people reading the case, not used by the run itself:
            // elapsed working time is measured from start_time each time, so a
            // definition changed mid-flight applies immediately.
            'deadline' => $minutes > 0 ? $this->calendar->addWorkingMinutes($start, $minutes) : null,
            'status' => SlaTracker::STATUS_IN_PROGRESS,
        ]);
    }

    /** Close the clocks on a case — all of them, or one type. */
    public function complete(string $caseType, int $caseId, ?string $type = null): int
    {
        $trackers = SlaTracker::query()
            ->forApp($this->definitions->app())
            ->forCase($caseType, $caseId)
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->open()
            ->get();

        $trackers->each->markCompleted();

        return $trackers->count();
    }

    /** Move a running clock to a new owner without restarting it. */
    public function reassign(string $caseType, int $caseId, ?int $ownerId): void
    {
        SlaTracker::query()
            ->forApp($this->definitions->app())
            ->forCase($caseType, $caseId)
            ->where('type', SlaTracker::TYPE_OWNER)
            ->open()
            ->update(['owner_id' => $ownerId]);
    }

    /**
     * Walk every running clock and send whatever has come due.
     *
     * @return array<string, int> how many of each level went out
     */
    public function run(): array
    {
        $counts = array_fill_keys(self::LEVELS, 0);

        // Breached clocks stay in the walk: 150% and 200% come after the breach.
        $trackers = SlaTracker::query()
            ->forApp($this->definitions->app())
            ->open()
            ->get();

        foreach ($trackers as $tracker) {
            $minutes = $this->definitions->minutes($tracker->case_type, $tracker->type);

            if ($minutes <= 0) {
                continue;
            }

            $elapsed = $this->calendar->workingMinutesBetween($tracker->start_time, now());
            $thresholds = $this->definitions->thresholds($tracker->case_type);

            foreach (self::LEVELS as $level) {
                $fraction = $thresholds[$level] ?? null;
                $column = SlaTracker::stampColumn($level);

                if ($fraction === null || $tracker->{$column} !== null || $elapsed < $minutes * $fraction) {
                    continue;
                }

                $this->fire($tracker, $level);

                // Stamped whether or not anyone could be found to tell. An unroutable
                // level must not re-fire every quarter of an hour; the gap is in the
                // log, and it is a staffing problem rather than a scheduling one.
                $tracker->forceFill([$column => now()])->save();

                if ($level === 'escalation_1') {
                    $tracker->markBreached();
                }

                $counts[$level]++;
            }
        }

        return $counts;
    }

    private function fire(SlaTracker $tracker, string $level): void
    {
        $case = $this->directory->describe($tracker);

        if ($case === null) {
            // The case is gone — deleted, or belonging to a type this portal no
            // longer runs. Closing the clock is kinder than escalating about nothing.
            $tracker->markCompleted();

            return;
        }

        $owners = $this->directory->owners($tracker);

        if ($level === 'reminder') {
            $owners->each(fn ($owner) => $owner->notify(
                new SlaReminderNotification($case, (int) $tracker->id, $tracker->case_type)
            ));

            $this->warnIfUnrouted($tracker, $level, $owners);

            return;
        }

        $number = (int) substr($level, -1);
        $recipients = $this->directory->supervisors($tracker, $number);

        $copyTo = $owners->pluck('email');

        for ($below = 1; $below < $number; $below++) {
            $copyTo = $copyTo->merge($this->directory->supervisors($tracker, $below)->pluck('email'));
        }

        $copyTo = $copyTo->filter()->unique()->values()->all();

        $recipients->each(fn ($recipient) => $recipient->notify(new SlaEscalationNotification(
            $case,
            (int) $tracker->id,
            $tracker->case_type,
            $number,
            array_values(array_diff($copyTo, [$recipient->email])),
        )));

        $this->warnIfUnrouted($tracker, $level, $recipients);
    }

    /** @param  Collection<int, mixed>  $recipients */
    private function warnIfUnrouted(SlaTracker $tracker, string $level, Collection $recipients): void
    {
        if ($recipients->isNotEmpty()) {
            return;
        }

        // Silence here looks exactly like a healthy queue. SPAF went live with no
        // NSDC Reviewer or Group Head accounts at all, which would have made every
        // escalation an invisible no-op.
        Log::warning('SLA notification had no recipient', [
            'app' => $this->definitions->app(),
            'tracker_id' => $tracker->id,
            'case_type' => $tracker->case_type,
            'case_id' => $tracker->case_id,
            'level' => $level,
        ]);
    }
}
