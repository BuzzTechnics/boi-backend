<?php

namespace Boi\Backend\Nova\DocumentLibrary;

use Boi\Backend\DocumentLibrary\Models\Document as DocumentModel;
use Boi\Backend\Nova\DocumentLibrary\Actions\WaiveDocument;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/** A required item within a {@see DocumentRequest}. Reached through its panel; read-only. */
class Document extends Resource
{
    public static $model = DocumentModel::class;

    public static $title = 'name';

    public static $search = ['id', 'name', 'reference'];

    public static $displayInNavigation = false;

    public static function label(): string
    {
        return 'Documents';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Request', 'request', DocumentRequest::class)->exceptOnForms(),
            Text::make('Name')->sortable(),
            Text::make('Category')->hideFromIndex(),
            Boolean::make('Mandatory', 'is_mandatory'),
            Badge::make('Status')->map([
                DocumentModel::STATUS_REQUESTED => 'info',
                DocumentModel::STATUS_UPLOADED => 'warning',
                DocumentModel::STATUS_ACCEPTED => 'success',
                DocumentModel::STATUS_RETURNED => 'danger',
                DocumentModel::STATUS_WAIVED => 'info',
            ]),
            Textarea::make('Officer Comment', 'officer_comment')->onlyOnDetail(),
            Text::make('Instructions')->onlyOnDetail(),
            HasMany::make('Versions', 'versions', DocumentVersion::class),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            (new WaiveDocument)->canRun(fn ($request, $model) => $model === null
                || ! in_array($model->status, [DocumentModel::STATUS_ACCEPTED, DocumentModel::STATUS_WAIVED], true)),
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
