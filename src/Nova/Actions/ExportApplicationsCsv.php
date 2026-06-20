<?php

namespace Boi\Backend\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Http\Requests\ActionRequest;

/**
 * Queue a CSV export for the current selection/filters. The program-specific
 * export job is supplied by {@see csvExportJob()}.
 */
abstract class ExportApplicationsCsv extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Export As CSV';

    public $withoutActionEvents = true;

    /** @return class-string the CSV export job (ctor: array $criteria). */
    abstract protected function csvExportJob(): string;

    public function dispatchRequestUsing(ActionRequest $request, $response, $fields)
    {
        $criteria = [
            'userId' => auth()->id(),
            'resources' => $request->allResourcesSelected() ? 'all' : $request->selectedResourceIds()->toArray(),
            'filters' => $request->filters ?? null,
            'search' => $request->search ?? '',
            'trashed' => $request->trashed()->value ?? '',
        ];

        if ($criteria['resources'] !== 'all' && empty($criteria['resources'])) {
            return $response->failed();
        }

        $job = $this->csvExportJob();
        dispatch(new $job($criteria));

        return $response->successful([ActionResponse::message('CSV export queued. Check notifications when ready.')]);
    }
}
