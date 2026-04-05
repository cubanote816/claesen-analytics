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
        Schema::create('sync_histories', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->string('type')->default('individual'); // individual or master
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->integer('records_count')->default(0);
            $table->json('logs')->nullable(); // Structured event log
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // Can't easily use foreignId if users is in another module/connection without care
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_histories');
    }
};
