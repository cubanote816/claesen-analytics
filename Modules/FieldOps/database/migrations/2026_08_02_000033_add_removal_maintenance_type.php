<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fo_maintenance_types')->updateOrInsert(
            ['code' => 'removal'],
            ['name' => json_encode(['nl' => 'Verwijdering', 'en' => 'Removal', 'fr' => 'Retrait', 'de' => 'Entfernung']), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        $removalTypeId = DB::table('fo_maintenance_types')->where('code', 'removal')->value('id');
        $correctiveTypeId = DB::table('fo_maintenance_types')->where('code', 'corrective')->value('id');

        if ($removalTypeId && $correctiveTypeId) {
            DB::table('fo_maintenance_records')
                ->where('fo_maintenance_type_id', $removalTypeId)
                ->update(['fo_maintenance_type_id' => $correctiveTypeId]);
        }

        DB::table('fo_maintenance_types')->where('code', 'removal')->delete();
    }
};
