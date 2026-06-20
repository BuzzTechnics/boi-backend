<?php

namespace Boi\Backend\Nova\Actions;

use Boi\Backend\Enums\ApplicationStatus;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Return an application to the customer for updates, with a reason + optional
 * comments. Shared across interventions; apps extend with an empty subclass.
 */
class ReturnApplication extends Action
{
    use InteractsWithQueue, Queueable;

    public function name()
    {
        return 'Return to Customer';
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $application = $models->first();

        $canReturnFromSharepoint = $application->status === ApplicationStatus::INCOMPLETE
            && $application->internal_status === ApplicationStatus::RETURN_FROM_SHAREPOINT;

        if ($application->status === ApplicationStatus::INCOMPLETE && ! $canReturnFromSharepoint) {
            throw new Exception('Cannot return: Application is incomplete', 401);
        }

        if ($application->status === ApplicationStatus::APPROVED) {
            throw new Exception('Cannot return: Application already approved', 401);
        }

        if ($application->status === ApplicationStatus::DECLINED) {
            throw new Exception('Cannot return: Application already declined', 401);
        }

        $models->each(function ($model) use ($fields) {
            $model->update([
                'internal_status' => ApplicationStatus::RETURNED,
                'rejection_reason' => $fields->rejection_reason,
                'project_officer_comments' => $fields->comments ?? null,
            ]);
        });

        return Action::message(
            'Application(s) returned successfully. User will be notified to update their application.'
        );
    }

    public function fields(NovaRequest $request)
    {
        return [
            Select::make('Rejection Reason', 'rejection_reason')
                ->options([
                    'Incomplete Documentation' => 'Incomplete Documentation',
                    'Missing Required Information' => 'Missing Required Information',
                    'Incorrect Information Provided' => 'Incorrect Information Provided',
                    'Additional Documents Required' => 'Additional Documents Required',
                    'Business Plan Needs Revision' => 'Business Plan Needs Revision',
                    'Financial Information Incomplete' => 'Financial Information Incomplete',
                    'Other' => 'Other',
                ])
                ->rules('required'),

            Textarea::make('Additional Comments', 'comments')
                ->rules('nullable')
                ->help('Optional additional comments for the applicant'),
        ];
    }
}
