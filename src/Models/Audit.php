<?php

namespace Boi\Backend\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic audit trail row. Written via {@see \Boi\Backend\Jobs\LogAction}
 * and the {@see Concerns\Auditable} trait. Each app owns its `audits` table
 * migration (program-side); this base provides the shared behaviour only.
 */
class Audit extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'auditable_id',
        'auditable_type',
        'changed_columns',
        'ip_address',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'changed_columns' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class));
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
