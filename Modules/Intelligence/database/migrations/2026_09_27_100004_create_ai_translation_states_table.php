<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLA-611: per-(attribute, locale) AI translation state — the fix for gap
 * G2 (today `ai_translation_status` is one column per ROW, so "FR approved,
 * DE pending" is unrepresentable).
 *
 * One row per (translatable model, attribute, locale). Generic across every
 * model using Modules\Intelligence\Traits\HasAiTranslations (Website AND
 * FieldOps) — the engine is trait-level, so the table is engine-owned and
 * polymorphic. ADR D6-friendly: jobs keep receiving scalar ids and never
 * resolve shared-domain models through this table.
 *
 * `site_id` is nullable: FieldOps models have no site. It indexes glossary
 * lookup (only site-owned content gets site glossaries) without ever being
 * a second ownership FK on a site-owned table (D3 applies to site-owned
 * domain tables, not to this engine bookkeeping).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_translation_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->string('translatable_type');
            $table->unsignedBigInteger('translatable_id');
            $table->string('attribute');
            $table->string('locale', 5);
            // missing | machine | stale | needs_review | reviewed | published | failed
            $table->string('status', 20);
            $table->string('source_locale', 5)->nullable();
            $table->string('source_hash', 64)->nullable();
            $table->unsignedInteger('glossary_version')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'attribute', 'locale'], 'ai_translation_states_unique');
            $table->index(['status', 'translatable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_translation_states');
    }
};
