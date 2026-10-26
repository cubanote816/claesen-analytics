<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only history of acceptance-test executions.
 *
 * BACKEND-API-ZONES.md §3.2 requires it in so many words: "corregir la incidencia
 * y repetir la prueba deja **dos ejecuciones** registradas (no se sobreescribe el
 * fallo anterior)". A single `status` column on the test cannot honour that — the
 * second run would erase the evidence of the first, and that evidence is part of
 * what the client signs off at delivery.
 *
 * The test row keeps the current state (which is what the office app reads); this
 * table is the trail behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_test_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acceptance_test_id')->constrained('knx_acceptance_tests')->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('observed')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('executor_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['acceptance_test_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_test_executions');
    }
};
