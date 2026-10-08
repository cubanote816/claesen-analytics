<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * One person's notification preferences (KNX-54).
 *
 * Created lazily with the fixed defaults, so a person who never opened the settings
 * screen still gets the behaviour the decisions describe.
 */
class KnxNotificationPreference extends Model
{
    use BelongsToKnxTenant;

    public const DIGEST_PER_VISIT = 'per_visit';

    public const DIGEST_HOURLY = 'hourly';

    public const DIGEST_OFF = 'off';

    public const DIGESTS = [self::DIGEST_PER_VISIT, self::DIGEST_HOURLY, self::DIGEST_OFF];

    protected $table = 'knx_notification_preferences';

    protected $fillable = ['organization_id', 'employee_id', 'email_immediate', 'email_digest', 'muted_kinds'];

    protected function casts(): array
    {
        return [
            'email_immediate' => 'boolean',
            'muted_kinds' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'employee_id');
    }

    /**
     * The row for a person, created with the defaults the first time it is asked for.
     */
    public static function forEmployee(KnxEmployee $employee): self
    {
        return self::firstOrCreate(
            ['employee_id' => $employee->getKey()],
            [
                'email_immediate' => true,
                'email_digest' => self::DIGEST_PER_VISIT,
                'muted_kinds' => [],
            ],
        );
    }
}
