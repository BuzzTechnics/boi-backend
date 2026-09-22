<?php

namespace Boi\Backend\Nova\DocumentLibrary\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class DocumentStage extends Filter
{
    public $component = 'select-filter';

    public $name = 'Stage';

    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->when($value !== null && $value !== '', fn ($q) => $q->where('stage', $value));
    }

    /** Options come from the fund's configured stages. */
    public function options(NovaRequest $request): array
    {
        $out = [];
        foreach ((array) config('boi_document_library.stages', []) as $key => $stage) {
            $out[$stage['label'] ?? $key] = $key;
        }

        return $out;
    }
}
