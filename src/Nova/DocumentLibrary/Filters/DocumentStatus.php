<?php

namespace Boi\Backend\Nova\DocumentLibrary\Filters;

use Boi\Backend\DocumentLibrary\Models\DocumentRequest;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class DocumentStatus extends Filter
{
    public $component = 'select-filter';

    public $name = 'Status';

    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->when($value !== null && $value !== '', fn ($q) => $q->where('status', $value));
    }

    public function options(NovaRequest $request): array
    {
        return [
            'Requested' => DocumentRequest::STATUS_REQUESTED,
            'Awaiting Customer' => DocumentRequest::STATUS_AWAITING_CUSTOMER,
            'Submitted' => DocumentRequest::STATUS_SUBMITTED,
            'Under Review' => DocumentRequest::STATUS_UNDER_REVIEW,
            'Returned' => DocumentRequest::STATUS_RETURNED,
            'Complete' => DocumentRequest::STATUS_COMPLETE,
        ];
    }
}
