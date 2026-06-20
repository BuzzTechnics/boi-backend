<?php

namespace Boi\Backend\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Re-run EDOC bank-statement consolidation for the selected applications
 * (roles 1/3 only), via the model's edocConsolidate() behaviour.
 * Shared across interventions; apps extend with an empty subclass.
 */
class RerunEdocConsolidation extends Action
{
    public $name = 'Rerun EDOC consolidation';

    public function handle(ActionFields $fields, Collection $models)
    {
        $user = request()->user();
        if (! $user || ! in_array($user->role_id, [1, 3], true)) {
            return Action::danger('You are not authorized to run this action.');
        }

        $succeeded = [];
        $failed = [];

        foreach ($models as $application) {
            $result = $application->edocConsolidate();
            if ($result['success']) {
                $succeeded[] = $application->id;
            } else {
                $failed[] = '#'.$application->id.': '.$result['message'];
            }
        }

        if ($failed !== [] && $succeeded === []) {
            return Action::danger(implode(' ', $failed));
        }

        $parts = [];
        if ($succeeded !== []) {
            $parts[] = 'OK: '.implode(', ', $succeeded).'.';
        }
        if ($failed !== []) {
            $parts[] = 'Failed: '.implode(' ', $failed);
        }

        return Action::message(implode(' ', $parts));
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
