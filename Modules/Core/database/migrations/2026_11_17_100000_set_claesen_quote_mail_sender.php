<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CLA-699: las respuestas a las consultas de presupuesto de la web de Claesen salen
 * de offerte@, no del remitente de la plataforma.
 *
 * El correo automático de Claesen (códigos, reseteos, avisos) usa `config('mail.from.*')`
 * (backoffice@ en producción); `sites.mail_from_address` del sitio Claesen queda como el
 * remitente de los presupuestos, que es lo único que lee `MailBrand::forSite()`.
 *
 * Migración y no seeder, igual que `2026_10_04_120000_set_bertels_mail_sender`: tiene que
 * llegar al servidor con el despliegue. Solo actualiza una fila existente; nunca inserta
 * ni borra, y no toca Bertels.
 */
return new class extends Migration
{
    private const FROM_ADDRESS = 'offerte@claesen-verlichting.be';

    public function up(): void
    {
        DB::table('sites')
            ->where('key', config('organizations.panel_sites.admin'))
            ->update(['mail_from_address' => self::FROM_ADDRESS]);
    }

    public function down(): void
    {
        DB::table('sites')
            ->where('key', config('organizations.panel_sites.admin'))
            ->update(['mail_from_address' => null]);
    }
};
