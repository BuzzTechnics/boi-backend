<?php

namespace Boi\Backend\Sla;

use Illuminate\Support\Carbon;

/**
 * Counts SLA time the way BOI does.
 *
 * "SPAF Portal Project – Task Reminder and Escalation Notifications" opens with two
 * rules before anything else: weekends are excluded from the reminder and escalation
 * timeline, and so are the six named public holidays. The workday is Monday to
 * Friday, 08:00–16:00.
 *
 * SLAs across these funds are short — a supervisor gets four hours to assign a case —
 * so counting wall-clock time does not merely drift, it inverts the rule: work
 * arriving at 15:00 on a Friday would breach at 19:00 that evening and escalate over
 * the weekend for work nobody was expected to do.
 *
 * Holidays are whole days: a date in the list contributes nothing, however the clock
 * falls.
 */
class BusinessCalendar
{
    /** @return array<int, string> */
    private function holidays(): array
    {
        return (array) config('boi_sla.workday.holidays', []);
    }

    private function startHour(): int
    {
        return (int) config('boi_sla.workday.start_hour', 8);
    }

    private function endHour(): int
    {
        return (int) config('boi_sla.workday.end_hour', 16);
    }

    public function isWorkday(Carbon $date): bool
    {
        return ! $date->isWeekend() && ! $this->isPublicHoliday($date);
    }

    public function isPublicHoliday(Carbon $date): bool
    {
        return in_array($date->format('m-d'), $this->holidays(), true);
    }

    /** Working minutes between two instants. */
    public function workingMinutesBetween(Carbon $from, Carbon $to): int
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }

        $minutes = 0;
        $cursor = $from->copy();

        while ($cursor->lessThan($to)) {
            if (! $this->isWorkday($cursor)) {
                $cursor = $cursor->copy()->addDay()->setTime($this->startHour(), 0);

                continue;
            }

            $dayStart = $cursor->copy()->setTime($this->startHour(), 0);
            $dayEnd = $cursor->copy()->setTime($this->endHour(), 0);

            // Only the part of today's shift inside [from, to] counts: arriving at
            // 06:00 earns nothing until 08:00, and 16:00 closes the day whatever is
            // still outstanding.
            $windowStart = $cursor->greaterThan($dayStart) ? $cursor : $dayStart;
            $windowEnd = $to->lessThan($dayEnd) ? $to : $dayEnd;

            if ($windowEnd->greaterThan($windowStart)) {
                $minutes += (int) $windowStart->diffInMinutes($windowEnd);
            }

            $cursor = $cursor->copy()->addDay()->setTime($this->startHour(), 0);
        }

        return $minutes;
    }

    /** The instant that is $minutes of working time after $from. */
    public function addWorkingMinutes(Carbon $from, int $minutes): Carbon
    {
        $remaining = max(0, $minutes);

        // Work arriving out of hours starts its clock at the next opening, so the
        // deadline is measured from when it could first have been done.
        $cursor = $this->nextWorkingInstant($from->copy());

        while ($remaining > 0) {
            $dayEnd = $cursor->copy()->setTime($this->endHour(), 0);
            $availableToday = (int) $cursor->diffInMinutes($dayEnd);

            if ($availableToday >= $remaining) {
                return $cursor->copy()->addMinutes($remaining);
            }

            $remaining -= $availableToday;
            $cursor = $this->nextWorkingInstant($cursor->copy()->addDay()->setTime($this->startHour(), 0));
        }

        return $cursor;
    }

    /** The given instant, or the next moment the office is open. */
    public function nextWorkingInstant(Carbon $at): Carbon
    {
        $cursor = $at->copy();

        while (true) {
            if (! $this->isWorkday($cursor)) {
                $cursor = $cursor->copy()->addDay()->setTime($this->startHour(), 0);

                continue;
            }

            $dayStart = $cursor->copy()->setTime($this->startHour(), 0);
            $dayEnd = $cursor->copy()->setTime($this->endHour(), 0);

            if ($cursor->lessThan($dayStart)) {
                return $dayStart;
            }

            if ($cursor->greaterThanOrEqualTo($dayEnd)) {
                $cursor = $cursor->copy()->addDay()->setTime($this->startHour(), 0);

                continue;
            }

            return $cursor;
        }
    }
}
