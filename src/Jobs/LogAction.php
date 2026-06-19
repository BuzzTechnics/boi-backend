<?php

namespace Boi\Backend\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued audit writer. Records an action against $model (or, when no model is
 * given, against the acting user resolved from $user_id) via the
 * {@see \Boi\Backend\Models\Concerns\Auditable} `audits` relationship.
 */
class LogAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected $model,
        protected string $action,
        protected ?array $changes = null,
        protected ?int $user_id = null,
        protected ?string $created_at = null,
        protected ?string $updated_at = null,
        protected ?string $ip_address = null,
    ) {
        $this->onQueue('logs');
    }

    public function handle(): void
    {
        $subject = $this->model;

        if ($subject === null && $this->user_id !== null) {
            $userModel = config('auth.providers.users.model');
            $subject = $userModel ? $userModel::find($this->user_id) : null;
        }

        $subject?->audits()->create([
            'user_id' => $this->user_id,
            'action' => $this->action,
            'changed_columns' => $this->changes,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at ?? now(),
            'updated_at' => $this->updated_at ?? now(),
        ]);
    }
}
