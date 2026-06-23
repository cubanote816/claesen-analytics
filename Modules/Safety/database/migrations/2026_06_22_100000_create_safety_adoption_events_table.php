<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safety_adoption_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index(); // Can be null for system errors, but holds identity for 90 days for DAU/MAU
            $table->string('event_type')->index(); // e.g., 'inspection_completed', 'photo_upload_failed'
            $table->string('project_id')->nullable()->index(); // Allows grouping by project
            $table->json('metadata')->nullable(); // Additional details (e.g., photo_warning count)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_adoption_events');
    }
};
