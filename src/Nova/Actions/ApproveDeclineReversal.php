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
 * reopen the declined application as returned. Hidden from, and refused for,
 * anyone but a configured checker who did not raise the request.
 */
class ApproveDeclineReversal extends Action
{
    public function __construct()
    {
        $this->canSee(fn ($request) => app(DeclineReversalAuthorizer::class)->canReview($request->user()))
            ->canRun(fn ($request, $model) => $model->isPending()
                && app(DeclineReversalAuthorizer::class)->canReviewRequest($request->user(), $model));
    }

    public function name()
    {
        return 'Approve Decline Reversal';
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->count() !== 1) {
            return Action::danger('Approve one request at a time.');
        }

        try {
            app(DeclineReversalService::class)->approve($models->first(), auth()->user(), $fields->comment);
        } catch (StatusChangeRequestException $e) {
            return Action::danger($e->getMessage());
        }

        return Action::message('Decline reversed. The application has been returned to the applicant for updates.');
    }

    public function fields(NovaRequest $request)
    {
        return [
            Textarea::make('Comment', 'comment')
                ->rules('nullable', 'string', 'max:5000')
                ->help('Optional internal note recorded with the approval.'),
        ];
    }
}
