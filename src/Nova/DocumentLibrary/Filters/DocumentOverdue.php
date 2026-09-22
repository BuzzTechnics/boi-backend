<?php

namespace Boi\Backend\Nova\DocumentLibrary\Filters;

use Boi\Backend\DocumentLibrary\Models\DocumentRequest;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class DocumentOverdue extends Filter
{
    public $component = 'select-filter';

    public $name = 'Due';

    public function apply(NovaRequest $request, $query, $value)
    {
        if ($value === 'overdue') {
            return $query->whereNotNull('due_at')->where('due_at', '<', now())
                ->where('status', '!=', DocumentRequest::STATUS_COMPLETE);
        }
        if ($value === 'upcoming') {
            return $query->whereNotNull('due_at')->where('due_at', '>=', now())
                ->where('status', '!=', DocumentRequest::STATUS_COMPLETE);
        }

        return $query;
    }

    public function options(NovaRequest $request): array
    {
        return ['Overdue' => 'overdue', 'Upcoming' => 'upcoming'];
    }
}
