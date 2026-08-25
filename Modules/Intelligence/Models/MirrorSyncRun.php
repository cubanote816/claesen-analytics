<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\User;

class MirrorSyncRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const SOURCE_SCHEDULED = 'scheduled';
    public const SOURCE_MANUAL = 'manual';

    protected $table = 'intelligence_mirror_sync_runs';

    protected $fillable = [
        'status',
        'trigger_source',
        'triggered_by_user_id',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function durationSeconds(): ?int
    {
        if (! $this->finished_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at);
    }
}
