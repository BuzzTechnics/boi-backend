<?php

namespace Boi\Backend\Events;

use Boi\Backend\Models\StatusChangeRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A maker raised a decline reversal; apps notify checkers. */
class DeclineReversalRequested implements ShouldDispatchAfterCommit
{
    public function __construct(public StatusChangeRequest $request) {}
}
