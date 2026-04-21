<?php

namespace Modules\Performance\Emails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;
use Modules\Performance\Models\ProjectInsight;

class ImmediateRiskAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public ProjectInsight $insight;
    public float $wipAmount;
    public array $projectData;

    public function __construct(ProjectInsight $insight, float $wipAmount)
    {
        $this->insight = $insight;
        $this->wipAmount = $wipAmount;

        $this->projectData = [
            'id' => $insight->project_id,
            'name' => $insight->full_dna['name'] ?? 'Unknown',
            'wip' => $wipAmount,
            'stale_days' => 'Live ETL',
            'risk_level' => $wipAmount > 20000 ? 'CRITICAL' : 'HIGH',
        ];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('hostmaster@claesen-verlichting.be', 'Claesen Intelligence Hub'),
            subject: '🚨 URGENT: High-Risk Financial Leak Detected - Project ' . $this->insight->project_id,
            replyTo: [
                new Address('no-reply@claesen-verlichting.be', 'No-Reply'),
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
