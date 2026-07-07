<?php

declare(strict_types=1);

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Analytics\Enums\AppSource;
use Modules\Analytics\Enums\EventName;
use Modules\Core\Models\User;

class AppEvent extends Model
{
    // Append-only event log — rows are never updated after insertion.
    public const UPDATED_AT = null;

    protected $table = 'app_events';

    protected $fillable = [
        'event_name',
        'app',
        'user_id',
        'session_id',
        'employee_id',
        'entity_type',
        'entity_id',
        'role_snapshot',
        'properties',
        'duration_ms',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_name' => EventName::class,
            'app' => AppSource::class,
            'role_snapshot' => 'array',
            'properties' => 'array',
            'duration_ms' => 'integer',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
