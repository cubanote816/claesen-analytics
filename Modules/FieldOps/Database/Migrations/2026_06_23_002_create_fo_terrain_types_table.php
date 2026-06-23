<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_terrain_types', function (Blueprint $table) {
            $table->id();
            $table->json('type'); // translatable: {nl, fr, en, es}
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_terrain_types');
    }
};
