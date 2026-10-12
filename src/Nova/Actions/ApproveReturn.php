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
 * return the application to the applicant. Hidden from, and refused for, anyone
 * but a configured checker who did not raise the request.
 */
class ApproveReturn extends Action
{
    public function __construct()
    {
        $this->canSee(fn ($request) => app(ReturnRequestAuthorizer::class)->canReview($request->user()))
            ->canRun(fn ($request, $model) => $model->isPending()
                && app(ReturnRequestAuthorizer::class)->canReviewRequest($request->user(), $model));
    }

    public function name()
    {
        return 'Approve Return';
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->count() !== 1) {
            return Action::danger('Approve one request at a time.');
        }

        try {
            app(ReturnApprovalService::class)->approve($models->first(), auth()->user(), $fields->comment);
        } catch (StatusChangeRequestException $e) {
            return Action::danger($e->getMessage());
        }

        return Action::message('Return approved. The application has been returned to the applicant for updates.');
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
