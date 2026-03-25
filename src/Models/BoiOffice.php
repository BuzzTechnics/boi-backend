<?php

namespace Boi\Backend\Models;

use Boi\Backend\Models\Concerns\UsesBoiApiDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoiOffice extends Model
{
    use UsesBoiApiDatabase;
    use SoftDeletes;

    protected $fillable = ['name', 'state_id', 'internal_region_id'];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function internalRegion()
    {
        return $this->belongsTo(InternalRegion::class);
    }
}

