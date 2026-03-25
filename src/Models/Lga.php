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
}

