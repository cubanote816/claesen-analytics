<?php

namespace Modules\Website\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\Site;
use Modules\Core\Support\MailBrand;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\SiteSetting;

/**
 * F4/CLA-473 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * The client-facing counterpart to NewConsultationRequestMail (the
 * internal notice) — before this ticket, no confirmation of any kind was
 * ever sent to the person who submitted the form (confirmed by grep: no
 * other Mailable in Modules/Website addresses ConsultationRequest::$email).
 * Deliberately its own Mailable/template/delivery row rather than a second
 * recipient on the internal one — the two have entirely different
 * audiences and content (this one carries no internal fields: no message
 * body, no assigned_to, no internal_notes).
 *
 * CLA-705: rendered in the visitor's language (the request `locale`, else the
 * site default, else English), with a reference number in subject and body,
 * an HTML and a plain-text part, and the site phone as the urgent channel.
 */
class ConsultationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Locales the acknowledgement is written in (`Modules/Website/lang`). */
    public const LOCALES = ['nl', 'fr', 'en', 'de'];

    public function __construct(
        public readonly ConsultationRequest $consultation,
        public readonly Site $site,
    ) {
        // Mailable::send()/render() wrap the whole render in this locale.
        $this->locale(self::resolveLocale($consultation, $site));
    }

    public static function resolveLocale(ConsultationRequest $consultation, Site $site): string
    {
        foreach ([$consultation->locale, $site->default_locale] as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::LOCALES, true)) {
                return $candidate;
            }
        }

        return 'en';
    }

    public function envelope(): Envelope
    {
        // `MailBrand` fija el remitente Y deja puesto el nombre que la plantilla de
        // Laravel imprime en cabecera y pie. Acá y no en el constructor: una Mailable
        // encolada se serializa al despachar y el worker la re-renderiza sin volver a
        // pasar por el constructor. `envelope()` sí corre en el worker, y el framework
        // lo resuelve ANTES que `content()` (ver `prepareMailableForDelivery`), así que
        // el nombre ya está puesto cuando se renderiza el cuerpo.
        $brand = MailBrand::forSite($this->site);

        return new Envelope(
            from: new Address(
                (string) $brand['address'],
                (string) $brand['name'],
            ),
            subject: __('website::consultation.confirmation.subject', [
                'reference' => $this->consultation->publicReference(),
            ], $this->locale),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'website::emails.consultation-confirmation',
            text: 'website::emails.consultation-confirmation-text',
            with: [
                'reference' => $this->consultation->publicReference(),
                'phone' => $this->sitePhone(),
            ],
        );
    }

    /**
     * The site's own phone (site settings `phone_display` / `phone_tel`), or
     * null when the site has none — the line is then left out.
     *
     * @return array{display: string, tel: ?string}|null
     */
    private function sitePhone(): ?array
    {
        // Queued send: no request, so no site context for the global scope.
        $settings = SiteSetting::query()
            ->withoutGlobalScopes()
            ->where('site_id', $this->site->getKey())
            ->whereIn('key', ['phone_display', 'phone_tel'])
            ->pluck('value', 'key');

        $display = $settings->get('phone_display');

        if (! is_string($display) || trim($display) === '') {
            return null;
        }

        $tel = $settings->get('phone_tel');

        return ['display' => $display, 'tel' => is_string($tel) && $tel !== '' ? $tel : null];
    }
}
