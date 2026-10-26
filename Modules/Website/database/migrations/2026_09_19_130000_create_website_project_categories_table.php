<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3/CLA-468 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Structure only, no data (that is the next migration, kept separate per
 * D7). Replaces Modules\Website\App\Enums\ProjectCategory — a hardcoded
 * PHP enum shared by every site — with a real, site-owned catalog:
 * `website_projects.category` keeps storing a plain slug string (zero
 * schema change to that column, zero risk to existing rows or the public
 * API contract), but which slugs are valid, their display names, and their
 * order are now data, scoped by site_id, instead of a global enum that
 * would otherwise force Bertels' six categories (residencial/empresa/
 * industria/KNX-Qbus/iluminación/renovación) and Claesen's three
 * (sport/industrial/public) into the same fixed list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_project_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->string('slug');
            $table->json('name');
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_project_categories');
    }
};
