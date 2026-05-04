<?php

namespace Boi\Backend\Nova\Metrics;

use Boi\Backend\Models\EdocCall;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class EdocCallsByStatus extends Partition
{
    public $name = 'Calls by Status';

    public function calculate(NovaRequest $request)
    {
        return $this->count($request, EdocCall::class, 'succeeded')
            ->label(fn ($value) => $value ? 'Successful' : 'Failed')
            ->colors([
                1 => '#10b981', // emerald-500 — succeeded
                0 => '#ef4444', // red-500 — failed
            ]);
    }

    public function uriKey(): string
    {
        return 'edoc-calls-by-status';
    }
}
