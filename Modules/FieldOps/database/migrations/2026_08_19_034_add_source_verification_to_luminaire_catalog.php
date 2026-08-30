<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CLA-389 — a LuminaireType/LuminaireSubgroup can now be created from an
// unverified AI suggestion (ClaudeVisionService, out-of-catalog candidate)
// instead of only by hand. `source` distinguishes the two; `verified_by_user_id`
// lets a super_admin mark an AI-created entry as reviewed from Filament.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fo_luminaire_subgroups', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('brand');
            $table->foreignId('verified_by_user_id')->nullable()->after('source')->constrained('users')->nullOnDelete();
        });

        Schema::table('fo_luminaire_types', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('image');
            $table->foreignId('verified_by_user_id')->nullable()->after('source')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fo_luminaire_subgroups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn('source');
        });

        Schema::table('fo_luminaire_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn('source');
        });
    }
};
