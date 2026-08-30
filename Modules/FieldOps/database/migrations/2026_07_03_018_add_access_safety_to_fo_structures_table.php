<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Replaces the external_safety_id/external_access_id placeholders (unused,
// no FK, no production data — Access/Safety were out of scope for Slice C)
// with real catalog-backed columns. Access/Safety are 1:1 with their
// Structure (never reused across structures), so they are denormalized as
// plain columns instead of separate instance tables — same precedent as
// LuminaireGroup collapsing into luminaire_subgroups.group_name.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fo_structures', function (Blueprint $table) {
            $table->dropColumn(['external_safety_id', 'external_access_id']);
        });

        Schema::table('fo_structures', function (Blueprint $table) {
            $table->foreignId('access_type_id')->nullable()->after('height')
                ->constrained('fo_access_types')->restrictOnDelete();
            $table->boolean('access_active')->default(false)->after('access_type_id');
            $table->foreignId('safety_type_id')->nullable()->after('access_active')
                ->constrained('fo_safety_types')->restrictOnDelete();
            $table->boolean('safety_certified')->default(false)->after('safety_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('fo_structures', function (Blueprint $table) {
            $table->dropForeign(['access_type_id']);
            $table->dropForeign(['safety_type_id']);
            $table->dropColumn(['access_type_id', 'access_active', 'safety_type_id', 'safety_certified']);
        });

        Schema::table('fo_structures', function (Blueprint $table) {
            $table->unsignedBigInteger('external_safety_id')->nullable();
            $table->unsignedBigInteger('external_access_id')->nullable();
        });
    }
};
