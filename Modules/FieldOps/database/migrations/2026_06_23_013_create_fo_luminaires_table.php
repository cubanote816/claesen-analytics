<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_luminaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('luminaire_type_id')->constrained('fo_luminaire_types')->restrictOnDelete();
            $table->foreignId('luminaire_subgroup_id')->constrained('fo_luminaire_subgroups')->restrictOnDelete();
            $table->foreignId('luminaire_frame_id')->constrained('fo_luminaire_frames')->cascadeOnDelete();
            $table->integer('frame_position');
            $table->string('serial_number', 50)->unique();
            $table->decimal('frame_x', 8, 4)->default(0);
            $table->decimal('frame_y', 8, 4)->default(0);
            $table->json('info')->nullable(); // translatable
            $table->integer('cafca_material_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_luminaires');
    }
};
