<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Corrective migration — changes all created_by_user_id FKs from cascadeOnDelete
// to nullOnDelete. Deleting a Core user must not cascade to operational data
// (complexes, structures, luminaires) or canonical catalogs (structure_types,
// luminaire_frame_types, etc.).
return new class extends Migration
{
    private const TABLES = [
        'fo_complexes',
        'fo_terrains',
        'fo_structure_types',
        'fo_structures',
        'fo_luminaire_frame_types',
        'fo_luminaire_subgroups',
        'fo_luminaire_types',
        'fo_luminaire_frames',
        'fo_luminaires',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(['created_by_user_id']);
                $blueprint->unsignedBigInteger('created_by_user_id')->nullable()->change();
                $blueprint->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(['created_by_user_id']);
                $blueprint->unsignedBigInteger('created_by_user_id')->nullable(false)->change();
                $blueprint->foreign('created_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
