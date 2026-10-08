<?php

namespace Boi\Backend\Support;

use Boi\Backend\Models\StatusChangeRequest;
use Throwable;

/**
 * Who may act in the decline-reversal maker-checker flow. A user needs one of
 * the configured roles AND the configured permission
 * ({@see config('boi_backend.decline_reversal')}); checkers may never review a
 * request they raised. Apps can rebind this class in the container to change
 * the rules without touching the service.
 */
class DeclineReversalAuthorizer
{
    public function canRequest($user): bool
    {
        return $this->qualifies($user, 'maker');
    }

    public function canReview($user): bool
    {
        return $this->qualifies($user, 'checker');
    }

    /** Checker for this specific request: never the user who raised it. */
    public function canReviewRequest($user, StatusChangeRequest $request): bool
    {
        return $this->canReview($user) && ! $this->isRequester($user, $request);
    }

    public function canView($user): bool
    {
        return $this->canRequest($user) || $this->canReview($user);
    }

    public function isRequester($user, StatusChangeRequest $request): bool
    {
        return $user !== null
            && (string) $user->getAuthIdentifier() === (string) $request->requested_by;
    }

    protected function qualifies($user, string $side): bool
    {
        if ($user === null) {
            return false;
        }

        $config = (array) config('boi_backend.decline_reversal', []);

        return $this->hasAnyRole($user, (array) ($config["{$side}_roles"] ?? []))
            && $this->hasPermission($user, $config["{$side}_permission"] ?? null);
    }

    /** @param  array<int, string>  $roles */
    protected function hasAnyRole($user, array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        if (method_exists($user, 'hasRoleName')) {
            return (bool) $user->hasRoleName($roles);
        }

        if (method_exists($user, 'hasRole')) {
            return (bool) $user->hasRole($roles);
        }

        return false;
    }

    protected function hasPermission($user, ?string $permission): bool
    {
        // Fail closed: an unset permission never grants access.
        if ($permission === null || $permission === '' || ! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        try {
            return (bool) $user->hasPermissionTo($permission);
        } catch (Throwable) {
            // Spatie throws when the permission row does not exist yet:
            // treat as "not granted" rather than a 500.
            return false;
        }
    }
}
