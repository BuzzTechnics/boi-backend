<?php

namespace Boi\Backend\Models;

use Boi\Backend\Models\Concerns\UsesBoiApiDatabase;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per outbound BVN/NIN verification call. Written by BvnNinCallLogger;
 * surfaced via the Nova\BvnNinCall resource in host apps that have Nova.
 *
 * Like EdocCall, this uses UsesBoiApiDatabase so consuming apps with a
 * `boi_api` connection (glow / spaf) read and write the shared boi-api
 * bvn_nin_calls table — one global audit view. Hosts without that connection
 * (boi-api itself) fall back to their own default DB.
 *
 * PII: the raw BVN/NIN is never stored — only `identifier_masked` (e.g.
 * ******7890). Responses are whitelisted to identity-summary fields by the
 * logger before persistence.
 *
 * @property string $project
 * @property string $kind            'bvn' | 'nin'
 * @property string|null $identifier_masked
 * @property string $method
 * @property string $endpoint
 * @property array|null $request_payload
 * @property int|null $response_status
 * @property mixed $response_body
 * @property int|null $duration_ms
 * @property int|null $user_id
 * @property string|null $exception_class
 * @property bool $succeeded
 * @property \Illuminate\Support\Carbon $created_at
 */
class BvnNinCall extends Model
{
    use UsesBoiApiDatabase;

    protected $table = 'bvn_nin_calls';

    public $timestamps = false;

    protected $fillable = [
        'project',
        'kind',
        'identifier_masked',
        'method',
        'endpoint',
        'request_payload',
        'response_status',
        'response_body',
        'duration_ms',
        'user_id',
        'exception_class',
        'succeeded',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_body' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'user_id' => 'integer',
            'succeeded' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
