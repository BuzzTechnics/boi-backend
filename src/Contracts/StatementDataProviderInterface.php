<?php

namespace Boi\Backend\Contracts;

interface StatementDataProviderInterface
{
    /**
     * Get data needed for EDOC manual upload (email, account_number, name).
     *
     * @param  int  $bankStatementId
     * @return array{email: string, account_number: string, name: string}
     */
    public function get(int $bankStatementId): array;
}
