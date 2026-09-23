<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects_locations', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('contact_type');
        });

        DB::statement("
            UPDATE prospects_locations pl
            JOIN prospects_prospects pp ON pp.id = pl.prospect_id
            SET pl.external_id = CONCAT(pp.external_id, '::hq')
            WHERE pl.contact_type = 'headquarters' AND pp.external_id IS NOT NULL
        ");

        DB::statement("
            UPDATE prospects_locations pl
            JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY prospect_id ORDER BY id) AS rn
                FROM prospects_locations
                WHERE contact_type = 'venue_name'
            ) ranked ON ranked.id = pl.id
            JOIN prospects_prospects pp ON pp.id = pl.prospect_id
            SET pl.external_id = CONCAT(pp.external_id, '::v', ranked.rn)
            WHERE pl.contact_type = 'venue_name' AND pp.external_id IS NOT NULL
        ");

        Schema::table('prospects_locations', function (Blueprint $table) {
            $table->unique(['prospect_id', 'external_id'], 'prospects_locations_prospect_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('prospects_locations', function (Blueprint $table) {
            $table->dropUnique('prospects_locations_prospect_external_unique');
            $table->dropColumn('external_id');
        });
    }
};
