<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_electrical_board_structure', function (Blueprint $table) {
            $table->id();
            $table->foreignId('electrical_board_id')->constrained('fo_electrical_boards')->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained('fo_structures')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_electrical_board_structure');
    }
};
