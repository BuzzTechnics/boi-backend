<?php

namespace Boi\Backend\Nova\DocumentLibrary\Actions;

use Boi\Backend\DocumentLibrary\Models\Document;
use Boi\Backend\DocumentLibrary\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/** Waive a document requirement so it no longer blocks completion; records the reason. */
class WaiveDocument extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Waive requirement';

    public $confirmButtonText = 'Waive';

    public $confirmText = 'Waive this document requirement? It will no longer block completion.';

    public function fields(NovaRequest $request): array
    {
        return [Textarea::make('Reason', 'reason')->rules('required', 'max:1000')];
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $waived = 0;
        $requestIds = [];

        foreach ($models as $document) {
            /** @var Document $document */
            if (in_array($document->status, [Document::STATUS_ACCEPTED, Document::STATUS_WAIVED], true)) {
                continue;
            }
            $document->update(['status' => Document::STATUS_WAIVED, 'officer_comment' => $fields->reason]);
            $requestIds[$document->boi_document_request_id] = true;
            $waived++;
        }

        DocumentRequest::whereIn('id', array_keys($requestIds))->with('documents')->get()->each->recomputeStatus();

        if ($waived === 0) {
            return Action::danger('Nothing to waive — already accepted or waived.');
        }

        return Action::message("Waived {$waived} document requirement(s).");
    }
}
