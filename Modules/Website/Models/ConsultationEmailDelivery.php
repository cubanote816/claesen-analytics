<?php

declare(strict_types=1);

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Site;
use Modules\Website\Database\Factories\ConsultationEmailDeliveryFactory;

/**
 * F4/CLA-473 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * One row per e-mail a consultation request can trigger — see the
 * create-table migration's docblock for why this exists. Deliberately not
 * scoped through Modules\Core\Models\Concerns\BelongsToSite: this is an
 * operational log always queried by consultation_request_id (never listed
 * cross-site), the same reasoning activity_log already applies (D3) rather
 * than the shared-domain-table reasoning BelongsToSite exists for.
 */
class ConsultationEmailDelivery extends Model
{
    use HasFactory;

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_CONFIRMATION = 'confirmation';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'website_email_deliveries';

    protected $fillable = [
        'site_id',
        'consultation_request_id',
        'type',
        'recipient',
        'status',
        'attempts',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ConsultationEmailDeliveryFactory
    {
        return ConsultationEmailDeliveryFactory::new();
    }

    public function consultationRequest(): BelongsTo
    {
        return $this->belongsTo(ConsultationRequest::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'last_error' => null,
            'sent_at' => now(),
        ])->save();
    }

    /**
     * @param  string  $sanitizedError  A short, PII-free description — never
     *                                  an interpolated exception message
     *                                  that could carry the recipient's
     *                                  address/name a second time (see
     *                                  Modules\Website\Jobs\SendConsultationEmailJob).
     */
    public function markFailed(string $sanitizedError): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'last_error' => $sanitizedError,
        ])->save();
    }
}
