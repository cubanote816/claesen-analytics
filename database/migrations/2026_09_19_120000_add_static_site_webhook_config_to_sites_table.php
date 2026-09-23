<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3/CLA-472 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Per-site override of config/static_site.php's global webhook settings.
 * All nullable, no backfill: Claesen's site row stays null on every column,
 * so Modules\Website\Services\StaticSitePublicationService::sendWebhook()
 * falls back to the existing global config('static_site.*') for it — zero
 * behaviour change. A future Bertels site row can carry its own endpoint
 * without needing a second global config block or an env var per site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->string('static_site_webhook_url')->nullable()->after('domain');
            $table->text('static_site_webhook_secret')->nullable()->after('static_site_webhook_url');
            $table->unsignedSmallInteger('static_site_webhook_timeout')->nullable()->after('static_site_webhook_secret');
            $table->string('static_site_health_url')->nullable()->after('static_site_webhook_timeout');
            $table->unsignedSmallInteger('static_site_debounce_seconds')->nullable()->after('static_site_health_url');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
            $table->dropColumn([
                'static_site_webhook_url',
                'static_site_webhook_secret',
                'static_site_webhook_timeout',
                'static_site_health_url',
                'static_site_debounce_seconds',
            ]);
        });
    }
};
