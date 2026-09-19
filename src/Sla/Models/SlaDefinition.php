<?php

namespace Boi\Backend\Sla\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a stage is called, how long it has, and who hears about it — the part of the
 * SLA a business owner should be able to change without a deploy.
 *
 * A portal can run entirely from config/boi_sla.php; a row here overrides it. That
 * ordering matters: a fund goes live on the values in the repository, and the table
 * only exists so BOI can adjust them afterwards in Nova.
 */
class SlaDefinition extends Model
{
    protected $table = 'boi_sla_definitions';

    protected $fillable = [
        'app',
        'case_type',
        'name',
        'description',
        'sla_minutes',
        'assignment_minutes',
        'owner_roles',
        'level_1_roles',
        'level_2_roles',
        'level_3_roles',
        'thresholds',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sla_minutes' => 'integer',
            'assignment_minutes' => 'integer',
            'owner_roles' => 'array',
            'level_1_roles' => 'array',
            'level_2_roles' => 'array',
            'level_3_roles' => 'array',
            'thresholds' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForApp($query, ?string $app)
    {
        // A row with no app belongs to whichever portal is asking: that is how a
        // single-fund install uses the table without filling in a name everywhere.
        return $query->where(fn ($q) => $q->whereNull('app')->orWhere('app', $app));
    }
}
