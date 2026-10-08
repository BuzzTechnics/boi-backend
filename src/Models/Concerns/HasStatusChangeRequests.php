<?php

namespace Boi\Backend\Models\Concerns;

use Boi\Backend\Models\StatusChangeRequest;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives an application its maker-checker {@see StatusChangeRequest} history.
 */
trait HasStatusChangeRequests
{
    public function statusChangeRequests(): MorphMany
    {
        return $this->morphMany(StatusChangeRequest::class, 'requestable');
    }
}
