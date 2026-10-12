<?php

namespace Boi\Backend\Events;

use Boi\Backend\Models\StatusChangeRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A checker rejected a return request; the application is unchanged. */
class ReturnRejected implements ShouldDispatchAfterCommit
{
    public function __construct(public StatusChangeRequest $request) {}
}
