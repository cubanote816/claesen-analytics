<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Database\Factories\KnxNotificationFactory;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;
use Modules\Knx\Observers\KnxNotificationObserver;

/**
 * A notice for the office. Until KNX-54 it was only "a device the field registered"
 * (`kind: device_registered`); now `kind` says WHAT happened and the row also carries
 * the human line (`title`/`summary`) and its `target`. The old device columns stay, so
 * the existing inbox keeps working unchanged.
 *
 * `board_id` nullable is meaningful: a field registration can report a board the office
 * has never seen, which is exactly what the office must triage. `address`/`type` are
 * nullable because a cabinet notice has neither.
 *
 * Creating a row is what triggers the email decision (KNX-54): the observer sends the
 * immediate kinds and leaves the rest for the digest. A field retry creates no second
 * row, so it can never duplicate a mail.
 */
#[ObservedBy(KnxNotificationObserver::class)]
class KnxNotification extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    /** A device registered from site. */
    public const KIND_DEVICE_REGISTERED = 'device_registered';

    public const KIND_BOARD_CREATED = 'board_created';

    public const KIND_MODULE_ADDED = 'module_added';

    public const KIND_MODULE_CHANGED = 'module_changed';

    public const KIND_ADDRESS_CONFLICT = 'address_conflict';

    public const KIND_INTENT_CHANGED = 'intent_changed';

    public const KIND_ISSUE_REPORTED = 'issue_reported';

    public const KIND_MALFUNCTION_REPORTED = 'malfunction_reported';

    public const KIND_MALFUNCTION_STATUS_CHANGED = 'malfunction_status_changed';

    public const KIND_VISIT_CLOSED = 'visit_closed';

    public const KINDS = [
        self::KIND_DEVICE_REGISTERED,
        self::KIND_BOARD_CREATED,
        self::KIND_MODULE_ADDED,
        self::KIND_MODULE_CHANGED,
        self::KIND_ADDRESS_CONFLICT,
        self::KIND_INTENT_CHANGED,
        self::KIND_ISSUE_REPORTED,
        self::KIND_MALFUNCTION_REPORTED,
        self::KIND_MALFUNCTION_STATUS_CHANGED,
        self::KIND_VISIT_CLOSED,
    ];

    protected $table = 'knx_notifications';

    protected $fillable = [
        'organization_id',
        'project_id',
        'device_id',
        'issue_id',
        'board_id',
        'room_id',
        'module_slot',
        'kind',
        'type',
        'title',
        'summary',
        'address',
        'room',
        'serial',
        'target_type',
        'target_id',
        'reported_by_employee_id',
        'reported_at',
        'captured_at',
        'acknowledged_at',
        'emailed_at',
    ];

    protected static function newFactory(): KnxNotificationFactory
    {
        return KnxNotificationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'captured_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(KnxDevice::class, 'device_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(KnxBoard::class, 'board_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(KnxProjectRoom::class, 'room_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(KnxConflict::class, 'issue_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'reported_by_employee_id');
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function isAcked(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
