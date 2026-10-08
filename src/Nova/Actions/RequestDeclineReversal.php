<?php

namespace Boi\Backend\Nova\Actions;

use Boi\Backend\Enums\ApplicationStatus;
use Boi\Backend\Exceptions\StatusChangeRequestException;
use Boi\Backend\Services\DeclineReversalService;
use Boi\Backend\Support\DeclineReversalAuthorizer;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Maker step: ask for a declined application to be reopened (declined →
 * returned). Creates a pending request only; the application stays declined
 * until a Super Admin approves it. Apps extend with an empty subclass.
 *
 * Visibility and per-row run checks default to the configured maker rule;
 * {@see DeclineReversalService} re-checks everything when it runs.
 */
class RequestDeclineReversal extends Action
{
    public function __construct()
    {
        $this->canSee(fn ($request) => app(DeclineReversalAuthorizer::class)->canRequest($request->user()))
            ->canRun(fn ($request, $model) => app(DeclineReversalAuthorizer::class)->canRequest($request->user())
                && $model->status === ApplicationStatus::DECLINED
                && $model->internal_status === ApplicationStatus::DECLINED);
    }

    public function name()
    {
        return 'Request Decline Reversal';
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->count() !== 1) {
            return Action::danger('Select a single declined application.');
        }

        try {
            app(DeclineReversalService::class)->request(
                $models->first(),
                auth()->user(),
                (string) $fields->justification,
                (string) $fields->rejection_reason,
                $fields->comments,
            );
        } catch (StatusChangeRequestException $e) {
            return Action::danger($e->getMessage());
        }

        return Action::message('Decline reversal requested. The application stays declined until a Super Admin approves it.');
    }

    public function fields(NovaRequest $request)
    {
        return [
            Textarea::make('Justification', 'justification')
                ->rules('required', 'string', 'max:5000')
                ->help('Internal: why this decline should be reversed. Seen by the approving Super Admin, not the applicant.'),

            Select::make('Return Reason', 'rejection_reason')
                ->options(ReturnApplication::returnReasonOptions())
                ->rules('required')
                ->help('Shown to the applicant if the reversal is approved.'),

            Textarea::make('Comments for Applicant', 'comments')
                ->rules('nullable', 'string', 'max:5000')
                ->help('Optional. Sent to the applicant with the return notice if approved.'),
        ];
    }
}
