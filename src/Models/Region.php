<?php

namespace Boi\Backend\Models;

use Boi\Backend\Models\Concerns\UsesBoiApiDatabase;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    use UsesBoiApiDatabase;

    protected $fillable = ['name'];

    public function states()
    {
        return $this->hasMany(State::class);
    }

    public function internalRegions()
    {
        return $this->hasMany(InternalRegion::class);
    }
}

