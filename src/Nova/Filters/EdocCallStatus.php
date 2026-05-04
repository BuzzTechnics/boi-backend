<?php

namespace Boi\Backend\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class EdocCallStatus extends Filter
{
    public $name = 'Status';

    public function apply(NovaRequest $request, $query, $value)
    {
        return $query
            ->when($value === 'successful', fn ($q) => $q->where('succeeded', true))
            ->when($value === 'failed', fn ($q) => $q->where('succeeded', false));
    }

    public function options(NovaRequest $request): array
    {
        return [
            'Successful' => 'successful',
            'Failed' => 'failed',
        ];
    }
}
