<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')
                  ->constrained('mailing_messages')
                  ->cascadeOnDelete();
            $table->enum('event_type', [
                'sent',
                'delivered',
                'opened',
                'clicked',
                'bounced_hard',
                'bounced_soft',
                'complained',
                'unsubscribed',
            ]);
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->index(['message_id', 'event_type']);
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_message_events');
    }
};
