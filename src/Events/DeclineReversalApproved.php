<?php

namespace Boi\Backend\Events;

use Boi\Backend\Models\StatusChangeRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A checker approved a decline reversal and the application is now returned.
 * The applicant is notified by the app's existing Returned flow; apps use this
 * to tell the maker.
 */
class DeclineReversalApproved implements ShouldDispatchAfterCommit
{
    public function __construct(public StatusChangeRequest $request) {}
}
