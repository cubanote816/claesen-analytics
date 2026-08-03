<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->string('app_source')->index();
            $table->string('auth_channel', 80)->index();
            $table->string('session_id')->nullable()->index();
            $table->string('access_token_name')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'event_type', 'occurred_at'], 'core_access_events_user_event_idx');
            $table->index(['app_source', 'occurred_at'], 'core_access_events_source_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_access_events');
    }
};
