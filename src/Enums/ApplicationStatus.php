<?php

namespace Boi\Backend\Enums;

/**
 * Shared application lifecycle statuses for BOI intervention apps.
 *
 * Apps extend this and may add program-specific statuses (e.g. SPAF's
 * STEERCO_* cases), overriding {@see getBadgeMap()} to map them.
 */
class ApplicationStatus extends Enum
{
    const INCOMPLETE = 'incomplete';

    const INITIATED = 'initiated';

    const SUBMITTED = 'submitted';

    const APPROVED = 'approved';

    const DECLINED = 'declined';

    const COMPLETED = 'completed';

    const STEPPED_DOWN = 'stepped_down';

    const PENDING = 'pending';

    const CREATED = 'created';

    const RETURNED = 'returned';

    const RETURN_FROM_SHAREPOINT = 'returned_from_sharepoint';

    const READ_FOR_SHAREPOINT = 'read_for_sharepoint';

    const SHAREPOINT = 'sharepoint';

    public static function getBadgeMap(): array
    {
        return [
            self::INCOMPLETE => 'info',
            self::INITIATED => 'info',
            self::SUBMITTED => 'success',
            self::APPROVED => 'success',
            self::DECLINED => 'danger',
            self::COMPLETED => 'success',
            self::STEPPED_DOWN => 'danger',
            self::PENDING => 'warning',
            self::CREATED => 'info',
            self::RETURNED => 'danger',
            self::RETURN_FROM_SHAREPOINT => 'warning',
            self::READ_FOR_SHAREPOINT => 'info',
            self::SHAREPOINT => 'success',
        ];
    }

    public static function getBadgeIcons(): array
    {
        return [
            'info' => 'exclamation-circle',
            'danger' => 'exclamation-circle',
            'success' => 'check-circle',
            'warning' => 'exclamation-circle',
        ];
    }
}
