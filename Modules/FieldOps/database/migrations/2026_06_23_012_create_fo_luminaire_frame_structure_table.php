<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_luminaire_frame_structure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('luminaire_frame_id')->constrained('fo_luminaire_frames')->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('fo_structures')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_luminaire_frame_structure');
    }
};
