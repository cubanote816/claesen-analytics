<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_auth_security_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('alert_type', 40)->index();
            $table->string('alert_key')->unique();
            $table->timestamp('window_started_at')->index();
            $table->timestamp('window_ended_at')->index();
            $table->unsignedInteger('attempt_count');
            $table->string('identifier')->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('notified_at')->nullable()->index();
            $table->timestamps();

            $table->index(['alert_type', 'window_started_at'], 'core_auth_security_alerts_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_auth_security_alerts');
    }
};
