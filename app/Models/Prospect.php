<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospect extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'region',
        'league',
        'league_id',
        'channel',
        'logo_url',
        'website',
        'vat_number',
        'cafca_relation_id',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(ProspectLocation::class);
    }
}
