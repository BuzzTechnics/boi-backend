<?php

namespace Boi\Backend\Nova;

use Boi\Backend\Models\EdocCall as EdocCallModel;
use Boi\Backend\Nova\Filters\EdocCallStatus;
use Boi\Backend\Nova\Metrics\EdocCallsByStatus;
use Boi\Backend\Nova\Metrics\SuccessfulEdocCalls;
use Boi\Backend\Nova\Metrics\TotalEdocCalls;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * Read-only audit-trail resource for outbound Edoc HTTP calls. Host apps register
 * this in `Nova::resources([...])` (or the `resources()` method on their
 * NovaServiceProvider). Records are written by Boi\Backend\Services\EdocCallLogger.
 */
class EdocCall extends Resource
{
    public static $model = EdocCallModel::class;

    public static $title = 'endpoint';

    public static $search = [
        'endpoint',
        'method',
        'project',
        'exception_class',
    ];

    public static $perPageOptions = [25, 50, 100];

    public static function label(): string
    {
        return 'Edoc Calls';
    }

    public static function singularLabel(): string
    {
        return 'Edoc Call';
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->orderByDesc('created_at');
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            DateTime::make('When', 'created_at')->sortable()->filterable(),

            Text::make('Project')->sortable()->filterable(),

            Text::make('Method')->sortable(),

            Text::make('Endpoint')->displayUsing(fn ($v) => $v ? mb_strimwidth((string) $v, 0, 80, '…') : null),

            Number::make('Status', 'response_status')->sortable(),

            Boolean::make('OK', 'succeeded')->sortable(),

            Number::make('Duration (ms)', 'duration_ms')->sortable(),

            Number::make('User', 'user_id')->onlyOnDetail(),

            Text::make('Exception', 'exception_class')->onlyOnDetail(),

            Code::make('Request Payload', 'request_payload')->json()->onlyOnDetail(),

            Code::make('Response Body', 'response_body')->json()->onlyOnDetail(),
        ];
    }

    public function filters(NovaRequest $request): array
    {
        return [
            new EdocCallStatus,
        ];
    }

    public function cards(NovaRequest $request): array
    {
        return [
            (new TotalEdocCalls)->width('1/3'),
            (new SuccessfulEdocCalls)->width('1/3'),
            (new EdocCallsByStatus)->width('1/3'),
        ];
    }

    public static function authorizedToCreate(\Illuminate\Http\Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(\Illuminate\Http\Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(\Illuminate\Http\Request $request): bool
    {
        return false;
    }
}
