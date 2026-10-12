<?php

namespace Boi\Backend\Events;

use Boi\Backend\Models\StatusChangeRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A maker raised a return request; apps notify checkers. */
class ReturnRequested implements ShouldDispatchAfterCommit
{
    public function __construct(public StatusChangeRequest $request) {}
}
