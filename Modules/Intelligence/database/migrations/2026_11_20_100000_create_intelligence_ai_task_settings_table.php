<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CLA-714: provider and model per AI task, chosen on the "AI providers" screen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_ai_task_settings', function (Blueprint $table) {
            $table->id();
            $table->string('task', 64)->unique();
            $table->string('provider', 32);
            $table->string('model', 100);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_ai_task_settings');
    }
};
