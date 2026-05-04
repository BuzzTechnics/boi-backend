<?php

namespace Boi\Backend\Nova\Metrics;

use Boi\Backend\Models\EdocCall;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class SuccessfulEdocCalls extends Value
{
    public $name = 'Successful Calls';

    public function calculate(NovaRequest $request)
    {
        return $this->count($request, EdocCall::query()->where('succeeded', true));
    }

    public function ranges(): array
    {
        return [
            30 => '30 Days',
            60 => '60 Days',
            365 => 'Last Year',
            'TODAY' => 'Today',
            'MTD' => 'Month To Date',
            'YTD' => 'Year To Date',
        ];
    }

    public function cacheFor()
    {
        return now()->addMinute();
    }

    public function uriKey(): string
    {
        return 'successful-edoc-calls';
    }
}
