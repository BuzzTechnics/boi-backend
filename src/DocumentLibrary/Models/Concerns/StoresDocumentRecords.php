<?php

namespace Boi\Backend\DocumentLibrary\Models\Concerns;

/**
 * Where a fund keeps its document-library records. By default the portal's own
 * database; set boi_document_library.connection to a connection name (boi-api's) to
 * move all three tables there, told apart by the `app` column. Mirrors the SLA
 * engine's {@see \Boi\Backend\Sla\Models\Concerns\StoresSlaRecords}.
 */
trait StoresDocumentRecords
{
    public function getConnectionName(): ?string
    {
        $name = config('boi_document_library.connection');

        if (is_string($name) && $name !== '' && array_key_exists($name, config('database.connections', []))) {
            return $name;
        }

        return parent::getConnectionName();
    }
}
