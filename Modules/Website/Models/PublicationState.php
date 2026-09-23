<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\BelongsToSite;
use Modules\Core\Models\Site;
use Modules\Website\App\Enums\PublicationStatus;

class PublicationState extends Model
{
    use BelongsToSite;

    protected $table = 'website_publication_states';

    protected $fillable = [
        'site_id',
        'status',
        'dispatch_key',
        'dispatched_at',
        'pending_since',
        'last_accepted_at',
        'last_error',
        'last_error_at',
    ];

    protected $casts = [
        'status'           => PublicationStatus::class,
        'dispatched_at'    => 'datetime',
        'pending_since'    => 'datetime',
        'last_accepted_at' => 'datetime',
        'last_error_at'    => 'datetime',
    ];

    // ─── Singleton-per-site access ──────────────────────────────────────────
    //
    // F1/P3b (docs/ai/adr-multi-organization.md): one row per site (D3),
    // addressed by site_id rather than a hardcoded id = 1. Every caller today
    // omits $siteId, so nothing about the observed behaviour for Claesen
    // changes — there is still, and will remain until a second site exists,
    // exactly one row.
    //
    // F3/CLA-472: withoutGlobalScope('site') is deliberate, not a bypass of
    // isolation — $siteId is already an explicit argument naming exactly the
    // row this call wants, so BelongsToSite's ambient-context scope (which
    // filters by whatever OrganizationContext::siteId() currently resolves
    // to) has nothing left to add. Applying both would AND them together:
    // called for Bertels' site from a request/job whose ambient context
    // resolves to Claesen (or none at all under organizations.enforce)
    // would either silently look at the wrong site or throw
    // MissingOrganizationContext despite the caller supplying an exact,
    // valid site id.

    public static function current(?int $siteId = null): static
    {
        $siteId ??= Site::claesenId();

        return static::withoutGlobalScope('site')->firstOrCreate(
            ['site_id' => $siteId],
            ['status' => PublicationStatus::IDLE]
        );
    }

    // ─── State transitions ────────────────────────────────────────────────────

    public function markPending(): void
    {
        $this->status      = PublicationStatus::PENDING;
        $this->last_error  = null;
        $this->last_error_at = null;

        // Set only on first change of the cycle; subsequent saves keep the
        // original timestamp so the admin can see when content first drifted.
        if (!$this->pending_since) {
            $this->pending_since = now();
        }

        $this->save();
    }

    public function recordDispatch(string $dispatchKey): void
    {
        $this->dispatch_key  = $dispatchKey;
        $this->dispatched_at = now();
        $this->save();
    }

    public function markAccepted(): void
    {
        $this->status           = PublicationStatus::ACCEPTED;
        $this->last_accepted_at = now();
        $this->dispatch_key     = null;
        $this->dispatched_at    = null;
        $this->pending_since    = null;
        $this->last_error       = null;
        $this->last_error_at    = null;
        $this->save();
    }

    public function markError(string $message): void
    {
        $this->status        = PublicationStatus::ERROR;
        $this->last_error    = mb_substr($message, 0, 2000);
        $this->last_error_at = now();
        $this->save();
    }
}
