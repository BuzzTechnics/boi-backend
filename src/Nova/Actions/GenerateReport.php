<?php

namespace Boi\Backend\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Http\Requests\ActionRequest;

/**
 * Queue a filtered applications report for the current selection/filters. The
 * program-specific report job is supplied by {@see reportJob()}.
 */
abstract class GenerateReport extends Action
{
    use InteractsWithQueue, Queueable;

    public $withoutActionEvents = true;

    public static $chunkCount = 200000;

    /** @return class-string the report job (ctor: array $criteria). */
    abstract protected function reportJob(): string;

    public function dispatchRequestUsing(ActionRequest $request, $response, $fields)
    {
        $criteria = [
            'userId' => auth()->id(),
            'resources' => $request->allResourcesSelected() ? 'all' : $request->selectedResourceIds()->toArray(),
            'filters' => $request->filters ?? null,
            'search' => $request->search ?? '',
            'trashed' => $request->trashed()->value ?? '',
        ];
        info('[GenerateReport] criteria', [
            'userId' => $criteria['userId'],
            'resources' => is_array($criteria['resources'])
                ? count($criteria['resources']).' ids'
                : $criteria['resources'],
        ]);

        if ($criteria['resources'] !== 'all' && empty($criteria['resources'])) {
            info('[GenerateReport] no resources selected');

            return $response->failed();
        }

        $job = $this->reportJob();
        dispatch(new $job($criteria));

        return $response->successful([
            ActionResponse::message('Report is Exporting, Please Check Notifications Bell At the Top Navigation bar'),
        ]);
    }
}
