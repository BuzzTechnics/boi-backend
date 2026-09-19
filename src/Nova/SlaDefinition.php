<?php

namespace Boi\Backend\Nova;

use Boi\Backend\Sla\Models\SlaDefinition as SlaDefinitionModel;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * The SLA numbers, in front of the people who own them.
 *
 * Each portal ships defaults in config/boi_sla.php; a row here overrides one, so
 * changing how long NSDC review gets is an afternoon's decision rather than a
 * release. Host apps register this in their Nova resources() and gate it on whatever
 * permission they use for configuration.
 */
class SlaDefinition extends Resource
{
    public static $model = SlaDefinitionModel::class;

    public static $title = 'name';

    public static $search = ['case_type', 'name'];

    public static function label(): string
    {
        return 'SLA Definitions';
    }

    public static function singularLabel(): string
    {
        return 'SLA Definition';
    }

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make('Case Type', 'case_type')
                ->sortable()
                ->rules('required', 'max:64')
                ->help('The stage key the portal uses, e.g. nsdc_review.'),

            Text::make('Name')->rules('required', 'max:255')
                ->help('How this stage is named in reminder and escalation emails.'),

            Textarea::make('Description')->hideFromIndex(),

            Number::make('SLA (working minutes)', 'sla_minutes')
                ->rules('required', 'integer', 'min:1')
                ->sortable()
                ->help('Working minutes only: 08:00–16:00, weekdays, excluding public holidays. One working day is 480.'),

            Number::make('Assignment SLA (minutes)', 'assignment_minutes')
                ->nullable()
                ->hideFromIndex()
                ->help('For the clock that runs on a pool before anyone holds the task. Leave empty if this stage has no such step.'),

            Text::make('Owner roles', fn () => $this->listRoles($this->owner_roles))->onlyOnIndex(),

            Text::make('Level 1 roles', fn () => $this->listRoles($this->level_1_roles))->onlyOnIndex(),

            Boolean::make('Active', 'is_active')->sortable(),
        ];
    }

    /** @param  array<int, string>|null  $roles */
    private function listRoles(?array $roles): string
    {
        // An empty slot is not "nobody" — it falls back to the portal's config, and
        // saying "—" here would read as a gap that needs filling.
        return $roles === null || $roles === [] ? 'from config' : implode(', ', $roles);
    }
}
