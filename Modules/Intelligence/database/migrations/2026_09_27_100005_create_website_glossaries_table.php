<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLA-611 (gap G4): per-site translation glossary. Original term =>
 * approved translation per locale, plus `do_not_translate` for brand names
 * (KNX, QBUS, …) that must cross every locale verbatim. Versioned
 * (glossary_version derived from the rows) so the translation cache
 * invalidates when the glossary changes.
 *
 * ADR D3: site-owned table — only `site_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_glossaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('term', 191);
            $table->json('translations'); // locale => approved translation
            $table->boolean('do_not_translate')->default(false);
            $table->timestamps();

            $table->unique(['site_id', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_glossaries');
    }
};
