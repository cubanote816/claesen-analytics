<?php

namespace Modules\Mailing\Emails;

use Modules\Prospects\Models\Prospect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ProspectCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Prospect $prospect,
        public string $dynamicSubject,
        public string $htmlBody,
        public string $unsubscribeUrl,
        public ?string $trackingToken = null,
        public bool $isCommercial = true,
    ) {}

    /**
     * Get the message headers.
     *
     * List-Unsubscribe (RFC 8058) is included only for commercial emails.
     * Transactional emails must never carry unsubscribe headers.
     * The X-Mailing-Token header is always included when a tracking token is present.
     */
    public function headers(): Headers
    {
        $textHeaders = [];

        if ($this->isCommercial) {
            $oneClickUrl = route('api.mailing.unsubscribe.oneclick', [
                'prospect' => $this->prospect->id,
                'token'    => $this->prospect->getUnsubscribeToken(),
            ]);

            $mailtoFallback = 'mailto:afmelden@' . config('mailing.unsubscribe_domain', 'claesen-verlichting.be') . '?subject=afmelden';

            $textHeaders['List-Unsubscribe']      = "<{$oneClickUrl}>, <{$mailtoFallback}>";
            $textHeaders['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        if ($this->trackingToken !== null) {
            $textHeaders['X-Mailing-Token'] = $this->trackingToken;
        }

        return new Headers(text: $textHeaders);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->dynamicSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mailing::emails.campaign',
            with: [
                'body'            => $this->htmlBody,
                'prospect'        => $this->prospect,
                'subject'         => $this->dynamicSubject,
                'unsubscribe_url' => $this->unsubscribeUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
