<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4/CLA-475 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Append-only evidence log for Modules\Website\Services\IntakeSpamGuard —
 * a row is written only when a public intake submission is actually
 * rejected/dropped for a spam-related reason (honeypot field filled,
 * Turnstile verification failed), never for a merely rate-limited request
 * (that carries no evidence of intent, just volume already visible to the
 * rate limiter itself). Modules\Website\Console\Commands\
 * CheckIntakeAbuseAlertsCommand reads this to detect a spike per site.
 *
 * site_id nullable + restrictOnDelete, same D7 pattern as every other
 * site-scoped table this program has added — a spam attempt is always
 * resolved against a real site by ResolveRequestSite before this is ever
 * written, but the column stays nullable for consistency with the rest of
 * the program's site-scoped tables and to never block a Site delete on
 * unrelated historical log rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_intake_spam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('reason', 50);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_intake_spam_attempts');
    }
};
