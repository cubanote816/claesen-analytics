<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_deliverability_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                  ->constrained('mailing_campaigns')
                  ->cascadeOnDelete();
            $table->enum('alert_type', ['hard_bounce_high', 'spam_complaint_high']);
            $table->decimal('rate', 10, 6);
            $table->decimal('threshold', 10, 6);
            $table->unsignedInteger('sent_count');
            $table->unsignedInteger('event_count');
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();

            // Primary idempotency guard: one alert per campaign+type
            $table->unique(['campaign_id', 'alert_type']);
            $table->index(['alert_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_deliverability_alerts');
    }
};
