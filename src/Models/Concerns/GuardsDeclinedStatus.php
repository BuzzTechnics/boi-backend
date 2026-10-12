<?php

namespace Boi\Backend\Models\Concerns;

use Boi\Backend\Enums\ApplicationStatus;
use Boi\Backend\Exceptions\DeclinedApplicationLockedException;
use Boi\Backend\Support\ApprovedStatusTransition;
use Illuminate\Database\Eloquent\Model;

/**
 * Last line of defence for the maker-checker decline reversal: a declined
 * application cannot be saved as returned, or moved off `declined`, unless the
 * save is an approved reversal ({@see \Boi\Backend\Services\DeclineReversalService}).
 *
 * Covers every Eloquent save path (Nova actions, Nova update forms, tinker,
 * jobs). Query-builder mass updates bypass model events and are not covered.
 */
trait GuardsDeclinedStatus
{
    public static function bootGuardsDeclinedStatus(): void
    {
        static::updating(function (Model $model): void {
            if (static::reopensDeclinedApplication($model) && ! ApprovedStatusTransition::isApproved($model)) {
                throw DeclinedApplicationLockedException::make();
            }
        });
    }

    protected static function reopensDeclinedApplication(Model $model): bool
    {
        if ($model->getOriginal('status') !== ApplicationStatus::DECLINED) {
            return false;
        }

        $leavesDeclined = $model->isDirty('status') && $model->status !== ApplicationStatus::DECLINED;
        $returned = $model->isDirty('internal_status') && $model->internal_status === ApplicationStatus::RETURNED;

        return $leavesDeclined || $returned;
    }
}
