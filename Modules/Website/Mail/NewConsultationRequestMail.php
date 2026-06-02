<?php

namespace Modules\Website\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Website\Models\ConsultationRequest;

class NewConsultationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ConsultationRequest $consultation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New consultation request — ' . $this->consultation->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'website::emails.new-consultation-request',
        );
    }
}
