<?php

namespace Boi\Backend\Nova\Actions;

use Boi\Backend\Enums\ApplicationStatus;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

/**
 * Approve an application for SharePoint: mark it read-for-sharepoint + submitted.
 * Shared across interventions; apps extend with an empty subclass.
 */
class ApproveForSharepoint extends Action
{
    use InteractsWithQueue, Queueable;

    public function handle(ActionFields $fields, Collection $models)
    {
        $application = $models->first();

        if ($application->status === ApplicationStatus::INCOMPLETE) {
            throw new Exception('Cannot approve: Application is incomplete', 401);
        }

        $application->internal_status = ApplicationStatus::READ_FOR_SHAREPOINT;
        $application->status = ApplicationStatus::SUBMITTED;
        $application->save();

        return Action::message('Application Approved For Sharepoint!');
    }
}
