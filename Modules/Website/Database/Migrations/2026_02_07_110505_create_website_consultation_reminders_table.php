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
        Schema::create('website_consultation_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_request_id')->constrained('website_consultation_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('remind_at');
            $table->string('status')->default('pending'); // pending, completed, cancelled
            $table->string('type')->default('follow_up'); // follow_up, deadline, custom
            $table->json('notification_methods')->nullable(); // email, database, slack

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_consultation_reminders');
    }
};
