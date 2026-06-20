<?php

namespace Boi\Backend\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Open a public, time-limited link for a single application. The program builds
 * the URL via {@see generatePublicUrl()} (typically its ApplicationController).
 */
abstract class GeneratePublicLink extends Action
{
    public $name = 'Generate Public Link';

    public $showInline = true;

    /** Build the public URL for the given application id + expiry window. */
    abstract protected function generatePublicUrl(int $applicationId, int $expiresInDays): string;

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->count() > 1) {
            return Action::danger('Please select only one application at a time.');
        }

        return Action::openInNewTab(
            $this->generatePublicUrl($models->first()->id, (int) ($fields->expires_in_days ?? 7))
        );
    }

    public function fields(NovaRequest $request)
    {
        return [
            Number::make('Expires In Days', 'expires_in_days')->default(7)->min(1)->max(365),
        ];
    }
}
