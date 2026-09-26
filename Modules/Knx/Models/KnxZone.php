<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;
use Modules\Knx\Database\Factories\KnxZoneFactory;

/**
 * A site-readiness zone: a room that must reach "ready for integration".
 *
 * There is no `status` column anywhere on purpose. The contract (§1.3) says the
 * status is derived from the checks on every read and every write, and the
 * derivation lives here so there is exactly one implementation of it.
 */
class KnxZone extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    public const STATUS_NOT_READY = 'not_ready';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_READY = 'ready';

    public const STATUS_BLOCKED = 'blocked';

    protected $table = 'knx_zones';

    protected $fillable = ['organization_id', 'project_id', 'room_id', 'name', 'floor', 'next_review_at'];

    protected static function newFactory(): KnxZoneFactory
    {
        return KnxZoneFactory::new();
    }

    protected function casts(): array
    {
        return ['next_review_at' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(KnxProjectRoom::class, 'room_id');
    }

    /** Always the eight keys, in config order (see config/knx.php). */
    public function checks(): HasMany
    {
        return $this->hasMany(KnxZoneCheck::class, 'zone_id');
    }

    public function functionSpecs(): HasMany
    {
        return $this->hasMany(KnxFunctionSpec::class, 'zone_id');
    }

    public function check(string $key): ?KnxZoneCheck
    {
        return $this->checks->firstWhere('key', $key);
    }

    /**
     * The applicable checks, in display order — `na` means "does not apply here"
     * and is excluded from the derivation.
     *
     * @return Collection<int, KnxZoneCheck>
     */
    public function applicableChecks(): Collection
    {
        $order = array_flip(KnxZoneCheck::keys());

        return $this->relationLoaded('checks')
            ? $this->checks
                ->filter(fn (KnxZoneCheck $check): bool => $check->status !== KnxZoneCheck::STATUS_NA)
                ->sortBy(fn (KnxZoneCheck $check): int => $order[$check->key] ?? 99)
                ->values()
            : collect();
    }

    /**
     * §1.3, verbatim:
     *   none applicable            → not_ready
     *   any failed                 → blocked
     *   all passed                 → ready
     *   none passed                → not_ready
     *   otherwise                  → in_progress
     */
    public function derivedStatus(): string
    {
        $applicable = $this->applicableChecks();

        if ($applicable->isEmpty()) {
            return self::STATUS_NOT_READY;
        }

        if ($applicable->contains(fn (KnxZoneCheck $check): bool => $check->status === KnxZoneCheck::STATUS_FAILED)) {
            return self::STATUS_BLOCKED;
        }

        $passed = $applicable->filter(fn (KnxZoneCheck $check): bool => $check->status === KnxZoneCheck::STATUS_PASSED)->count();

        if ($passed === $applicable->count()) {
            return self::STATUS_READY;
        }

        return $passed === 0 ? self::STATUS_NOT_READY : self::STATUS_IN_PROGRESS;
    }

    /**
     * The FIRST failed check, which is the root cause: its note is the blocker
     * and its author owns it. The second failure is usually the follow-up.
     */
    public function blockingCheck(): ?KnxZoneCheck
    {
        $order = array_flip(KnxZoneCheck::keys());

        return $this->checks
            ->filter(fn (KnxZoneCheck $check): bool => $check->status === KnxZoneCheck::STATUS_FAILED)
            ->sortBy(fn (KnxZoneCheck $check): int => $order[$check->key] ?? 99)
            ->first();
    }

    public function isReady(): bool
    {
        return $this->derivedStatus() === self::STATUS_READY;
    }
}
