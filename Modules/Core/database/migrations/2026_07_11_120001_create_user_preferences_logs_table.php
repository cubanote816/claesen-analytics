<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_preferences_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->json('old_preferences')->nullable();
            $table->json('new_preferences');
            $table->json('changed_fields');
            $table->timestamp('changed_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->index('user_id', 'idx_preferences_logs_user_id');
            $table->index('changed_at', 'idx_preferences_logs_changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences_logs');
    }
};
