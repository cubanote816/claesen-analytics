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
        Schema::create('website_consultation_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_request_id')
                ->constrained('website_consultation_requests', indexName: 'wcn_request_id_foreign')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type'); // assignment, due_date, status_change
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high, urgent

            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_consultation_notifications');
    }
};
