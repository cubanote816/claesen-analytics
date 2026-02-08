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
        Schema::create('website_consultation_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_request_id')->constrained('website_consultation_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type'); // status_change, comment, file_attachment, assignment, note, priority_change, created
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('data')->nullable(); // Metadata: old_value, new_value, file_path, etc.
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();

            $table->timestamp('activity_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_consultation_activities');
    }
};
