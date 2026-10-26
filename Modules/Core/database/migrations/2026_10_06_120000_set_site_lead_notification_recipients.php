<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A quién le llega el aviso interno de una solicitud nueva, por sitio.
 *
 * Va en una **migración** y no en un seeder, igual que `set_bertels_mail_sender` y por la
 * misma razón: un seeder no corre en el despliegue, y esto tiene que llegar al servidor.
 *
 * `mail_notification_email` es la columna de `sites` que `Site::notificationEmail()` lee
 * primero. Con NULL cae a `config('website.consultation_notification_email')`, que está
 * vacío, así que **el aviso se omitía por completo**: el lead se guardaba y nadie se
 * enteraba (el comportamiento que CLA-532 estableció, nunca un fallo duro). Elegir esa
 * dirección es una decisión de negocio y quedó explícitamente fuera de
 * `set_bertels_mail_sender`; esta migración la cierra.
 *
 * **Los valores son provisionales y el usuario lo dijo el 2026-10-06**: los va a cambiar.
 *
 * **No hay pantalla para cambiarlos, y conviene saberlo**: la pantalla de ajustes del sitio
 * edita `site_settings` (una fila por clave) y esto es una **columna de `sites`**, así que un
 * cambio futuro es otra migración o un comando, no un tecleo en el panel.
 *
 * No se toca ningún remitente: sólo el destinatario del aviso interno.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const RECIPIENTS = [
        'claesen-verlichting' => 'orelvys.cuellar@claesen-verlichting.be',
        'electro-bertels' => 'cubanote816@gmail.com',
    ];

    public function up(): void
    {
        foreach (self::RECIPIENTS as $siteKey => $address) {
            DB::table('sites')
                ->where('key', $siteKey)
                ->update(['mail_notification_email' => $address]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::RECIPIENTS) as $siteKey) {
            DB::table('sites')
                ->where('key', $siteKey)
                ->update(['mail_notification_email' => null]);
        }
    }
};
