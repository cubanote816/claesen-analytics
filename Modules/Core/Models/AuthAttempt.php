<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthAttempt extends Model
{
    public const EVENT_FAILED = 'failed';
    public const EVENT_BLOCKED = 'blocked';
    public const EVENT_THROTTLED = 'throttled';
    public const EVENT_OAUTH_FAILED = 'oauth_failed';

    protected $table = 'core_auth_attempts';

    protected $fillable = [
        'user_id',
        'login_identifier',
        'event_type',
        'app_source',
        'auth_channel',
        'failure_reason',
        'session_id',
        'ip_address',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
