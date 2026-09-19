<?php

namespace Boi\Backend\Sla\Contracts;

use Boi\Backend\Sla\Models\SlaTracker;
use Boi\Backend\Sla\Support\SlaCaseSummary;
use Illuminate\Support\Collection;

/**
 * The half of the SLA engine only a portal can answer: who its people are and what
 * its cases are called.
 *
 * Everything else — the calendar, the thresholds, the stamps, the trail — is the
 * same across the funds, which is why it lives in this package. Roles, users and
 * case records are not: GLOW has business units and project officers, SPAF has NSDC
 * reviewers and a steering committee, and neither could be hard-coded here without
 * making the engine a liability for the next fund.
 *
 * {@see Boi\Backend\Sla\Support\RoleBasedDirectory} is a ready implementation for a
 * portal whose recipients are simply "everyone holding role X".
 */
interface SlaDirectory
{
    /** Details of a case, for the letters. Null if it no longer exists. */
    public function describe(SlaTracker $tracker): ?SlaCaseSummary;

    /**
     * Whoever currently holds the task — the {Task Owner} of the template.
     *
     * @return Collection<int, mixed> notifiable models
     */
    public function owners(SlaTracker $tracker): Collection;

    /**
     * Whoever should hear about it at this escalation level (1, 2 or 3).
     *
     * @return Collection<int, mixed> notifiable models
     */
    public function supervisors(SlaTracker $tracker, int $level): Collection;
}
