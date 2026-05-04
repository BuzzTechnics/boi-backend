<?php

namespace Boi\Backend\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per Edoc HTTP call. Written by EdocCallLogger; surfaced via the
 * Nova\EdocCall resource in host apps that have Nova installed.
 *
 * @property string $project
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
class EdocCall extends Model
{
    protected $table = 'edoc_calls';

    public $timestamps = false;

    protected $fillable = [
        'project',
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
