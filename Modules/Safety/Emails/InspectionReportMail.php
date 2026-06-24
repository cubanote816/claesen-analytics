<?php

declare(strict_types=1);

namespace Modules\Safety\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Models\User;
use Modules\Safety\Models\Inspection;

class InspectionReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $reportSubject;

    public function __construct(
        public readonly Inspection $inspection,
        public readonly User $inspector,
    ) {
        $projectId = $inspection->project_id;

        $this->reportSubject = $inspection->type === 'incident'
            ? "Nieuw incidentenrapport – {$projectId}"
            : "Nieuw veiligheidsrapport – Werkplekinspectie {$projectId}";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hostmaster@claesen-verlichting.be', 'Claesen Intelligence Hub'),
            subject: $this->reportSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'safety::emails.inspection-report',
            with: [
                'inspection' => $this->inspection,
                'inspector'  => $this->inspector,
                'subject'    => $this->reportSubject,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk(config('safety.disk'), $this->inspection->pdf_path)
                ->as(basename($this->inspection->pdf_path))
                ->withMime('application/pdf'),
        ];
    }
}
