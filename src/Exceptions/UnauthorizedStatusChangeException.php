<?php

namespace Boi\Backend\Exceptions;

/**
 * The acting user may not perform this step of the maker-checker flow.
 */
class UnauthorizedStatusChangeException extends StatusChangeRequestException
{
    public static function maker(): self
    {
        return new self('You are not authorized to request a decline reversal.');
    }

    public static function checker(): self
    {
        return new self('Only a Super Admin can approve or reject a decline reversal.');
    }

    public static function selfReview(): self
    {
        return new self('You cannot approve or reject a decline reversal you requested.');
    }
}
