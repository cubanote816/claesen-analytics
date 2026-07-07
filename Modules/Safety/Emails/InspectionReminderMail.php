<?php

declare(strict_types=1);

namespace Modules\Safety\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\User;

class InspectionReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly ?int $daysSinceLastInspection,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hostmaster@claesen-verlichting.be', 'Claesen Outdoor Lighting Platform'),
            subject: 'Herinnering: Veiligheidsinspectie vereist',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'safety::emails.inspection-reminder',
        );
    }
}
