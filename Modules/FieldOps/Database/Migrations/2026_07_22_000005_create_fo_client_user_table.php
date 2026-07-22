<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_client_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fo_client_id')->constrained('fo_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('can_view')->default(true);
            $table->boolean('can_report')->default(true);
            $table->boolean('can_manage_contacts')->default(false);
            $table->timestamps();

            $table->unique(['fo_client_id', 'user_id']);
            $table->index(['user_id', 'is_active', 'can_view']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_client_user');
    }
};
