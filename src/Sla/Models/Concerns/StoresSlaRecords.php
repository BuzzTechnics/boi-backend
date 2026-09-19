<?php

namespace Boi\Backend\Sla\Models\Concerns;

/**
 * Where a fund keeps its SLA records.
 *
 * By default each portal keeps its own, on its own database. Setting
 * `boi_sla.connection` to a connection name — boi-api's, in practice — moves all
 * three tables there, so every fund's clocks and escalations sit in one place and a
 * single report can cover the programme. The `app` column on each row is what keeps
 * the funds apart once they share a table.
 *
 * Deliberately its own setting rather than riding on boi_api.eloquent_connection: a
 * portal that reads shared reference data from boi-api has not thereby agreed to
 * keep its audit trail there, and that is not a decision to make by side effect.
 */
trait StoresSlaRecords
{
    public function getConnectionName(): ?string
    {
        $name = config('boi_sla.connection');

        if (is_string($name) && $name !== '' && array_key_exists($name, config('database.connections', []))) {
            return $name;
        }

        return parent::getConnectionName();
    }
}
