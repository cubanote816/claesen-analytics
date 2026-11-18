<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Mail\Events\MessageSending;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * Who an outgoing email should appear to come from, for a given user.
 *
 * The platform serves two companies from ONE application instance, but Laravel's
 * mailer has a single global `from`. Left alone that means every message carries
 * `MAIL_FROM_NAME`, which here resolves to `${APP_NAME}` — so a Bertels employee
 * requesting a password reset, or receiving a sign-in code, got mail from
 * "Laravel" at Claesen's address. Both halves of that are wrong, and the second
 * one is worse than cosmetic: the address belongs to the other company.
 *
 * Neither notification can take the site in its constructor. Filament builds its
 * MFA notification itself (`app($notification, ['code' => …, 'codeExpiryMinutes'
 * => …])`), and both notifications are queued, so there is no request — and no
 * panel — to read the site from by the time the mail is actually sent. The one
 * thing that survives the queue is the notifiable, so the brand is derived from
 * the user's organization.
 *
 * `sites.mail_from_address` / `mail_from_name` are the intended override and are
 * currently NULL for both sites, so today this resolves to the organization's own
 * name plus the global address — which is why only the NAME changes here. The
 * address stays a deployment decision (Bertels' sender identity and its
 * SPF/DKIM/DMARC are recorded as pending in docs/ai/known-risks.md).
 */
final class MailBrand
{
    /**
     * The company for the mail being sent right now, or null.
     *
     * Laravel's markdown template prints `config('app.name')` in the header and the
     * footer, and it is a component: it receives no message, no notifiable and no
     * mailable data — verified by dumping `get_defined_vars()` inside it, which shows
     * only `__path`, `__data`, `app`, `attributes`, `slot`… So there is nowhere to read
     * the company from at render time, and the only channel left is a value that is
     * live while the body renders.
     *
     * It is set by `forUser()`, which the multi-company notifications call from
     * `toMail()` — i.e. before the body is rendered — and cleared after each send by
     * the `MessageSending` listener, so a later send can never inherit it. Clearing
     * AFTER is what makes this safe: the event fires too late to help the current
     * message but early enough to protect the next one.
     */
    private static ?string $forThisSend = null;

    /**
     * Whether the mail being sent right now is Electro Bertels'. Travels with
     * `$forThisSend` for the same reason (the markdown components receive no
     * notifiable) and is cleared with it. Anything that never names a company —
     * including mails that never call `forUser()`/`forSite()` — is Claesen.
     */
    private static bool $bertels = false;

    /**
     * Whether the mail being sent right now is a quote mail of a site (`forSite()`),
     * as opposed to an automatic platform mail. Quote mails are answered by people
     * (offerte@), so they never carry the "do not reply" notice. Cleared with the rest.
     */
    private static bool $siteMail = false;

    /**
     * Visual identity per company (design/system.md, CLA-686). The band is the dark
     * header/footer color, the accent the stripe and button fill, and the button label
     * is dark because white on either accent fails WCAG AA. Logos are light-on-dark
     * rasters, embedded in the message as CID attachments by the mail layout.
     */
    private const THEMES = [
        'claesen' => [
            'theme' => 'claesen',
            'accent' => '#00aeef',
            'band' => '#0b0b0f',
            'button_text' => '#0b0b0f',
            'logo' => 'img/brand-logo-dark.png',
            'logo_cid' => 'claesen-logo@mail.brand',
            'logo_width' => 180,
        ],
        'bertels' => [
            'theme' => 'bertels',
            'accent' => '#EE7203',
            'band' => '#121212',
            'button_text' => '#121212',
            // brand-logo-dark's Bertels sibling, trimmed and scaled to 2x for the band.
            'logo' => 'img/mail/bertels-logo-dark-360.png',
            'logo_cid' => 'bertels-logo@mail.brand',
            'logo_width' => 170,
        ],
    ];

    /**
     * The visual identity of the mail being sent right now.
     *
     * @return array{theme: string, accent: string, band: string, button_text: string, logo: string, logo_cid: string, logo_width: int}
     */
    public static function current(): array
    {
        return self::THEMES[self::$bertels ? 'bertels' : 'claesen'];
    }

    public static function accent(): string
    {
        return self::current()['accent'];
    }

    public static function logoPath(): string
    {
        return public_path(self::current()['logo']);
    }

