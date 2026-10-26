<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4/CLA-473 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Delivery-status log for both e-mails a consultation request can trigger
 * (Modules\Website\Models\ConsultationEmailDelivery — 'internal' notice to
 * the site's own team, 'confirmation' to the client who submitted the
 * form). Exists so "queued/sent/failed" is an observable, queryable fact
 * instead of only a Log::error() line, and so a failed send can be
 * identified and retried (website:retry-failed-consultation-emails)
 * without ever touching the already-persisted consultation row — a mail
 * failure here never implies the lead was lost (CLA-532's own guarantee,
 * carried forward).
 *
 * `recipient`/`last_error` are operational data, not a place to duplicate
 * free-text PII beyond the address itself: last_error only ever stores an
 * exception class name (see Modules\Website\Jobs\SendConsultationEmailJob),
 * never an interpolated message that could carry the recipient's name or
 * address a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignId('consultation_request_id')->constrained('website_consultation_requests')->cascadeOnDelete();
            $table->string('type');
            $table->string('recipient');
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'attempts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_email_deliveries');
    }
};
