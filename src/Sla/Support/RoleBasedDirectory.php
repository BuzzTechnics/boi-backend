<?php

namespace Boi\Backend\Sla\Support;

use Boi\Backend\Sla\Contracts\SlaDirectory;
use Boi\Backend\Sla\Models\SlaTracker;
use Illuminate\Support\Collection;

/**
 * A directory for the common case: recipients are "everyone holding role X", where
 * X is named per stage in the SLA definition or in config.
 *
 * A portal with anything more particular — routing to the officer actually assigned,
 * or to the business unit that owns the case — implements {@see SlaDirectory} itself
 * and keeps this as a base class for the parts it does not need to change.
 */
abstract class RoleBasedDirectory implements SlaDirectory
{
    /**
     * Users holding any of these role names.
     *
     * @param  array<int, string>  $roles
     * @return Collection<int, mixed>
     */
    abstract protected function usersWithRoles(array $roles): Collection;

    public function owners(SlaTracker $tracker): Collection
    {
        // A tracker with a named owner beats any role list: the case belongs to that
        // person, and telling their whole role group is neither accurate nor kind.
        if ($tracker->owner_id !== null) {
            $named = $this->usersById([(int) $tracker->owner_id]);

            if ($named->isNotEmpty()) {
                return $named;
            }
        }

        return $this->usersWithRoles(app(SlaDefinitions::class)->rolesFor($tracker->case_type, 'owner_roles'));
    }

    public function supervisors(SlaTracker $tracker, int $level): Collection
    {
        return $this->usersWithRoles(
            app(SlaDefinitions::class)->rolesFor($tracker->case_type, "level_{$level}_roles")
        );
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, mixed>
     */
    abstract protected function usersById(array $ids): Collection;
}
