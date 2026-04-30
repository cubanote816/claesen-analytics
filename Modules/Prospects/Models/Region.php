<?php

namespace Modules\Prospects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $table = 'prospects_regions';

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function booted()
    {
        static::creating(function ($region) {
            if (empty($region->slug)) {
                $region->slug = str($region->name)->slug();
            }
        });
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class, 'region_id');
    }
}
