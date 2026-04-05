<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Athletics (Flanders)
        DB::table('prospects_prospects')
            ->where('federation', 'VAL')
            ->update([
                'federation' => 'VL-VAL',
                'external_id' => DB::raw("CONCAT('VL-', external_id)")
            ]);

        // 2. Tennis (Flanders)
        DB::table('prospects_prospects')
            ->where('federation', 'TPV')
            ->update([
                'federation' => 'VL-TPV',
                'external_id' => DB::raw("REPLACE(external_id, 'TPV-', 'VL-TPV-')")
            ]);

        // 3. Hockey (Flanders)
        DB::table('prospects_prospects')
            ->where('federation', 'VHL')
            ->update([
                'federation' => 'VL-VHL',
                'external_id' => DB::raw("REPLACE(external_id, 'HOCKEY-', 'VL-HOCKEY-')")
            ]);

        // 4. Hockey (Wallonia)
        DB::table('prospects_prospects')
            ->where('federation', 'LFH')
            ->update([
                'federation' => 'FR-LFH',
                'external_id' => DB::raw("REPLACE(external_id, 'HOCKEY-', 'FR-HOCKEY-')")
            ]);

        // 5. Athletics (Wallonia)
        DB::table('prospects_prospects')
            ->where('federation', 'LBFA')
            ->update([
                'federation' => 'FR-LBFA',
                'external_id' => DB::raw("REPLACE(external_id, 'LBFA-', 'FR-LBFA-')")
            ]);

        // 6. Tennis (Wallonia)
        DB::table('prospects_prospects')
            ->where('federation', 'AFT')
            ->update([
                'federation' => 'FR-AFT',
                'external_id' => DB::raw("REPLACE(external_id, 'AFT-', 'FR-AFT-')")
            ]);

        // 7. Football (RBFA) - Partition by region
        $flandersRegions = DB::table('prospects_regions')
            ->whereIn('name', ['Antwerpen', 'Limburg', 'Oost-Vlaanderen', 'West-Vlaanderen', 'Vlaams-Brabant'])
            ->pluck('id');

        DB::table('prospects_prospects')
            ->where('federation', 'RBFA')
            ->whereIn('region_id', $flandersRegions)
            ->update([
                'federation' => 'VL-VV',
                'language' => 'nl',
                'external_id' => DB::raw("REPLACE(external_id, 'RBFA-', 'VL-RBFA-')")
            ]);

        DB::table('prospects_prospects')
            ->where('federation', 'RBFA')
            ->whereNotIn('region_id', $flandersRegions)
            ->update([
                'federation' => 'FR-ACFF',
                'language' => 'fr',
                'external_id' => DB::raw("REPLACE(external_id, 'RBFA-', 'FR-RBFA-')")
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Athletics
        DB::table('prospects_prospects')
            ->where('federation', 'VL-VAL')
            ->update([
                'federation' => 'VAL',
                'external_id' => DB::raw("REPLACE(external_id, 'VL-', '')")
            ]);

        // TPV
        DB::table('prospects_prospects')
            ->where('federation', 'VL-TPV')
            ->update([
                'federation' => 'TPV',
                'external_id' => DB::raw("REPLACE(external_id, 'VL-TPV-', 'TPV-')")
            ]);

        // Hockey
        DB::table('prospects_prospects')
            ->where('federation', 'VL-VHL')
            ->update([
                'federation' => 'VHL',
                'external_id' => DB::raw("REPLACE(external_id, 'VL-HOCKEY-', 'HOCKEY-')")
            ]);

        DB::table('prospects_prospects')
            ->where('federation', 'FR-LFH')
            ->update([
                'federation' => 'LFH',
                'external_id' => DB::raw("REPLACE(external_id, 'FR-HOCKEY-', 'HOCKEY-')")
            ]);

        // Football
        DB::table('prospects_prospects')
            ->whereIn('federation', ['VL-VV', 'FR-ACFF'])
            ->update([
                'federation' => 'RBFA',
                'external_id' => DB::raw("REPLACE(REPLACE(external_id, 'VL-RBFA-', 'RBFA-'), 'FR-RBFA-', 'RBFA-')")
            ]);
        
        // Reverse other prefixes if needed
    }
};
