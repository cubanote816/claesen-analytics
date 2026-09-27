<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLA-481: named media slots per site (home.hero, bedrijven.hero,
 * over-ons.team, …) so the frontend build can fetch site imagery with
 * stable dimensions, per-locale alt/caption and a stable checksum.
 *
 * ADR D3: site-owned table — only `site_id`; the organization derives
 * through sites.organization_id. `media_id` references Spatie's shared
 * `media` table (cascadeOnDelete — a deleted media item removes its slot
 * assignment, never the reverse).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_media_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('slot', 100);
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_media_slots');
    }
};
