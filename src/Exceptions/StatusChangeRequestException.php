<?php

namespace Boi\Backend\Exceptions;

use RuntimeException;

/**
 * A maker-checker request could not be created or processed because of the
 * request's or the application's state. Messages are safe to show to staff.
 */
class StatusChangeRequestException extends RuntimeException
{
    public static function notDeclined(): self
    {
        return new self('Only a declined application can have its decline reversed.');
    }

    public static function steppedDown(): self
    {
        return new self('Stepped-down applications cannot be reversed through a decline reversal.');
    }

    public static function alreadyPending(): self
    {
        return new self('A decline reversal request is already pending for this application.');
    }

    public static function notPending(): self
    {
        return new self('This request has already been processed.');
    }

    public static function stateChanged(): self
    {
        return new self('The application is no longer in the declined state this request was raised against.');
    }

    public static function applicationMissing(): self
    {
        return new self('The application for this request no longer exists.');
    }

    public static function unsupportedType(): self
    {
        return new self('This request type cannot be processed as a decline reversal.');
    }

    public static function cannotReopen(string $reason): self
    {
        return new self($reason);
    }

    public static function reopenConflict(?\Throwable $previous = null): self
    {
        return new self(
            'This application cannot be reopened because it conflicts with existing data — usually the applicant has already started another application. Nothing was changed; the request is still pending.',
            0,
            $previous,
        );
    }

    public static function missing(string $field): self
    {
        return new self("A {$field} is required.");
    }
}
