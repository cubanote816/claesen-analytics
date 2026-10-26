<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Site;

/**
 * F4/CLA-475 — see Modules\Website\Console\Commands\
 * CheckIntakeAbuseAlertsCommand and the migration's docblock for the
 * hourly-bucket idempotency shape.
 */
class WebsiteAbuseAlert extends Model
{
    protected $table = 'website_abuse_alerts';

    protected $fillable = [
        'site_id',
        'alert_bucket',
        'attempt_count',
        'threshold',
        'window_minutes',
        'notified_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'threshold' => 'integer',
        'window_minutes' => 'integer',
        'notified_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
