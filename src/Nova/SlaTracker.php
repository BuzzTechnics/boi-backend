<?php

namespace Boi\Backend\Nova;

use Boi\Backend\Sla\Models\SlaTracker as SlaTrackerModel;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * Every SLA clock, running or finished — which is what makes "how long does NSDC
 * review actually take?" answerable. A stamp on the case itself is lost the moment
 * the case moves on; these rows are not.
 *
 * Read-only: the engine owns them.
 */
class SlaTracker extends Resource
{
    public static $model = SlaTrackerModel::class;

    public static $title = 'case_type';

    public static $search = ['case_type', 'case_id'];

    public static $perPageOptions = [25, 50, 100];

    public static function label(): string
    {
        return 'SLA Trackers';
    }

    public static function singularLabel(): string
    {
        return 'SLA Tracker';
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->orderByDesc('start_time');
    }

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Case', fn () => "{$this->case_type} #{$this->case_id}")->sortable(),

            Text::make('Type')->sortable()->filterable(),

            Badge::make('Status')
                ->map([
                    'in_progress' => 'info',
                    'completed' => 'success',
                    'breached' => 'danger',
                    'cancelled' => 'warning',
                ])
                ->sortable()
                ->filterable(),

            DateTime::make('Started', 'start_time')->sortable(),

            DateTime::make('Due', 'deadline')->sortable()
                ->help('Working time only: weekends, public holidays and hours outside 08:00–16:00 do not count.'),

            DateTime::make('Completed', 'completed_at')->sortable(),

            Number::make('Owner', 'owner_id')->onlyOnDetail(),
            Number::make('Group', 'group_id')->onlyOnDetail(),

            DateTime::make('Reminder sent', 'reminder_sent_at')->onlyOnDetail(),
            DateTime::make('Escalation 1 sent', 'escalation1_sent_at')->onlyOnDetail(),
            DateTime::make('Escalation 2 sent', 'escalation2_sent_at')->onlyOnDetail(),
            DateTime::make('Escalation 3 sent', 'escalation3_sent_at')->onlyOnDetail(),

            Text::make('Notes')->onlyOnDetail(),
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
