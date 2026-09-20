<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\BelongsToSite;
use Modules\Core\Models\User;
use Modules\Website\Database\Factories\ConsultationRequestFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ConsultationRequest extends Model
{
    use BelongsToSite;
    use HasFactory;
    use LogsActivity;

    /**
     * F4/CLA-477 — the lead-inbox workflow (2026_09_20_120100 backfilled the
     * historical pending/contacted/in_progress/completed/cancelled set to
     * this one; see that migration's docblock for the exact mapping).
     */
    public const STATUS_NEW = 'new';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_CLIENT = 'waiting_client';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_SPAM = 'spam';

    protected static function newFactory(): ConsultationRequestFactory
    {
        return ConsultationRequestFactory::new();
    }

    protected $table = 'website_consultation_requests';

    protected $fillable = [
        'site_id',
        'name',
        'email',
        'phone',
        'company',
        'type',
        'project_type',
        'message',
        'preferred_contact',
        'status',
        'internal_notes',
        'contacted_at',
        'first_response_at',
        'assigned_to',
        'priority',
        'source',
        'estimated_value',
        'actual_value',
        'currency',
        'follow_up_date',
        'follow_up_notes',
        'tags',
        'custom_fields',
        'last_activity_at',
        'activity_count',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
        'first_response_at' => 'datetime',
        'follow_up_date' => 'date',
        'last_activity_at' => 'datetime',
        'tags' => 'array',
        'custom_fields' => 'array',
        'estimated_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * F4/CLA-477: "SLA de primera respuesta medible" — whole minutes between
     * creation and first_response_at (stamped by
     * ConsultationService::updateStatus() the first time status leaves
     * 'new'). Null while still unanswered — never a fabricated duration.
     */
    public function firstResponseSlaMinutes(): ?int
    {
        if (! $this->first_response_at) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($this->first_response_at);
    }

    /**
     * Sets first_response_at in-memory (never saves by itself) the first
     * time status moves away from 'new' — called from both write paths
     * that can change status: ConsultationRequestObserver::saving() (the
     * Filament edit form) and ConsultationService::updateStatus() (which
     * uses updateQuietly(), so it never fires the observer and must call
     * this explicitly). A no-op once first_response_at is already set —
     * the SLA measures the *first* response, never a later status change.
     */
    public function maybeStampFirstResponse(string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === self::STATUS_NEW && $newStatus !== self::STATUS_NEW && $this->first_response_at === null) {
            $this->first_response_at = now();
        }
    }

    public function activities()
    {
        return $this->hasMany(ConsultationActivity::class, 'consultation_request_id');
    }

    public function reminders()
    {
        return $this->hasMany(ConsultationReminder::class);
    }

    public function notifications()
    {
        return $this->hasMany(ConsultationNotification::class);
    }
}
