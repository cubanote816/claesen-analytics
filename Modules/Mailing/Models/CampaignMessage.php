<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Prospects\Models\Prospect;

class CampaignMessage extends Model
{
    use HasFactory;

    protected $table = 'mailing_messages';

    protected $fillable = [
        'campaign_id',
        'prospect_id',
        'user_id',
        'email',
        'template_name',
        'subject_snapshot',
        'body_snapshot',
        'status',
        'error_message',
        'sent_at',
        'tracking_token',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message) {
            if (empty($message->tracking_token)) {
                $message->tracking_token = Str::random(64);
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MessageEvent::class, 'message_id');
    }

    public function hasEvent(MessageEventType $type): bool
    {
        return $this->events()->where('event_type', $type->value)->exists();
    }
}
