<?php

namespace Boi\Backend\Events;

use Boi\Backend\Models\StatusChangeRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A checker rejected a decline reversal; the application stays declined. */
class DeclineReversalRejected implements ShouldDispatchAfterCommit
{
    public function __construct(public StatusChangeRequest $request) {}
}
