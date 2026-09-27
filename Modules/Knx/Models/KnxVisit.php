<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Knx\Database\Factories\KnxVisitFactory;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * A closure signed off from site (V11.e, CLA-609).
 *
 * The three types are phases of the same delivery, not alternatives:
 * `visit_end` (a day's work), `partial` (part of the scope handed over) and `final`
 * (acceptance). The office reads them as the project's history, which is why they
 * live in one table.
 *
 * `minutes` is what turns the office's `hours` report from a refusal into data.
 *
 * The location follows the same rule as a field device registration: the room row is
 * the authority when the name matches one, and the app's own label is kept only when
 * it does not — so nothing the technician saw is lost, and there is no second copy of
 * a name the office already owns.
 */
class KnxVisit extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    public const TYPE_VISIT_END = 'visit_end';

    public const TYPE_PARTIAL = 'partial';

    public const TYPE_FINAL = 'final';

    /** In the order they happen. */
    public const TYPES = [self::TYPE_VISIT_END, self::TYPE_PARTIAL, self::TYPE_FINAL];

    public const ITEM_PENDING = 'pending';

    public const ITEM_RESERVATION = 'reservation';

    public const ITEM_VERIFIED_FUNCTION = 'verified_function';

    public const ITEM_DOCUMENT = 'document';

    public const ITEM_KINDS = [
        self::ITEM_PENDING,
        self::ITEM_RESERVATION,
        self::ITEM_VERIFIED_FUNCTION,
        self::ITEM_DOCUMENT,
    ];

    protected $table = 'knx_visits';

    protected $fillable = [
        'organization_id',
        'project_id',
        'room_id',
        'room_label',
        'client_id',
        'type',
        'work_done',
        'minutes',
        'signed_by',
        'captured_at',
        'closed_by_employee_id',
    ];

    protected static function newFactory(): KnxVisitFactory
    {
        return KnxVisitFactory::new();
    }

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(KnxProjectRoom::class, 'room_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'closed_by_employee_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KnxVisitItem::class, 'visit_id')->orderBy('position')->orderBy('id');
    }

    /**
     * The place to show, exactly like the rest of the module: the room the office
     * knows, or the name the technician read on site.
     */
    public function location(): ?string
    {
        return $this->room?->name ?? $this->room_label;
    }

    /**
     * @return list<string>
     */
    public function itemsOfKind(string $kind): array
    {
        return $this->items
            ->where('kind', $kind)
            ->pluck('label')
            ->values()
            ->all();
    }
}
