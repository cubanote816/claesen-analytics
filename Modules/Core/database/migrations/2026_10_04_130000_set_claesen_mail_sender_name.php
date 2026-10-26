<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El nombre del remitente de Claesen, explícito.
 *
 * Sin esto, `Site::mailFromName()` cae a `config('mail.from.name')`, que resuelve a
 * `APP_NAME` — «Laravel» en este despliegue. Cualquier camino que use el accesor del
 * sitio remitía como «Laravel»: lo veía el visitante que manda el formulario.
 *
 * **La dirección NO se toca**: se deja en NULL para que siga cayendo a
 * `config('mail.from.*')`, que ya es la de Claesen. Es una dirección **de producción**
 * y no corresponde fijarla desde acá.
 *
 * Una migración y no un seeder, por lo mismo que la de Bertels: tiene que llegar al
 * servidor, y un seeder no corre en el despliegue.
 *
 * Nota para quien lea esto después: las notificaciones del núcleo (reseteo y código de
 * acceso) ya salían bien por otra vía —`MailBrand` prefiere el nombre de la
 * organización— así que este hueco sólo se veía en las mails del propio sitio.
 */
return new class extends Migration
{
    private const SITE_KEY = 'claesen-verlichting';

    private const FROM_NAME = 'Claesen Verlichting';

    public function up(): void
    {
        DB::table('sites')
            ->where('key', self::SITE_KEY)
            ->update(['mail_from_name' => self::FROM_NAME]);
    }

    public function down(): void
    {
        DB::table('sites')
            ->where('key', self::SITE_KEY)
            ->update(['mail_from_name' => null]);
    }
};
