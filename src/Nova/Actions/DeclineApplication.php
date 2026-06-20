<?php

namespace Boi\Backend\Nova\Actions;

use Boi\Backend\Enums\ApplicationStatus;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Decline the selected applications, dispatching the program-specific decline
 * job (supplied by {@see declineJob()}) per application.
 */
abstract class DeclineApplication extends Action implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public static $chunkCount = 100;

    /** @return class-string the decline job (dispatch: id, plainMessage, message). */
    abstract protected function declineJob(): string;

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->contains(fn ($application) => $application->status === ApplicationStatus::DECLINED)) {
            throw new Exception('Cannot decline: One or more applications already declined', 401);
        }

        $message = $fields->message;
        $plainMessage = strip_tags($message);
        $job = $this->declineJob();

        $models->pluck('id')->each(function ($id) use ($plainMessage, $message, $job) {
            $job::dispatch($id, $plainMessage, $message);
        });

        return Action::message('Application(s) declined successfully.');
    }

    public function fields(NovaRequest $request)
    {
        return [
            Trix::make('Message')
                ->rules('required'),
        ];
    }
}
