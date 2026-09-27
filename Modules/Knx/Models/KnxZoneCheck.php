<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Database\Factories\KnxZoneCheckFactory;

/**
 * One readiness check of a zone. `updated_at` is the domain timestamp the
 * contract exposes (nullable until someone touches it), so this model does not
 * use Laravel's created_at/updated_at pair.
 */
class KnxZoneCheck extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PASSED = 'passed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NA = 'na';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PASSED,
        self::STATUS_FAILED,
        self::STATUS_NA,
    ];

    public $timestamps = false;

    protected $table = 'knx_zone_checks';

    protected $fillable = ['zone_id', 'key', 'status', 'note', 'updated_by_employee_id', 'updated_at'];

    protected static function newFactory(): KnxZoneCheckFactory
    {
        return KnxZoneCheckFactory::new();
    }

    protected function casts(): array
    {
        return ['updated_at' => 'datetime'];
    }

    /** The eight keys, in the order the UI renders them. */
    public static function keys(): array
    {
        return config('knx.zone_check_keys');
    }

    /** Both `failed` and `na` must explain themselves — see §1.4. */
    public function requiresNote(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_NA], true);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(KnxZone::class, 'zone_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'updated_by_employee_id');
    }
}
