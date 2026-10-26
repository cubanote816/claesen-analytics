<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A short-lived public notice per site ("cerrado por vacaciones", "nuevo
 * horario"), distinct from SiteSetting: this is a list of dated items with
 * a publish/archive lifecycle, not a single whitelisted key/value catalog.
 * `message` is translatable JSON (Spatie\Translatable\HasTranslations).
 * `starts_at`/`ends_at` bound when the API considers it active; `status`
 * is the separate editorial gate ("Estados publicado/archivado" in the
 * ticket) — a published announcement outside its date window still never
 * appears in the public API (see Announcement::scopeActive()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->json('message');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_announcements');
    }
};
