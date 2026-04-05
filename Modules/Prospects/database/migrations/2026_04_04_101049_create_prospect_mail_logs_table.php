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
        Schema::create('prospect_mail_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects_prospects')->cascadeOnDelete();
            $table->string('email');
            $table->string('template_name');
            $table->text('subject_snapshot')->nullable();
            $table->longText('body_snapshot')->nullable();
            $table->string('status')->default('sent'); // sent, failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_mail_logs');
    }
};
