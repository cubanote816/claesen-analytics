<?php

namespace Modules\Mailing\Emails;

use Modules\Prospects\Models\Prospect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProspectCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Prospect $prospect,
        public string $dynamicSubject,
        public string $htmlBody,
        public string $unsubscribeUrl
    ) {}

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
