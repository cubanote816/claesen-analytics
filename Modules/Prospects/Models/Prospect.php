<?php

namespace Modules\Prospects\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospect extends Model
{
    use HasFactory;
 
    protected $table = 'prospects_prospects';


    protected $fillable = [
        'external_id',
        'name',
        'type',
        'region_id',
        'federation',
        'language',
        'contact_person',
        'channel',
        'logo_url',
        'website',
        'vat_number',
        'cafca_relation_id',
    ];

    public function region(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProspectLocation::class);
    }
}
