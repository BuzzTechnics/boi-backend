<?php

namespace Boi\Backend\DocumentLibrary\Contracts;

/**
 * Tells the shared engine which case a webhook request belongs to, and who the
 * customer is — the one fund-specific piece. A fund binds its implementation and
 * the engine stays ignorant of whether the case is an Application or a
 * LoanApplication.
 */
interface DocumentOwnerResolver
{
    /**
     * Resolve the owning case for a request, from the workflow's identifiers.
     *
     * @param  array<string,mixed>  $payload  The webhook payload (workflowId, reference, …).
     * @return array{documentable: ?\Illuminate\Database\Eloquent\Model, user_id: ?int, company_id: ?int, notifiable: ?object}|null
     *   Null when no case matches. `notifiable` is what receives customer notifications.
     */
    public function resolve(array $payload): ?array;
}
