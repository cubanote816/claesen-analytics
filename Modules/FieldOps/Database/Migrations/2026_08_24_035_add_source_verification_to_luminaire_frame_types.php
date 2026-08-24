<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CLA-409 (CLA-390 Fase 3) — a LuminaireFrameType can now be created from an
// AI-generated image (GeminiImageGenerationService, technician-confirmed
// catalog-style rendering of a frame not already in the catalog) instead of
// only by hand or from a raw uploaded photo. Same source/verified_by_user_id
// pattern already used for LuminaireType/LuminaireSubgroup (CLA-389).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fo_luminaire_frame_types', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('image');
            $table->foreignId('verified_by_user_id')->nullable()->after('source')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fo_luminaire_frame_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn('source');
        });
    }
};
