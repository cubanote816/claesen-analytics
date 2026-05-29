<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\SuppressionReason;
use Modules\Prospects\Models\Prospect;

class SuppressionEntry extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return \Modules\Mailing\Database\Factories\SuppressionEntryFactory::new();
    }

    protected $table = 'mailing_suppression_list';

    protected $fillable = [
        'email',
        'prospect_id',
        'reason',
        'source_campaign_id',
        'notes',
        'suppressed_at',
        'suppressed_by',
    ];

    protected $casts = [
        'reason'       => SuppressionReason::class,
        'suppressed_at' => 'datetime',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function sourceCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'source_campaign_id');
    }

    public function suppressedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suppressed_by');
    }
}
