<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La base de comparación de «¿qué cambió desde la última publicación?» (WU4).
 *
 * Se guarda el **id** más alto del log de actividad en el momento de aceptar una publicación, y
 * no una fecha. La fecha se usó primero y se midió que no alcanza: las columnas `timestamp` de
 * MySQL tienen precisión de **segundo**, así que un cambio guardado en el mismo segundo que la
 * publicación es indistinguible de ella — hay un test que lo demuestra creando uno a cada lado
 * de la marca y afirmando que sólo uno se ve. Los ids son monótonos y exactos: no hay empate.
 *
 * Nullable a propósito: las publicaciones anteriores a esta migración no lo tienen, y sin marca
 * de agua la pantalla muestra todo lo registrado. Es la respuesta honesta («no sé desde
 * cuándo»), mejor que inventar una fecha de corte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_publication_states', function (Blueprint $table): void {
            $table->unsignedBigInteger('last_published_activity_id')
                ->nullable()
                ->after('last_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('website_publication_states', function (Blueprint $table): void {
            $table->dropColumn('last_published_activity_id');
        });
    }
};
