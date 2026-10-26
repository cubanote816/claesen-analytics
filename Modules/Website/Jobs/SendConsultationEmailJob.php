<?php

namespace Modules\Website\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\Site;
use Modules\Core\Services\OrganizationContext;
use Modules\Website\Mail\ConsultationConfirmationMail;
use Modules\Website\Mail\NewConsultationRequestMail;
use Modules\Website\Models\ConsultationEmailDelivery;
use Throwable;

/**
 * F4/CLA-473 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Sends exactly one Modules\Website\Models\ConsultationEmailDelivery row's
 * e-mail and records the outcome on that row (queued -> sent|failed).
 *
 * $tries is deliberately 1 — this job never lets an exception escape
 * handle(), by design, not oversight. Illuminate\Queue\SyncQueue::push()
 * (confirmed by reading vendor source: config('queue.default') is 'sync'
 * in phpunit.xml, and DB::afterCommit() callbacks — how this job is always
 * dispatched — run inline, in-process, on whatever connection is active)
 * calls $job->fail($e) and then RE-THROWS on any uncaught exception. A
 * rethrow here would propagate into the same request/response cycle that
 * already returned (or is about to return) the consultation's 201 — the
 * exact outcome CLA-532 exists to prevent for the "internal notice" send,
 * now extended to both e-mails this job can send. On the real, async
 * 'database' queue connection (production, via the persistent
 * queue:work worker), that risk doesn't exist, but the job's behaviour
 * must be identical on both connections. Automatic backoff-based retrying
 * is therefore not used at all; recovery is
 * website:retry-failed-consultation-emails instead (observable,
 * schedulable, and connection-agnostic).
 */
class SendConsultationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = ConsultationEmailDelivery::find($this->deliveryId);

        if (! $delivery || $delivery->status === ConsultationEmailDelivery::STATUS_SENT) {
            return;
        }

        $delivery->increment('attempts');

        $consultation = $delivery->consultationRequest;
        $site = Site::query()->find($delivery->site_id);

        if (! $consultation || ! $site) {
            $delivery->markFailed('Consultation request or site no longer exists.');

            return;
        }

        // F3/CLA-472's own precedent: the first real consumer that needed
        // BelongsToSite-scoped queries to resolve inside a background job.
        // Nothing on this job's own path currently depends on the scope,
        // but the Mailable's view/branding does read $site->organization,
        // and any future site-scoped read added to either template
        // inherits this for free.
        app(OrganizationContext::class)->setSite($site);

        try {
            $mailable = $delivery->type === ConsultationEmailDelivery::TYPE_CONFIRMATION
                ? new ConsultationConfirmationMail($consultation, $site)
                : new NewConsultationRequestMail($consultation, $site);

            Mail::mailer('microsoft-graph')->to($delivery->recipient)->send($mailable);

            $delivery->markSent();
        } catch (Throwable $e) {
            // Never interpolate $e->getMessage() here — a Graph API error
            // response can and does echo the request payload (recipient
            // address, sometimes the subject) back in its error text. Only
            // the exception class is PII-free by construction.
            Log::error('SendConsultationEmailJob: delivery attempt failed.', [
                'delivery_id' => $delivery->id,
                'type' => $delivery->type,
                'exception' => $e::class,
            ]);

            $delivery->markFailed(sprintf('[%s] delivery attempt failed.', $e::class));
        }
    }
}
