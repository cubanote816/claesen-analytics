<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_complex_electrical_board', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complex_id')->constrained('fo_complexes')->cascadeOnDelete();
            $table->foreignId('electrical_board_id')->constrained('fo_electrical_boards')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_complex_electrical_board');
    }
};
