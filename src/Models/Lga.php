<?php

namespace Boi\Backend\Models;

use Boi\Backend\Models\Concerns\UsesBoiApiDatabase;
use Illuminate\Database\Eloquent\Model;

class Lga extends Model
{
    use UsesBoiApiDatabase;

    protected $fillable = ['name', 'state_id', 'internal_region_id', 'internal_region_id_2'];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function internalRegion()
    {
        return $this->belongsTo(InternalRegion::class);
    }

    public function internalRegion2()
    {
        return $this->belongsTo(InternalRegion::class, 'internal_region_id_2');
    }

    /**
     * @return list<int|string>
     */
    public static function idsForInternalRegion(int|string $internalRegionId): array
    {
        return static::query()
            ->where('internal_region_id', $internalRegionId)
            ->orWhere('internal_region_id_2', $internalRegionId)
            ->pluck('id')
            ->toArray();
    }
}

