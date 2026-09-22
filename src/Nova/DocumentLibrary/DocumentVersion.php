<?php

namespace Boi\Backend\Nova\DocumentLibrary;

use Boi\Backend\DocumentLibrary\Models\DocumentVersion as DocumentVersionModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/** A single customer upload against a document. Reached through its panel; read-only. */
class DocumentVersion extends Resource
{
    public static $model = DocumentVersionModel::class;

    public static $title = 'original_name';

    public static $search = ['id', 'original_name'];

    public static $displayInNavigation = false;

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->reorder('version', 'desc');
    }

    public static function label(): string
    {
        return 'Versions';
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Number::make('Version')->sortable(),
            Text::make('File', 'original_name')->displayUsing(fn ($v) => $v ?: '—'),
            Number::make('Size (KB)', 'size_kb')->hideFromIndex(),
            Number::make('Uploaded By (User ID)', 'uploaded_by')->hideFromIndex(),
            Text::make('Review Decision', 'review_decision')->displayUsing(fn ($v) => $v ? ucfirst($v) : '—'),
            Textarea::make('Review Comment', 'review_comment')->onlyOnDetail(),
            DateTime::make('Reviewed At', 'reviewed_at')->exceptOnForms(),
            DateTime::make('Uploaded At', 'created_at')->exceptOnForms()->sortable(),
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
