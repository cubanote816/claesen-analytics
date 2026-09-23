<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F0/P1 of the Claesen/Electro Bertels multi-organization program —
 * see docs/ai/adr-multi-organization.md, decisions D1-D4, D7.
 *
 * Structure only, no data (that is the next migration, kept separate per
 * D7). Nothing in the application reads these tables yet:
 * config('organizations.enforce') stays false until phase P5, so this
 * migration is inert on any existing deploy — zero behavior change for
 * Claesen.
 *
 * `sites.organization_id` is the single source of truth linking a site to
 * its organization (D3). Site-owned rows added in later phases (P3) will
 * store only `site_id`, never a duplicate `organization_id` column next
 * to it — the organization is always derived through this relation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->json('retention_policy')->nullable();
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('key', 64)->unique();
            $table->string('domain')->nullable();
            $table->string('default_locale', 5)->default('nl');
            $table->json('locales');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    /**
     * Deliberately refuses to run if any non-Claesen organization exists.
     * This program never discards real data in a migration down() (ADR
     * "Rollback Plan") — dropping these tables while Electro Bertels (or
     * any other organization) has a row would be exactly that.
     */
    public function down(): void
    {
        if (Schema::hasTable('organizations')) {
            $foreignOrganizationExists = DB::table('organizations')->where('slug', '!=', 'claesen')->exists();

            if ($foreignOrganizationExists) {
                throw new \RuntimeException(
                    'Refusing to drop organizations/sites: a non-Claesen organization exists. '
                    .'This migration never discards real organization data in down().'
                );
            }
        }

        Schema::dropIfExists('sites');
        Schema::dropIfExists('organizations');
    }
};
