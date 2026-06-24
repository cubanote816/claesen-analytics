<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Database\Factories\InspectionFactory;
use Modules\Safety\Emails\InspectionReportMail;
use Modules\Safety\Jobs\SendInspectionReportMailJob;
use Modules\Safety\Models\Inspection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionReportMailTest extends TestCase
{
    use RefreshDatabase;

    private const RECIPIENT = 'bert.bertels@claesen-verlichting.be';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'project_manager', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        Storage::fake(config('safety.disk'));
        Mail::fake();

        config(['safety.report_recipients' => [self::RECIPIENT]]);
    }

    private function inspectionWithPdf(string $type = 'inspection'): Inspection
    {
        $inspection = InspectionFactory::new()->create([
            'type'     => $type,
            'pdf_path' => "safety-inspections/1/werkplekinspectie_P-TEST_20260624_120000.pdf",
        ]);

        Storage::disk(config('safety.disk'))->put($inspection->pdf_path, '%PDF fake content');

        return $inspection;
    }

    // ── 1. Email sent for inspection type ────────────────────────────────────

    public function test_email_sent_for_inspection_type(): void
    {
        $inspection = $this->inspectionWithPdf('inspection');

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        Mail::assertSent(InspectionReportMail::class, 1);
    }

    // ── 2. Email sent for incident type ──────────────────────────────────────

    public function test_email_sent_for_incident_type(): void
    {
        $inspection = $this->inspectionWithPdf('incident');

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        Mail::assertSent(InspectionReportMail::class, 1);
    }

    // ── 3. Subject correct for inspection ────────────────────────────────────

    public function test_subject_correct_for_inspection(): void
    {
        $inspection = $this->inspectionWithPdf('inspection');

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        Mail::assertSent(InspectionReportMail::class, function (InspectionReportMail $mail) use ($inspection) {
            return str_contains($mail->reportSubject, 'Werkplekinspectie')
                && str_contains($mail->reportSubject, $inspection->project_id);
        });
    }

    // ── 4. Subject correct for incident ──────────────────────────────────────

    public function test_subject_correct_for_incident(): void
    {
        $inspection = $this->inspectionWithPdf('incident');

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        Mail::assertSent(InspectionReportMail::class, function (InspectionReportMail $mail) use ($inspection) {
            return str_contains($mail->reportSubject, 'incidentenrapport')
                && str_contains($mail->reportSubject, $inspection->project_id);
        });
    }

    // ── 5. From address is hostmaster@ ───────────────────────────────────────

    public function test_from_address_is_hostmaster(): void
    {
        $inspection = $this->inspectionWithPdf();

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        Mail::assertSent(InspectionReportMail::class, function (InspectionReportMail $mail) {
            $envelope = $mail->envelope();
            return $envelope->from?->address === 'hostmaster@claesen-verlichting.be';
        });
    }

    // ── 6. No recipients → 0 emails + Log::warning ───────────────────────────

    public function test_no_recipients_logs_warning_and_sends_no_email(): void
    {
        config(['safety.report_recipients' => []]);

        $inspection = $this->inspectionWithPdf();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'SAFETY_REPORT_RECIPIENTS'));

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        Mail::assertNothingSent();
    }

    // ── 7. pdf_path null → 0 emails + Log::warning ───────────────────────────

    public function test_null_pdf_path_logs_warning_and_sends_no_email(): void
    {
        $inspection = InspectionFactory::new()->create(['pdf_path' => null]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'pdf_path is null'));

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        Mail::assertNothingSent();
    }

    // ── 8. Multiple recipients → individual email per recipient ──────────────

    public function test_multiple_recipients_get_individual_emails(): void
    {
        config(['safety.report_recipients' => [
            'bert.bertels@claesen-verlichting.be',
            'other.manager@claesen-verlichting.be',
        ]]);

        $inspection = $this->inspectionWithPdf();

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        Mail::assertSent(InspectionReportMail::class, 2);
    }

    // ── 9. Idempotency: already emailed → 0 emails sent ──────────────────────

    public function test_already_emailed_inspection_is_skipped(): void
    {
        $inspection = $this->inspectionWithPdf();
        $inspection->update(['report_emailed_at' => now()]);

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        Mail::assertNothingSent();
    }

    // ── 10. report_emailed_at persisted after successful send ─────────────────

    public function test_report_emailed_at_is_persisted_after_send(): void
    {
        $inspection = $this->inspectionWithPdf();

        $this->assertNull($inspection->report_emailed_at);

        SendInspectionReportMailJob::dispatchSync($inspection->id);

        $this->assertNotNull($inspection->fresh()->report_emailed_at);
    }
}
