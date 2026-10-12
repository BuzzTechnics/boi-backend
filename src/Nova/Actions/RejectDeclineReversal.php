<?php

namespace Boi\Backend\Nova\Actions;

use Boi\Backend\Exceptions\StatusChangeRequestException;
use Boi\Backend\Services\DeclineReversalService;
use Boi\Backend\Support\DeclineReversalAuthorizer;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Checker step on a pending {@see \Boi\Backend\Models\StatusChangeRequest}:
 * refuse the reversal. The application stays declined.
 */
class RejectDeclineReversal extends Action
{
    public function __construct()
    {
        $this->canSee(fn ($request) => app(DeclineReversalAuthorizer::class)->canReview($request->user()))
            ->canRun(fn ($request, $model) => $model->isPending()
                && app(DeclineReversalAuthorizer::class)->canReviewRequest($request->user(), $model));
    }

    public function name()
    {
        return 'Reject Decline Reversal';
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->count() !== 1) {
            return Action::danger('Reject one request at a time.');
        }

        try {
            app(DeclineReversalService::class)->reject($models->first(), auth()->user(), (string) $fields->reason);
        } catch (StatusChangeRequestException $e) {
            return Action::danger($e->getMessage());
        }

        return Action::message('Decline reversal rejected. The application remains declined.');
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
