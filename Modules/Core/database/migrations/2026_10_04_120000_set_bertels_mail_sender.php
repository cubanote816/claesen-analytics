<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El sitio de Bertels manda correo desde su propio dominio.
 *
 * Va en una **migración** y no en un seeder a propósito: un seeder no corre en el
 * despliegue, y esto tiene que llegar al servidor. El repo ya usa este patrón para
 * datos de `sites` (`2026_09_17_100100_seed_claesen_organization_and_site`).
 *
 * Con la columna puesta, los dos caminos que mandan correo de Bertels quedan
 * correctos sin tocar código: las mails del sitio (`ConsultationConfirmationMail`,
 * `NewConsultationRequestMail`) ya leen `Site::mailFromAddress()`, y `MailBrand`
 * —que resuelve lo mismo para el reseteo de contraseña y el código de acceso— usa
 * esa misma fuente.
 *
 * El nombre también se fija explícito, aunque hoy ya coincida con el de la
 * organización: así el remitente no depende de que nadie renombre la organización.
 *
 * Claesen no se toca: su sitio deja las dos columnas en NULL y sigue cayendo a
 * `config('mail.from.*')`, que ya es suyo.
 *
 * Queda **fuera** de esta migración `mail_notification_email` (a quién le llegan los
 * leads): hoy está vacío en los dos sitios, lo que significa que el aviso interno se
 * omite, y elegir esa dirección es una decisión de negocio, no una consecuencia del
 * dominio del remitente.
 */
return new class extends Migration
{
    private const SITE_KEY = 'electro-bertels';

    private const FROM_ADDRESS = 'info@electrobertels.be';

    private const FROM_NAME = 'Electro Bertels';

    public function up(): void
    {
        DB::table('sites')
            ->where('key', self::SITE_KEY)
            ->update([
                'mail_from_address' => self::FROM_ADDRESS,
                'mail_from_name' => self::FROM_NAME,
            ]);
    }

    public function down(): void
    {
        DB::table('sites')
            ->where('key', self::SITE_KEY)
            ->update([
                'mail_from_address' => null,
                'mail_from_name' => null,
            ]);
    }
};
