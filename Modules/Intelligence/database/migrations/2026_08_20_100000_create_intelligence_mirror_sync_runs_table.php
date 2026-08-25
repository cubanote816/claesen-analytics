<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_mirror_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('running'); // running, completed, failed
            $table->string('trigger_source')->default('scheduled'); // scheduled, manual
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('triggered_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_mirror_sync_runs');
    }
};
