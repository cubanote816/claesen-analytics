<?php

namespace Modules\Knx\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Modules\Knx\Models\KnxNotification;

/**
 * One notice, or a digest of them (KNX-54).
 *
 * Queued with retries so a Graph hiccup does not lose an operational notice; the
 * `emailed_at` flag on the row is stamped after a successful send, so a retry of the
 * JOB is safe and a retry of the FIELD WRITE never reaches here.
 */
class KnxNoticeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  Collection<int, KnxNotification>  $notices
     */
    public function __construct(
        public Collection $notices,
        public bool $immediate = true,
    ) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function envelope(): Envelope
    {
        $subject = $this->immediate
            ? (string) ($this->notices->first()?->title ?? 'KNX')
            : 'Overzicht meldingen · '.$this->notices->count().' nieuw';

        return new Envelope(subject: $subject.' — Claesen Outdoor Lighting Platform');
    }

    public function content(): Content
    {
        return new Content(
            view: 'knx::emails.notice',
            with: [
                'notices' => $this->notices,
                'immediate' => $this->immediate,
            ],
        );
    }
}
