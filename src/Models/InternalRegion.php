<?php

namespace Boi\Backend\Models;

use Boi\Backend\Models\Concerns\UsesBoiApiDatabase;
use Illuminate\Database\Eloquent\Model;

class InternalRegion extends Model
{
    use UsesBoiApiDatabase;

    protected $fillable = ['name', 'region_id'];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function states()
    {
        return $this->hasMany(State::class);
    }

    public function lgas()
    {
        return $this->hasMany(Lga::class);
    }

    public function lgas2()
    {
        return $this->hasMany(Lga::class, 'internal_region_id_2');
    }

    public function boiOffices()
    {
        return $this->hasMany(BoiOffice::class);
    }
}

