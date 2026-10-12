<?php

namespace Boi\Backend\Exceptions;

use LogicException;

/**
 * Thrown by {@see \Boi\Backend\Models\Concerns\GuardsDeclinedStatus} when code
 * tries to reopen a declined application without an approved reversal.
 */
class DeclinedApplicationLockedException extends LogicException
{
    public static function make(): self
    {
        return new self(
            'Cannot reopen a declined application directly. Raise a decline reversal request; it must be approved by a Super Admin.'
        );
    }
}
