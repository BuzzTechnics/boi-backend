<?php

namespace Boi\Backend\Nova\Actions;

use Boi\Backend\Enums\ApplicationStatus;
use Boi\Backend\Exceptions\StatusChangeRequestException;
use Boi\Backend\Services\ReturnApprovalService;
use Boi\Backend\Support\ReturnRequestAuthorizer;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Maker step: ask for an under-review application to be returned to the
 * customer. Creates a pending request only; the application is not returned
 * until a Super Admin approves it. Runs alongside the direct ReturnApplication
 * action. Apps extend with an empty subclass.
 */
class RequestReturn extends Action
{
    public function __construct()
    {
        $this->canSee(fn ($request) => app(ReturnRequestAuthorizer::class)->canRequest($request->user()))
            ->canRun(fn ($request, $model) => app(ReturnRequestAuthorizer::class)->canRequest($request->user())
                && $this->isReturnable($model));
    }

    public function name()
    {
        return 'Request Return to Customer';
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->count() !== 1) {
            return Action::danger('Select a single application to return.');
        }

        try {
            app(ReturnApprovalService::class)->request(
                $models->first(),
                auth()->user(),
                (string) $fields->justification,
                (string) $fields->rejection_reason,
                $fields->comments,
            );
        } catch (StatusChangeRequestException $e) {
            return Action::danger($e->getMessage());
        }

        return Action::message('Return requested. The application is not returned until a Super Admin approves it.');
    }

    public function fields(NovaRequest $request)
    {
        return [
            Textarea::make('Justification', 'justification')
                ->rules('required', 'string', 'max:5000')
                ->help('Internal: why this application should be returned. Seen by the approving Super Admin, not the applicant.'),

            Select::make('Return Reason', 'rejection_reason')
                ->options(ReturnApplication::returnReasonOptions())
                ->rules('required')
                ->help('Shown to the applicant if the return is approved.'),

            Textarea::make('Comments for Applicant', 'comments')
                ->rules('nullable', 'string', 'max:5000')
                ->help('Optional. Sent to the applicant with the return notice if approved.'),
        ];
    }

    protected function isReturnable($model): bool
    {
        if ($model->internal_status === ApplicationStatus::RETURNED
            || $model->status === ApplicationStatus::APPROVED
            || $model->status === ApplicationStatus::DECLINED) {
            return false;
        }

        $canReturnFromSharepoint = $model->status === ApplicationStatus::INCOMPLETE
            && $model->internal_status === ApplicationStatus::RETURN_FROM_SHAREPOINT;

        return $model->status !== ApplicationStatus::INCOMPLETE || $canReturnFromSharepoint;
    }
}
