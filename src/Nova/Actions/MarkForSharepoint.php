<?php

namespace Boi\Backend\Nova\Actions;

use Boi\Backend\Enums\ApplicationStatus;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Mark submitted applications as read-for-sharepoint (roles 1/3 only).
 * Shared across interventions; apps extend with an empty subclass.
 */
class MarkForSharepoint extends Action
{
    use InteractsWithQueue, Queueable;

    public function handle(ActionFields $fields, Collection $models)
    {
        $user = request()->user();
        if (! in_array($user->role_id, [1, 3])) {
            throw new Exception('You are not authorized to mark applications for sharepoint', 401);
        }

        if ($models->first()->status !== ApplicationStatus::SUBMITTED) {
            throw new Exception('Application status is not Submitted', 401);
        }

        $models->each(fn ($model) => $model->update(['internal_status' => ApplicationStatus::READ_FOR_SHAREPOINT]));

        return Action::message('Applications marked for sharepoint');
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
