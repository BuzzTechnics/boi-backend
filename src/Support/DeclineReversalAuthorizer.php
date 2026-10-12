<?php

namespace Boi\Backend\Support;

/**
 * Who may act in the decline-reversal maker-checker flow. Roles and permission
 * come from {@see config('boi_backend.decline_reversal')}; all the logic lives
 * in {@see StatusChangeAuthorizer}.
 */
class DeclineReversalAuthorizer extends StatusChangeAuthorizer
{
    protected function configKey(): string
    {
        return 'decline_reversal';
    }
}
