<?php

namespace Boi\Backend\Nova;

use Boi\Backend\Sla\Models\SlaNotificationLog as SlaNotificationLogModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * What went out, when, to whom, and whether the channel took it (BRD §5.4-01).
 *
 * It exists to settle one argument out loud: when someone says they were never told,
 * this is the answer. Read-only, because §5.4-03 asks that audit records be
 * protected from alteration and deletion.
 */
class SlaNotificationLog extends Resource
{
    public static $model = SlaNotificationLogModel::class;

    public static $title = 'recipient_email';

    public static $search = ['recipient_email', 'recipient_name', 'notification_type', 'case_type'];

    public static $perPageOptions = [25, 50, 100];

    public static function label(): string
    {
        return 'SLA Notification Log';
    }

    public static function singularLabel(): string
    {
        return 'SLA Notification';
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->orderByDesc('created_at');
    }

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            DateTime::make('Sent At', 'created_at')->sortable()->filterable(),

            Text::make('Type', 'notification_type')
                ->displayUsing(fn ($value) => ucwords(str_replace('_', ' ', (string) $value)))
                ->sortable()
                ->filterable(),

            Text::make('Case', fn () => $this->case_type ? "{$this->case_type} #{$this->case_id}" : '—'),

            Text::make('Recipient', 'recipient_name')->sortable(),

            Text::make('Email', 'recipient_email')->sortable(),

            Text::make('Channel')->sortable()->filterable(),

            Badge::make('Status')
                ->map(['sent' => 'success', 'failed' => 'danger'])
                ->sortable()
                ->filterable(),

            Number::make('Tracker', 'tracker_id')->onlyOnDetail(),

            Text::make('Error')->onlyOnDetail(),
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
