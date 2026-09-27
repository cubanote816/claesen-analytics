<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * CLA-611: one row per (translatable model, attribute, locale) tracking the
 * translation lifecycle: missing → machine → reviewed → published, with
 * stale (source changed, value outdated), needs_review (source changed but
 * a human-approved value is preserved) and failed (provider error —
 * observable, gap G3) as side states.
 *
 * Status is bookkeeping, not truth: the public "approved in locale" rule is
 * DERIVED from these rows (Modules\Intelligence\Services\TranslationStatusService),
 * never hand-declared.
 */
class TranslationState extends Model
{
    public const STATUS_MISSING = 'missing';

    public const STATUS_MACHINE = 'machine';

    public const STATUS_STALE = 'stale';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    /** Worst-to-best: the public rule derives "approved" from the worst key. */
    public const SEVERITY_ORDER = [
        self::STATUS_MISSING,
        self::STATUS_FAILED,
        self::STATUS_STALE,
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_MACHINE,
        self::STATUS_REVIEWED,
        self::STATUS_PUBLISHED,
    ];

    protected $table = 'ai_translation_states';

    protected $fillable = [
        'site_id',
        'translatable_type',
        'translatable_id',
        'attribute',
        'locale',
        'status',
        'source_locale',
        'source_hash',
        'glossary_version',
        'error',
    ];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<string>  $statuses
     */
    public static function worstOf(array $statuses): string
    {
        $best = self::STATUS_PUBLISHED;

        foreach (self::SEVERITY_ORDER as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }

            if ($status === $best) {
                $best = null;
            }
        }

        // No known status at all — treat as missing (never silently approved).
        return self::STATUS_MISSING;
    }
}
