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
        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->after('type')->constrained('prospects_regions')->onDelete('set null');
            $table->string('federation')->nullable()->after('region_id');
            $table->string('language', 2)->nullable()->after('federation');
            $table->string('contact_person')->nullable()->after('language');
        });

        // 1. Backfill existing region labels to region_id
        $regions = DB::table('prospects_regions')->get();
        foreach ($regions as $region) {
            DB::table('prospects_prospects')
                ->where('region', $region->name)
                ->update(['region_id' => $region->id]);
        }

        // 2. Set default federation and language for existing football clubs
        DB::table('prospects_prospects')
            ->where('type', 'football_club')
            ->update([
                'federation' => 'RBFA',
                'language' => 'nl', // Matches existing Dutch-centric approach
            ]);

        // 3. Drop the old region column
        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->string('region')->nullable()->after('type');
        });

        // Restore data
        $records = DB::table('prospects_prospects')
            ->join('prospects_regions', 'prospects_prospects.region_id', '=', 'prospects_regions.id')
            ->select('prospects_prospects.id', 'prospects_regions.name')
            ->get();

        foreach ($records as $record) {
            DB::table('prospects_prospects')
                ->where('id', $record->id)
                ->update(['region' => $record->name]);
        }

        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn(['region_id', 'federation', 'language', 'contact_person']);
        });
    }
};
