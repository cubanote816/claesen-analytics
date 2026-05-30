<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mailing\Enums\DeliverabilityAlertType;

class DeliverabilityAlert extends Model
{
    protected $table = 'mailing_deliverability_alerts';

    protected $fillable = [
        'campaign_id',
        'alert_type',
        'rate',
        'threshold',
        'sent_count',
        'event_count',
        'notified_at',
    ];

    protected $casts = [
        'alert_type'   => DeliverabilityAlertType::class,
        'rate'         => 'float',
        'threshold'    => 'float',
        'sent_count'   => 'integer',
        'event_count'  => 'integer',
        'notified_at'  => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
