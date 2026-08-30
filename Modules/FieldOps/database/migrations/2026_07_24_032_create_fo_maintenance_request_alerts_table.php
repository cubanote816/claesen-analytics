<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_maintenance_request_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained('fo_maintenance_requests')->cascadeOnDelete();
            $table->string('alert_type');
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // One row per (request, type): re-triggering after resolution reuses
            // the same row instead of accumulating history, mirroring
            // mailing_deliverability_alerts' idempotency key. Named explicitly:
            // the auto-generated name exceeds MySQL's 64-char identifier limit.
            $table->unique(['maintenance_request_id', 'alert_type'], 'fo_request_alerts_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_maintenance_request_alerts');
    }
};
