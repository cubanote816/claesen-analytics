<?php

namespace Modules\Prospects\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectMailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'prospect_mail_campaign_id',
        'prospect_id',
        'user_id',
        'email',
        'template_name',
        'subject_snapshot',
        'body_snapshot',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ProspectMailCampaign::class, 'prospect_mail_campaign_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\User::class);
    }
}
