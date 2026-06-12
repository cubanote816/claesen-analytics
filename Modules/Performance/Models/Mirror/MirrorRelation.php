<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorRelation extends Model
{
    protected $table = 'intelligence_mirror_relations';

    protected $fillable = [
        'id',
        'name',
        'zipcode',
        'city',
        'country',
        'language',
        'vat_number',
        'email',
        'phone',
        'contact_name',
    ];

    /**
     * Returns true if this client's preferred output language is Dutch.
     */
    public function getIsNlAttribute(): bool
    {
        return in_array(strtolower($this->language ?? 'nl'), ['nl', 'nld']);
    }
}
