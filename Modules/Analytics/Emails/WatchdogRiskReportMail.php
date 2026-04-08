<?php

namespace Modules\Analytics\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use Illuminate\Mail\Mailables\Address;

class WatchdogRiskReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public array|string $report;

    public function __construct(array|string $report)
    {
        $this->report = $report;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hostmaster@claesen-verlichting.be', 'Claesen Intelligence Hub'),
            subject: '⚠️ Monday Morning Risk Report - Claesen Verlichting',
            replyTo: [
                new Address('no-reply@claesen-verlichting.be', 'No-Reply'),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'analytics::emails.watchdog-report',
        );
    }
}
