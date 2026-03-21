<?php

namespace Boi\Backend\Models\Concerns;

/**
 * Use on Eloquent models whose table lives in the boi-api database (e.g. banks).
 *
 * Connection name comes from config('boi_api.eloquent_connection'), matching
 * a key in config('database.connections'). When unset or invalid, the model
 * uses the application default connection.
 */
trait UsesBoiApiDatabase
{
    public function getConnectionName(): ?string
    {
        $name = config('boi_api.eloquent_connection');

        if (is_string($name) && $name !== '' && array_key_exists($name, config('database.connections', []))) {
            return $name;
        }

        return parent::getConnectionName();
    }
}
