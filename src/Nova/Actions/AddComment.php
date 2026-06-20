<?php

namespace Boi\Backend\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Add a comment to the selected application(s). The program-specific Comment
 * model is supplied by {@see commentModel()}.
 */
abstract class AddComment extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Add Comment';

    /** @return class-string the program's Comment model. */
    abstract protected function commentModel(): string;

    public function handle(ActionFields $fields, Collection $models)
    {
        $user = request()->user();
        $raw = $fields->get('comment') ?? $fields->get('Comment');
        $commentText = trim((string) (is_string($raw) || (is_scalar($raw) && $raw !== null) ? $raw : ''));

        if ($commentText === '') {
            return Action::danger('Comment cannot be empty.');
        }

        $commentModel = $this->commentModel();
        foreach ($models as $application) {
            $commentModel::create([
                'user_id' => $user->getKey(),
                'comment' => $commentText,
                'transactionable_id' => $application->getKey(),
                'transactionable_type' => $application->getMorphClass(),
            ]);
        }

        $count = $models->count();

        return Action::message($count === 1 ? 'Comment added.' : "Comment added to {$count} applications.");
    }

    public function fields(NovaRequest $request)
    {
        return [
            Textarea::make('Comment')
                ->rules('required')
                ->help('Add a comment to the selected application(s).'),
        ];
    }
}
