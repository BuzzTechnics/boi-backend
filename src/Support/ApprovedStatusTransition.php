<?php

namespace Boi\Backend\Support;

use Illuminate\Database\Eloquent\Model;
use WeakMap;

/**
 * Marks one model INSTANCE as carrying an approved status change for the
 * duration of a callback, so {@see \Boi\Backend\Models\Concerns\GuardsDeclinedStatus}
 * lets that single save through. Keyed by object identity (WeakMap): another
 * instance of the same row, or any save after the callback, stays guarded.
 */
final class ApprovedStatusTransition
{
    private static ?WeakMap $approved = null;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(Model $model, callable $callback): mixed
    {
        self::$approved ??= new WeakMap;
        self::$approved[$model] = true;

        try {
            return $callback();
        } finally {
            unset(self::$approved[$model]);
        }
    }

    public static function isApproved(Model $model): bool
    {
        return self::$approved !== null && isset(self::$approved[$model]);
    }
}
