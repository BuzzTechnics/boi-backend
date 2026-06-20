<?php

namespace Boi\Backend\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Queue a report for a single application. The program-specific report job is
 * supplied by {@see reportJob()}.
 */
abstract class SingleApplicationReport extends Action
{
    use InteractsWithQueue, Queueable;

    public $withoutActionEvents = true;

    public static $chunkCount = 200000;

    /** @return class-string the single-application report job (ctor: int $userId, int $applicationId). */
    abstract protected function reportJob(): string;

    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();
        $job = $this->reportJob();

        dispatch(new $job(auth()->id(), $model->id));

        return Action::message('Report is being generated. Please check notifications for download link.');
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
