<?php

namespace Boi\Backend\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Queue a full (all-records) report for the current user. The program-specific
 * report job is supplied by {@see totalReportJob()}.
 */
abstract class GenerateTotalReport extends Action
{
    use InteractsWithQueue, Queueable;

    public $withoutActionEvents = true;

    public static $chunkCount = 200000;

    /** @return class-string the total-report job (ctor: int $userId). */
    abstract protected function totalReportJob(): string;

    public function handle(ActionFields $fields, Collection $models)
    {
        $job = $this->totalReportJob();
        dispatch(new $job(auth()->id()));

        return ActionResponse::message(
            'Report is Exporting, Please Check Notifications Bell At the Top Navigation bar'
        );
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
