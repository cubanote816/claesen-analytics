<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La intención por elemento (KNX-55): qué debe hacer un módulo, un canal o un aparato.
 *
 * Una fila por **versión**, no por elemento: el contrato pide historial (`GET /intents/{id}/versions`)
 * y una sola intención vigente por `target`. `is_current` marca la vigente y `version` la numera
 * dentro de la identidad estable (`target_key`), con el índice único como red de seguridad.
 *
 * `text` es texto libre: la intención la escribe una persona y se lee tal cual.
 * `target_key` no es una columna de presentación: es la identidad del elemento dentro del proyecto
 * (`module:E10:3`, `channel:E10:3:kanaal 2`, `device:1893`), la que decide si un `PUT` crea versión
 * o es un no-op. Es string y no un hash para poder leerla en la BD al depurar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();

            $table->string('target_type', 16);
            $table->string('target_key', 191);

            $table->foreignId('board_id')->nullable()->constrained('knx_boards')->nullOnDelete();
            $table->unsignedInteger('module_slot')->nullable();
            $table->string('channel', 100)->nullable();
            $table->foreignId('device_id')->nullable()->constrained('knx_devices')->nullOnDelete();

            $table->foreignId('room_id')->nullable()->constrained('knx_project_rooms')->nullOnDelete();
            $table->foreignId('function_id')->nullable()->constrained('knx_function_specs')->nullOnDelete();

            $table->text('text');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->foreignId('author_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'target_key', 'version']);
            $table->index(['project_id', 'is_current']);
            $table->index('room_id');
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_intents');
    }
};
