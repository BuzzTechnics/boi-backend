<?php

namespace Boi\Backend\Models;

use Boi\Backend\Models\Concerns\UsesBoiApiDatabase;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use UsesBoiApiDatabase;

    protected $fillable = ['name', 'region_id', 'internal_region_id', 'boi_office_id'];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function internalRegion()
    {
        return $this->belongsTo(InternalRegion::class);
    }

    public function boiOffice()
    {
        return $this->belongsTo(BoiOffice::class, 'boi_office_id');
    }

    public function lgas()
    {
        return $this->hasMany(Lga::class);
    }

    public function boiOffices()
    {
        return $this->hasMany(BoiOffice::class);
    }
}

