<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Modules\Core\Models\Site;
use Modules\Core\Models\User;

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
     * The name the header and footer of an outgoing mail should carry.
     *
     * Falls back to the configured app name, which is what every single-company
     * sender in the repo keeps using — they send as Claesen and never touch this.
     */
    public static function footerName(): string
    {
        return self::$forThisSend ?? (string) config('app.name');
    }

    /** Called after a message is sent, so nothing leaks into the next one. */
    public static function forget(): void
    {
        self::$forThisSend = null;
    }

    /**
     * @return array{address: ?string, name: ?string}
     */
    public static function forUser(?User $user): array
    {
        $site = self::siteFor($user);

        $name = $site?->mail_from_name
            ?? $user?->organization?->name
            ?? $site?->mailFromName()
            ?? config('mail.from.name');

        // Sin usuario no hay empresa que afirmar: se limpia en vez de dejar la del
        // envío anterior, que sería peor que el nombre genérico.
        self::$forThisSend = $user === null ? null : (string) $name;

        return [
            'address' => $site?->mailFromAddress() ?? config('mail.from.address'),
            // The site's own override, then the organization's real name, and only
            // then the global fallback. `Site::mailFromName()` alone is not enough:
            // it falls back to `config('mail.from.name')`, which is the anonymous
            // global value this class exists to avoid.
            'name' => $name,
        ];
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
