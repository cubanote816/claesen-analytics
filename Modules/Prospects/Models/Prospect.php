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
        'unsubscribed_at',
    ];

    /**
     * Scope a query to only include subscribed prospects.
     */
    public function scopeSubscribed($query)
    {
        return $query->whereNull('unsubscribed_at');
    }

    /**
     * Generate a secure unsubscribe token.
     */
    public function getUnsubscribeToken(): string
    {
        return hash_hmac('sha256', $this->id . $this->external_id, config('app.key'));
    }

    public function region(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProspectLocation::class);
    }
}
