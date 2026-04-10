<?php

namespace Modules\Performance\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

use Illuminate\Mail\Mailables\Headers;

class VanguardImmediateAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $projectData;

    public function __construct(array $projectData)
    {
        $this->projectData = $projectData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('vanguard@claesen-verlichting.be', 'Vanguard Guardian'),
            subject: '🚨 DRINGEND: Kritiek Financieel Risico - Project ' . $this->projectData['id'],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Priority' => '1 (Highest)',
                'X-MSMail-Priority' => 'High',
                'Importance' => 'High',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'performance::emails.immediate-alert',
        );
    }
}
