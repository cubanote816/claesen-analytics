<?php

namespace Modules\Website\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\Site;
use Modules\Website\Models\ConsultationRequest;

class NewConsultationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * F4/CLA-473: $site is required, not derived from
     * $consultation->site — the ADR flagged that this mailable never set
     * an explicit From before this ticket (fell back to the single global
     * config('mail.from.*') regardless of which site's consultation
     * triggered it), and MicrosoftGraphTransport::getPayload() ignored
     * this Mailable's own From name even when the address was set. Both
     * fixed together — see Modules\Website\Jobs\SendConsultationEmailJob
     * for where $site is resolved.
     */
    public function __construct(
        public readonly ConsultationRequest $consultation,
        public readonly Site $site,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) $this->site->mailFromAddress(),
                (string) $this->site->mailFromName(),
            ),
            subject: 'New consultation request — '.$this->consultation->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'website::emails.new-consultation-request',
        );
    }
}
