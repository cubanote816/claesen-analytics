<?php

declare(strict_types=1);

namespace Modules\Safety\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\User;
use Modules\Safety\Emails\InspectionReportMail;
use Modules\Safety\Models\Inspection;

class SendInspectionReportMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $inspectionId) {}

    public function handle(): void
    {
        $inspection = Inspection::with(['checklist'])->find($this->inspectionId);

        if (! $inspection) {
            return;
        }

        // Idempotency guard — do not re-send if already emailed
        if ($inspection->report_emailed_at !== null) {
            return;
        }

        $recipients = config('safety.report_recipients', []);

        if (empty($recipients)) {
            Log::warning('SendInspectionReportMailJob: SAFETY_REPORT_RECIPIENTS is not configured — report email skipped.', [
                'inspection_id' => $this->inspectionId,
            ]);
            return;
        }

        if ($inspection->pdf_path === null) {
            Log::warning('SendInspectionReportMailJob: pdf_path is null, cannot attach report.', [
                'inspection_id' => $this->inspectionId,
            ]);
            return;
        }

        $inspector = User::find($inspection->user_id);

        if (! $inspector) {
            Log::warning('SendInspectionReportMailJob: inspector user not found.', [
                'inspection_id' => $this->inspectionId,
                'user_id'       => $inspection->user_id,
            ]);
            return;
        }

        $mailable = new InspectionReportMail($inspection, $inspector);

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)->send($mailable);
            } catch (\Exception $e) {
                Log::error('SendInspectionReportMailJob: failed to send to recipient.', [
                    'inspection_id' => $this->inspectionId,
                    'recipient'     => $recipient,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $inspection->update(['report_emailed_at' => now()]);
    }
}
