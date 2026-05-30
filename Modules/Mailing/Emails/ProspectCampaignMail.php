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
    ) {}

    /**
     * Get the message headers (RFC 8058 one-click unsubscribe + NDR correlation token).
     */
    public function headers(): Headers
    {
        $oneClickUrl = route('mailing.unsubscribe.oneclick', [
            'prospect' => $this->prospect->id,
            'token'    => $this->prospect->getUnsubscribeToken(),
        ]);

        $mailtoFallback = 'mailto:afmelden@' . config('mailing.unsubscribe_domain', 'claesen-verlichting.be') . '?subject=afmelden';

        $textHeaders = [
            'List-Unsubscribe'      => "<{$oneClickUrl}>, <{$mailtoFallback}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];

        if ($this->trackingToken !== null) {
            $textHeaders['X-Mailing-Token'] = $this->trackingToken;
        }

        return new Headers(text: $textHeaders);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->dynamicSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mailing::emails.campaign',
            with: [
                'body' => $this->htmlBody,
                'prospect' => $this->prospect,
                'subject' => $this->dynamicSubject,
                'unsubscribe_url' => $this->unsubscribeUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
