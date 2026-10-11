<?php

namespace Boi\Backend\Contracts;

/**
 * Optional, implemented by an app's application model: report app-specific
 * reasons a declined application cannot be reopened (e.g. "one incomplete
 * application per applicant"). {@see \Boi\Backend\Services\DeclineReversalService}
 * checks it before saving anything, so the app's Returned observer never runs
 * (and never emails the applicant) for a reopening that would then fail.
 */
interface ChecksReopenConflicts
{
    /** A staff-facing reason the application cannot be reopened, or null. */
    public function reopenConflict(): ?string;
}
