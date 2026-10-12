<?php

namespace Boi\Backend\Events;

use Boi\Backend\Models\StatusChangeRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A checker approved a return request; the application has been returned. */
class ReturnApproved implements ShouldDispatchAfterCommit
{
    public function __construct(public StatusChangeRequest $request) {}
}
