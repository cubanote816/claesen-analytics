<?php

namespace Modules\Prospects\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectMailCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'template_name',
        'description',
        'subject_snapshot',
        'body_snapshot',
        'total_count',
        'success_count',
        'failed_count',
        'skipped_count',
        'status',
        'finished_at',
    ];

    protected $casts = [
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProspectMailLog::class, 'prospect_mail_campaign_id');
    }
}
