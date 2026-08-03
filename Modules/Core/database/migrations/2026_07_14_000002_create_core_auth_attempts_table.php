<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_auth_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('login_identifier')->nullable()->index();
            $table->string('event_type', 32)->index();
            $table->string('app_source')->index();
            $table->string('auth_channel', 80)->index();
            $table->string('failure_reason', 80)->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at'], 'core_auth_attempts_event_window_idx');
            $table->index(['app_source', 'occurred_at'], 'core_auth_attempts_source_window_idx');
            $table->index(['login_identifier', 'occurred_at'], 'core_auth_attempts_identifier_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_auth_attempts');
    }
};
