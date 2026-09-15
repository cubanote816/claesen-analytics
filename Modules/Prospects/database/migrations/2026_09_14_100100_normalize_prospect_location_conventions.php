<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // (a) Single-address federations currently write 'venue_name' for what
        // is actually the club's headquarters; align with the RBFA/LBFA convention.
        DB::statement("
            UPDATE prospects_locations pl
            JOIN prospects_prospects pp ON pp.id = pl.prospect_id
            SET pl.contact_type = 'headquarters'
            WHERE pl.contact_type = 'venue_name'
              AND pp.federation IN ('VL-TPV', 'VL-VHL', 'FR-LFH', 'ARBH-KBHB', 'FR-AFT')
        ");

        // (b) Finding 3's data half: legacy ARBH rows emitted without the
        // region prefix. The code half (new-row emission) already lands in D1/B.
        DB::statement("
            UPDATE prospects_prospects
            SET external_id = CONCAT('ARBH-', external_id)
            WHERE federation = 'ARBH-KBHB' AND external_id LIKE 'HOCKEY-%'
        ");

        // (c) Finding 14's pre-existing duplicates: VAL rows keyed on
        // (prospect_id, address) before the external_id key existed.
        DB::statement("
            DELETE pl FROM prospects_locations pl
            JOIN (
                SELECT prospect_id, address, MIN(id) AS keep_id
                FROM prospects_locations
                WHERE address IS NOT NULL
                GROUP BY prospect_id, address
                HAVING COUNT(*) > 1
            ) dupes ON dupes.prospect_id = pl.prospect_id AND dupes.address = pl.address
            WHERE pl.id <> dupes.keep_id
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE prospects_locations pl
            JOIN prospects_prospects pp ON pp.id = pl.prospect_id
            SET pl.contact_type = 'venue_name'
            WHERE pl.contact_type = 'headquarters'
              AND pp.federation IN ('VL-TPV', 'VL-VHL', 'FR-LFH', 'ARBH-KBHB', 'FR-AFT')
        ");

        DB::statement("
            UPDATE prospects_prospects
            SET external_id = SUBSTRING(external_id, 6)
            WHERE federation = 'ARBH-KBHB' AND external_id LIKE 'ARBH-HOCKEY-%'
        ");
    }
};
