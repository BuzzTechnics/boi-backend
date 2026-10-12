<?php

namespace Boi\Backend\Support;

/**
 * Who may act in the return-approval maker-checker flow. Roles and permission
 * come from {@see config('boi_backend.return_approval')}; all the logic lives
 * in {@see StatusChangeAuthorizer}.
 */
class ReturnRequestAuthorizer extends StatusChangeAuthorizer
{
    protected function configKey(): string
    {
        return 'return_approval';
    }
}
