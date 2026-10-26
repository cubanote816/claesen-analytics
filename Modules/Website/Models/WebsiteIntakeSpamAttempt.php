<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Site;

/**
 * F4/CLA-475 — append-only evidence log for Modules\Website\Services\
 * IntakeSpamGuard. See the migration's docblock for what does/doesn't get
 * recorded here.
 */
class WebsiteIntakeSpamAttempt extends Model
{
    public const UPDATED_AT = null;

    public const REASON_HONEYPOT = 'honeypot';

    public const REASON_TURNSTILE_FAILED = 'turnstile_failed';

    protected $table = 'website_intake_spam_attempts';

    protected $fillable = [
        'site_id',
        'ip',
        'reason',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
