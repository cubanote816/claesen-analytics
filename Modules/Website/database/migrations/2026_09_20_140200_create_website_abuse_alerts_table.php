<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4/CLA-475 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Same idempotency shape as Modules\Mailing\Models\DeliverabilityAlert
 * (UNIQUE + firstOrCreate => no double notification), but a recurring
 * signal needs a recurring key rather than a permanent one: alert_bucket
 * is an hourly bucket string (Carbon's 'Y-m-d-H'), so continued abuse in
 * the site keeps re-alerting once per hour instead of exactly once ever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_abuse_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->string('alert_bucket', 20);
            $table->unsignedInteger('attempt_count');
            $table->unsignedInteger('threshold');
            $table->unsignedInteger('window_minutes');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'alert_bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_abuse_alerts');
    }
};
