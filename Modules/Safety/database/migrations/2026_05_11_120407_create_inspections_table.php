<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('safety_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id'); // Relación con Modules\Core\Models\User
            $table->foreignId('checklist_id')->constrained('safety_checklists');
            $table->string('project_id')->index(); // Legacy string ID
            $table->timestamp('completed_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_inspections');
    }
};
