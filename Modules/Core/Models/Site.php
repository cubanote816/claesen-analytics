<?php

namespace Modules\Core\Models;

use Database\Factories\SiteFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The public identity of one organization's website inside the shared
 * backoffice (docs/ai/adr-multi-organization.md, decision D3).
 *
 * `site_id` is the single source of truth on every site-owned row —
 * `organization_id` is never duplicated as a second foreign key on the
 * owning table. The organization is always derived through this model's
 * `organization()` relation.
 *
 * Not consulted by any authorization or scoping logic yet: as of phase P1
 * this model is pure structure. Enforcement lands in phase P5.
 */
class Site extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const CLAESEN_KEY = 'claesen-verlichting';

    protected $fillable = [
        'organization_id',
        'key',
        'domain',
        'default_locale',
        'locales',
        'status',
        // F3/CLA-472: per-site override of config/static_site.php's global
        // webhook settings. Null on every existing site (Claesen included) —
        // Modules\Website\Services\StaticSitePublicationService falls back
        // to the global config when these are unset.
        'static_site_webhook_url',
        'static_site_webhook_secret',
        'static_site_webhook_timeout',
        'static_site_health_url',
        'static_site_debounce_seconds',
        // F4/CLA-473: per-site override of the global mail sender identity
        // and the internal consultation-notification recipient. Null on
        // every existing site (Claesen included) — see mailFromAddress()/
        // mailFromName()/notificationEmail() below for the config fallback.
        'mail_from_address',
        'mail_from_name',
        'mail_notification_email',
    ];

    protected $hidden = [
        'static_site_webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'locales' => 'array',
        ];
    }

    protected static function newFactory(): SiteFactory
    {
        return SiteFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The id of the bootstrap Claesen site row (phase P1 seed migration).
     * Every Website creation path defaults here until the public API and the
     * panel become site-aware — see ADR phase P3a / F3-F4.
     */
    public static function claesenId(): int
    {
        return static::query()->where('key', self::CLAESEN_KEY)->value('id')
            ?? throw new \RuntimeException('The Claesen bootstrap site row is missing — run migrations.');
    }

    /**
     * CLA-598: the site a Filament panel manages, from config('organizations.panel_sites').
     * Null when the panel has no mapping or its site row does not exist yet
     * (Bertels' row is only created at P7) — callers must treat that as "no
     * content to show", never fall back to Claesen. Without an explicit panel it
     * uses the current one, or the default panel (admin) as Filament itself does.
     */
    public static function forPanel(?string $panelId = null): ?self
    {
        $panelId ??= Filament::getCurrentOrDefaultPanel()?->getId();
        $key = $panelId === null ? null : config("organizations.panel_sites.{$panelId}");

        return $key === null ? null : static::query()->where('key', $key)->first();
    }

    /**
     * @see forPanel() — 404 when the panel's site does not exist (never Claesen).
     */
    public static function forPanelOrFail(?string $panelId = null): self
    {
        return static::forPanel($panelId) ?? abort(404);
    }

    /**
     * F4/CLA-473: the address a transactional e-mail for this site is sent
     * from — this site's own override, or the app-wide default. Every
     * existing site (Claesen included) has a null override today, so this
     * resolves to exactly what NewConsultationRequestMail already sent
     * before this ticket.
     */
    public function mailFromAddress(): ?string
    {
        return $this->mail_from_address ?? config('mail.from.address');
    }

    /**
     * @see mailFromAddress()
     */
    public function mailFromName(): ?string
    {
        return $this->mail_from_name ?? config('mail.from.name');
    }

    /**
     * F4/CLA-473: who receives the internal "new consultation request"
     * notice for this site — this site's own override, or the single
     * global recipient CLA-532 introduced. Null (no override, no global
     * config) means the internal notice is skipped entirely — the same
     * "persist the lead, skip the notification" behaviour CLA-532
     * established, never a hard failure.
     */
    public function notificationEmail(): ?string
    {
        return $this->mail_notification_email ?? config('website.consultation_notification_email');
    }

    /**
     * CLA-603: the brand identity the auth screens use for this site's panel —
     * the company logo plus the accent hue of the login's primary colour.
     * Null when the site has no mapped brand, so callers fall back to the
     * mockup's own neutral/blue defaults rather than to another company's
     * identity (same rule as forPanel()).
     *
     * @return array{accent_hue: float|int, logo: string, logo_alt: string, logo_height: int}|null
     */
    public function loginBrand(): ?array
    {
        return config("organizations.login_brand.{$this->key}");
    }
}
