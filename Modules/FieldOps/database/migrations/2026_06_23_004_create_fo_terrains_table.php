<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_terrains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complex_id')->constrained('fo_complexes')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('terrain_type_id')->constrained('fo_terrain_types')->restrictOnDelete();
            $table->json('name')->nullable(); // translatable: {nl, fr, en, es}
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_terrains');
    }
};
