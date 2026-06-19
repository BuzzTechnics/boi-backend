<?php

namespace Boi\Backend\Models\Concerns;

use Boi\Backend\Models\Audit;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model a polymorphic `audits` relationship. Used with
 * {@see \Boi\Backend\Jobs\LogAction} to record changes against the model.
 */
trait Auditable
{
    public function audits(): MorphMany
    {
        return $this->morphMany(Audit::class, 'auditable');
    }
}
