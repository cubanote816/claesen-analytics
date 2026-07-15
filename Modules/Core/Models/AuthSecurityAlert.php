<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class AuthSecurityAlert extends Model
{
    protected $table = 'core_auth_security_alerts';

    protected $fillable = [
        'alert_type',
        'alert_key',
        'window_started_at',
        'window_ended_at',
        'attempt_count',
        'identifier',
        'ip_address',
        'metadata',
        'notified_at',
    ];

    protected $casts = [
        'window_started_at' => 'datetime',
        'window_ended_at' => 'datetime',
        'metadata' => 'array',
        'notified_at' => 'datetime',
    ];
}
