<?php

namespace Boi\Backend\DocumentLibrary\Concerns;

use Boi\Backend\DocumentLibrary\Models\DocumentRequest;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Add to a fund's case model (Application / LoanApplication) to give it the shared
 * document library: {@see documentRequests()} returns the requests raised against it.
 */
trait HasDocumentRequests
{
    public function documentRequests(): MorphMany
    {
        return $this->morphMany(DocumentRequest::class, 'documentable');
    }
}
