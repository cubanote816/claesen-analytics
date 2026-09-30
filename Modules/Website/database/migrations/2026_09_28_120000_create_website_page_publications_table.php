<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which locales of which **static page** are approved for publication.
 *
 * The copy of the eight pages lives in Git (decision D-F), so those pages have
 * no model, no row and no place to record an approval — and without it the build
 * can only guess, which is how the site ended up deciding with a constant in
 * TypeScript. One row per (site, page, locale).
 *
 * **Not** `PublicationState` (`website_publication_states`): that one is the
 * *deploy* state — idle/dispatched/pending/accepted plus the webhook dispatch —
 * and has nothing to do with an editorial approval. The similar names are the
 * reason this table says `page_publications`.
 *
 * Granularity is deliberate: **per page, not per content key**. The site's plan
 * already settled it (D-C: review per page, so a section can move forward) — and
 * asking the client to approve 274 keys is not a workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_page_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            // The page identifier the frontend's route manifest uses
            // ('home', 'contact', 'legal-privacy', …). It is a key, not a FK:
            // the page exists in the Astro repository, not in this database.
            $table->string('page', 60);
            $table->string('locale', 5);
            // machine | reviewed | published — no row at all means `missing`.
            $table->string('status', 20);
            // Who approved it and when: an approval without an author is an
            // assertion, and this is the record the client's decision lives on.
            // Who approved it, recorded automatically from the acting client —
            // the client who moves the state IS the responsible party, so this is
            // never assigned by hand. Null only for the hand-seeded initial state,
            // where nobody approved anything.
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'page', 'locale'], 'website_page_publications_unique');
            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_page_publications');
    }
};
