<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Mailing\Enums\MessageEventType;

class MessageEvent extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return \Modules\Mailing\Database\Factories\MessageEventFactory::new();
    }

    protected $table = 'mailing_message_events';

    public const UPDATED_AT = null;

    protected $fillable = [
        'message_id',
        'event_type',
        'occurred_at',
        'metadata',
    ];

    protected $casts = [
        'event_type'  => MessageEventType::class,
        'occurred_at' => 'datetime',
        'metadata'    => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('chronological', function (Builder $query) {
            $query->orderBy('occurred_at');
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CampaignMessage::class, 'message_id');
    }
}
