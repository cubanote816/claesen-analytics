<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('tracks_hours')->default(true)->after('uren_per_week');
        });

        // Bert Kenis (zaakvoerder), Bert Bertels (admin), Technieker (placeholder)
        // do not register billable hours — exclude from rankings and hours views.
        // Not synced by EmployeeSyncService, so this setting persists across employee syncs.
        DB::table('employees')
            ->whereIn('id', ['109', '130', '150'])
            ->update(['tracks_hours' => false]);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('tracks_hours');
        });
    }
};
