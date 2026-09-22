<?php

namespace Boi\Backend\Nova\DocumentLibrary\Actions;

use Boi\Backend\DocumentLibrary\DocumentLibraryService;
use Boi\Backend\DocumentLibrary\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

/** Re-push a submitted package to the workflow (manual retry). */
class PushToWorkflow extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = '☁️ Push to workflow';

    public $confirmText = 'Re-send this submitted document package to the workflow?';

    public $confirmButtonText = 'Push';

    public function handle(ActionFields $fields, Collection $models)
    {
        $service = app(DocumentLibraryService::class);
        $pushed = 0;
        $eligible = 0;

        foreach ($models as $model) {
            /** @var DocumentRequest $model */
            if ($model->submitted_at === null) {
                continue;
            }
            $eligible++;
            if ($service->transmit($model)) {
                $pushed++;
            }
        }

        if ($eligible === 0) {
            return Action::danger('Only submitted requests can be pushed.');
        }

        if ($pushed === 0) {
            return Action::danger('The push failed or no submit URL is configured.');
        }

        return Action::message("Pushed {$pushed} package(s) to the workflow.");
    }
}
