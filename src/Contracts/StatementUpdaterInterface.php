<?php

namespace Boi\Backend\Contracts;

interface StatementUpdaterInterface
{
    /**
     * Update a bank statement by ID (e.g. set consent_id, csv_url, edoc_status, statement_generated).
     *
     * @param  int  $bankStatementId
     * @param  array<string, mixed>  $data
     * @return object|null Updated model or null
     */
    public function update(int $bankStatementId, array $data): ?object;
}
