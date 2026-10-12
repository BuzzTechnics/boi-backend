<?php

namespace Boi\Backend\Nova\Actions;

use Boi\Backend\Exceptions\StatusChangeRequestException;
use Boi\Backend\Services\ReturnApprovalService;
use Boi\Backend\Support\ReturnRequestAuthorizer;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Checker step on a pending return {@see \Boi\Backend\Models\StatusChangeRequest}:
 * refuse the return. The application is left unchanged.
 */
class RejectReturn extends Action
{
    public function __construct()
    {
        $this->canSee(fn ($request) => app(ReturnRequestAuthorizer::class)->canReview($request->user()))
            ->canRun(fn ($request, $model) => $model->isPending()
                && app(ReturnRequestAuthorizer::class)->canReviewRequest($request->user(), $model));
    }

    public function name()
    {
        return 'Reject Return';
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->count() !== 1) {
            return Action::danger('Reject one request at a time.');
        }

        try {
            app(ReturnApprovalService::class)->reject($models->first(), auth()->user(), (string) $fields->reason);
        } catch (StatusChangeRequestException $e) {
            return Action::danger($e->getMessage());
        }

        return Action::message('Return request rejected. The application is unchanged.');
    }

    public function fields(NovaRequest $request)
    {
        return [
            Textarea::make('Reason', 'reason')
                ->rules('required', 'string', 'max:5000')
                ->help('Recorded with the rejection and shared with the requester.'),
        ];
    }
}
