<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A whitelisted key/value settings row per site (hours, phone, email,
 * address, social links) — never a free-form page-builder table. `key` is
 * validated against config('website.site_settings.allowed_keys') at the
 * application layer (Modules\Website\Models\SiteSetting), not a DB enum, so
 * adding a new allowed key is a config change, never a migration — same
 * reasoning already established for Modules\Analytics\Enums\EventName.
 *
 * `value` is JSON regardless of `type`: a 'translatable' setting stores
 * {"nl": "...", "en": "..."} (Spatie\Translatable\HasTranslations), a
 * 'text'/'json' setting stores its raw value directly. No settings data
 * exists to migrate — this is new functionality, not a replacement for
 * anything that previously lived elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_site_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->string('type');
            $table->timestamps();

            $table->unique(['site_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_site_settings');
    }
};
