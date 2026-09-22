<?php

namespace Boi\Backend\Tests\Support;

use Boi\Backend\DocumentLibrary\Contracts\DocumentOwnerResolver;

/** Resolves a webhook to a fixed FakeCase + FakeUser for the tests. */
class FakeOwnerResolver implements DocumentOwnerResolver
{
    public function resolve(array $payload): ?array
    {
        $case = FakeCase::query()->where('workflow_id', $payload['workflowId'] ?? null)->first();
        if (! $case) {
            return null;
        }

        $user = FakeUser::query()->find($case->user_id);

        return [
            'documentable' => $case,
            'user_id' => $case->user_id,
            'company_id' => null,
            'notifiable' => $user,
        ];
    }
}
