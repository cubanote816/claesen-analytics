<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_structure_terrain', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('fo_structures')->cascadeOnDelete();
            $table->foreignId('terrain_id')->constrained('fo_terrains')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_structure_terrain');
    }
};
