<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_suppression_list', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('prospect_id')
                  ->nullable()
                  ->constrained('prospects_prospects')
                  ->nullOnDelete();
            $table->enum('reason', [
                'unsubscribed',
                'hard_bounce',
                'spam_complaint',
                'soft_bounce_limit',
                'manual',
            ]);
            $table->foreignId('source_campaign_id')
                  ->nullable()
                  ->constrained('mailing_campaigns')
                  ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('suppressed_at');
            $table->foreignId('suppressed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_suppression_list');
    }
};
