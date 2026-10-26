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
 */
class ConsultationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ConsultationRequest $consultation,
        public readonly Site $site,
    ) {}

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
            subject: 'We received your request',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'website::emails.consultation-confirmation',
        );
    }
}
