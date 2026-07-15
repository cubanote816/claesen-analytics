<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessEvent extends Model
{
    public const EVENT_LOGIN = 'login';
    public const EVENT_LOGOUT = 'logout';

    protected $table = 'core_access_events';

    protected $fillable = [
        'user_id',
        'event_type',
        'app_source',
        'auth_channel',
        'session_id',
        'access_token_name',
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