    /**
     * Attaches, inline, the logo the HTML body references by `cid:`.
     *
     * The markdown components are anonymous: they get no `$message`, so they cannot call
     * `$message->embed()` themselves. Instead the header prints a fixed Content-ID per
     * brand and this listener (on `MessageSending`) adds the matching file. Keying on the
     * Content-ID found in the body — not on the static brand state — keeps it correct
     * whatever order the `forget()` listener runs in, and does nothing for mails that do
     * not use the branded header.
     */
    public static function embedLogo(MessageSending $event): void
    {
        $html = $event->message->getHtmlBody();

        if (! is_string($html)) {
            return;
        }

        foreach (self::THEMES as $theme) {
            if (! str_contains($html, 'cid:'.$theme['logo_cid'])) {
                continue;
            }

            $event->message->addPart(
                (new DataPart(new File(public_path($theme['logo'])), $theme['theme'].'-logo.png', 'image/png'))
                    ->asInline()
                    ->setContentId($theme['logo_cid'])
            );
        }
    }

    /**
     * The name the header and footer of an outgoing mail should carry.
     *
     * Falls back to the configured app name, which is what every single-company
     * sender in the repo keeps using — they send as Claesen and never touch this.
     */
    public static function footerName(): string
    {
        return self::$forThisSend ?? (string) config('app.name');
    }

    /**
     * CLA-699: whether the default mail layout must print the "automated message, do
     * not reply" notice. True for every Claesen automatic mail (anything that does not
     * name a company counts as Claesen); false for Electro Bertels and for the site's
     * quote mails.
     */
    public static function showsAutomatedNotice(): bool
    {
        return ! self::$bertels && ! self::$siteMail;
    }

    /** Called after a message is sent, so nothing leaks into the next one. */
    public static function forget(): void
    {
        self::$forThisSend = null;
        self::$bertels = false;
        self::$siteMail = false;
    }

    /**
     * La marca de un envío que conoce su **sitio** pero no su usuario.
     *
     * Es el caso de las mails del propio sitio (`ConsultationConfirmationMail`,
     * `NewConsultationRequestMail`): son `Mailable`s que ya llevan el `Site` encima.
     * Antes usaban `$site->mailFromName()` a secas, que cae a
     * `config('mail.from.name')` — o sea a `APP_NAME`, «Laravel» — así que el visitante
     * recibía un correo de un remitente llamado Laravel, y el pie decía «© 2026
     * Laravel», en las dos empresas.
     */
    public static function forSite(Site $site): array
    {
        $brand = self::resolveSite($site);
        self::$siteMail = true;

        return $brand;
    }

    /**
     * @return array{address: ?string, name: ?string}
     */
    private static function resolveSite(Site $site): array
    {
        $name = $site->mail_from_name
            ?? $site->organization?->name
            ?? $site->mailFromName()
            ?? config('mail.from.name');

        self::$forThisSend = (string) $name;
        // Only two companies share the instance: whoever is not Claesen is Bertels.
        self::$bertels = $site->organization_id !== Organization::claesenId();

        return [
            'address' => $site->mailFromAddress() ?? config('mail.from.address'),
            'name' => $name,
        ];
    }

    /**
     * @return array{address: ?string, name: ?string}
     */
    public static function forUser(?User $user): array
    {
        $site = self::siteFor($user);

        if ($site === null) {
            // Sin usuario o sin sitio no hay empresa que afirmar: se limpia en vez de
            // dejar la del envío anterior, que sería peor que el nombre genérico.
            self::forget();

            // CLA-699: a no-site user is Claesen only without an organization or inside
            // the Claesen organization (that of the Claesen panel site); a Bertels
            // user without a site gets no Claesen notice.
            if ($user?->organization_id !== null && $user->organization_id !== self::claesenOrganizationId()) {
                self::$bertels = true;
            }

            return [
                'address' => config('mail.from.address'),
                'name' => $user?->organization?->name ?? config('mail.from.name'),
            ];
        }

        $brand = self::resolveSite($site);
        self::$siteMail = false;

        // CLA-699: Claesen's automatic mail leaves from the platform sender
        // (`mail.from.*`, backoffice@); the Claesen site's own `mail_from_address` is the
        // QUOTE sender (offerte@) and is only used by `forSite()`. Electro Bertels keeps
        // its site sender for everything.
        if ($site->key === config('organizations.panel_sites.admin')) {
            $brand['address'] = config('mail.from.address');
            $brand['name'] = config('mail.from.name');
        }

        return $brand;
    }

    private static function claesenOrganizationId(): int
    {
        return (int) (Site::query()->where('key', config('organizations.panel_sites.admin'))->value('organization_id')
            ?? Organization::claesenId());
    }

    private static function siteFor(?User $user): ?Site
    {
        if ($user?->organization_id === null) {
            return null;
        }

        // A site per organization is the current shape (one each). `orderBy('id')`
        // makes the choice deterministic if that ever stops being true, instead of
        // silently depending on row order.
        return Site::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('id')
            ->first();
    }
}
