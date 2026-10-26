<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4/CLA-473 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Per-site override of the global config('mail.from.*')/
 * config('website.consultation_notification_email') values — same nullable-
 * override pattern already used for the static-site webhook config
 * (2026_09_19_120000_add_static_site_webhook_config_to_sites_table.php).
 * All null on every existing site (Claesen included), so
 * Modules\Core\Models\Site::mailFromAddress()/mailFromName()/
 * notificationEmail() fall back to the existing global config — zero
 * behaviour change. A future Bertels site row can carry its own sender
 * identity and internal recipient without a second global config block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->string('mail_from_address')->nullable()->after('static_site_debounce_seconds');
            $table->string('mail_from_name')->nullable()->after('mail_from_address');
            $table->string('mail_notification_email')->nullable()->after('mail_from_name');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn(['mail_from_address', 'mail_from_name', 'mail_notification_email']);
        });
    }
};
