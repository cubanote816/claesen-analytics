<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devices reported from the field app (Veld) that the office has not yet
 * acknowledged. This is what Kantoor polls every 5 s and what `acked` drives.
 *
 * `board_id` is nullable and that is meaningful: a field registration can
 * report a board the office has never seen ("Nuevo cuadro (desconocido)"),
 * which is exactly the case the office must triage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('knx_devices')->nullOnDelete();
            $table->foreignId('board_id')->nullable()->constrained('knx_boards')->nullOnDelete();
            $table->string('type');
            $table->string('address', 32);
            $table->string('room')->nullable();
            $table->string('serial')->nullable();
            $table->string('reported_by_name')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_notifications');
    }
};
