<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site address of a project (KNX-51).
 *
 * A project's `city` was the only location the contract carried, and it is not
 * enough to send a technician to a job: the street, the postal code and how to get
 * in are the parts the office actually needs. The address lives in its own four
 * columns because it is a structured block in the contract
 * (`siteAddress: {street, postalCode, city, accessNote?}`), not a free-text line.
 *
 * Nullable on purpose: every project created before this ticket has no address, and
 * the contract says so (`siteAddress` is nullable in the read shape). `city` stays
 * where it was and becomes a derived copy of `site_city`, so existing consumers keep
 * reading a city.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_projects', function (Blueprint $table): void {
            $table->string('site_street')->nullable()->after('name');
            $table->string('site_postal_code', 16)->nullable()->after('site_street');
            $table->string('site_city')->nullable()->after('site_postal_code');
            $table->string('site_access_note', 500)->nullable()->after('site_city');
        });
    }

    public function down(): void
    {
        Schema::table('knx_projects', function (Blueprint $table): void {
            $table->dropColumn(['site_street', 'site_postal_code', 'site_city', 'site_access_note']);
        });
    }
};
