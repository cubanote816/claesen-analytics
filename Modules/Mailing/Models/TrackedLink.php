<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackedLink extends Model
{
    public $timestamps = false;

    protected $table = 'mailing_tracked_links';

    protected $fillable = [
        'campaign_id',
        'original_url',
        'hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
