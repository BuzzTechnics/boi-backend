<?php

namespace Boi\Backend\Nova\DocumentLibrary;

use Boi\Backend\DocumentLibrary\Models\Document as DocumentModel;
use Boi\Backend\DocumentLibrary\Models\DocumentRequest as DocumentRequestModel;
use Boi\Backend\Nova\DocumentLibrary\Actions\PushToWorkflow;
use Boi\Backend\Nova\DocumentLibrary\Actions\WaiveDocument;
use Boi\Backend\Nova\DocumentLibrary\Filters\DocumentOverdue;
use Boi\Backend\Nova\DocumentLibrary\Filters\DocumentStage;
use Boi\Backend\Nova\DocumentLibrary\Filters\DocumentStatus;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * Shared oversight of the customer document library (every fund). Read-only: the
 * workflow requests and reviews, the customer uploads; Nova is the window, plus a
 * re-push and a waive.
 */
class DocumentRequest extends Resource
{
    public static $model = DocumentRequestModel::class;

    public static $title = 'reference';

    public static $search = ['id', 'reference', 'app'];

    public static $with = ['documents'];

    public static function label(): string
    {
        return 'Document Requests';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Fund', 'app')->sortable(),
            Text::make('Reference')->sortable(),
            Text::make('Case', fn () => $this->documentable_type ? class_basename($this->documentable_type).' #'.$this->documentable_id : '—')->exceptOnForms(),
            Text::make('Stage')->exceptOnForms(),
            Badge::make('Status')->map([
                DocumentRequestModel::STATUS_REQUESTED => 'info',
                DocumentRequestModel::STATUS_AWAITING_CUSTOMER => 'warning',
                DocumentRequestModel::STATUS_SUBMITTED => 'info',
                DocumentRequestModel::STATUS_UNDER_REVIEW => 'info',
                DocumentRequestModel::STATUS_RETURNED => 'danger',
                DocumentRequestModel::STATUS_COMPLETE => 'success',
            ]),
            Text::make('Accepted', function () {
                $m = $this->documents->where('is_mandatory', true);
                $t = $m->count();
                if ($t === 0) {
                    return '—';
                }
                $a = $m->whereIn('status', [DocumentModel::STATUS_ACCEPTED, DocumentModel::STATUS_WAIVED])->count();

                return "{$a}/{$t}";
            })->exceptOnForms(),
            Textarea::make('Message')->onlyOnDetail(),
            DateTime::make('Requested At', 'requested_at')->exceptOnForms()->sortable(),
            DateTime::make('Due At', 'due_at')->exceptOnForms(),
            Text::make('Overdue', fn () => $this->isOverdue() ? '⚠️ Overdue' : '—')->onlyOnIndex(),
            DateTime::make('Submitted At', 'submitted_at')->exceptOnForms(),
            HasMany::make('Documents', 'documents', Document::class),
        ];
    }

    public function filters(NovaRequest $request): array
    {
        return [new DocumentStatus, new DocumentStage, new DocumentOverdue];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            (new PushToWorkflow)->canRun(fn ($request, $model) => $model?->submitted_at !== null),
        ];
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }
}
