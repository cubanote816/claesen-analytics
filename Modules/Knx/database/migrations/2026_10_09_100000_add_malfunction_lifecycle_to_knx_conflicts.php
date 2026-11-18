<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mal funcionamiento como ciclo propio (KNX-56).
 *
 * Un conflicto nace `ets` (la lista de corrección del Conflictencentrum, libre y
 * reversible). `lifecycle: malfunction` marca la incidencia de la instalación física:
 * `open → diagnosing → resolved → verified` con `rejected` como salida, transiciones
 * validadas en el servicio.
 *
 * `symptom` es lo que hace el elemento (no enciende, se apaga sola, actúa sobre otra
 * zona…), el campo que en un conflicto ETS ocupa `proposal`: son dos preguntas
 * distintas, así que no se reutiliza la misma columna.
 *
 * El histórico gana autor y nota. Sigue siendo append-only, y el autor se guarda como
 * texto a propósito: es el nombre tal como estaba en el momento del paso, no un FK que
 * cambia si la persona se renombra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_conflicts', function (Blueprint $table): void {
            $table->string('lifecycle', 20)->default('ets')->after('type');
            $table->text('symptom')->nullable()->after('note');

            $table->index(['organization_id', 'lifecycle']);
        });

        Schema::table('knx_conflict_logs', function (Blueprint $table): void {
            $table->string('by', 150)->nullable()->after('action');
            $table->text('note')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('knx_conflicts', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'lifecycle']);
            $table->dropColumn(['lifecycle', 'symptom']);
        });

        Schema::table('knx_conflict_logs', function (Blueprint $table): void {
            $table->dropColumn(['by', 'note']);
        });
    }
};
