<?php

namespace Boi\Backend\Enums;

/**
 * Lifecycle of a maker-checker {@see \Boi\Backend\Models\StatusChangeRequest}.
 * Only PENDING requests can be approved or rejected; both outcomes are final.
 */
class StatusChangeRequestStatus extends Enum
{
    const PENDING = 'pending';

    const APPROVED = 'approved';

    const REJECTED = 'rejected';

    public static function getBadgeMap(): array
    {
        return [
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        ];
    }
}
