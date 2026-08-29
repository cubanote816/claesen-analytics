# Laravel 13 — baseline y readiness de PHP/Composer

> Ticket: CLA-515 (Done), primer subticket de CLA-514.
> Fecha del inventario: 2026-08-29.
> Alcance: baseline técnico; no actualiza Laravel ni corrige en bloque las advisories.

## Decisión de readiness

Los entornos local y productivo están listos a nivel de PHP/Composer para ejecutar el trabajo de migración, pero el proyecto todavía **no está listo para cambiar a Laravel 13**. Antes deben cerrarse el saneamiento de Laravel 12 (CLA-520), la matriz de compatibilidad (CLA-518) y los gates de CI/staging.

El requisito de plataforma del proyecto queda elevado de PHP `^8.2` a `^8.3`, que es el mínimo de Laravel 13. La imagen de desarrollo y los scripts operativos versionados quedan alineados en PHP 8.4.

## Matriz de entornos

| Entorno / runtime | PHP | Composer | Evidencia | Estado |
|---|---:|---:|---|---|
| Host local, CLI | 8.4.24 | 2.10.3 | `php -v`, `php --ri imagick`, `composer --version` | Listo; Composer oficial sin deprecations e Imagick 3.8.1 cargado |
| Sail local, web/CLI | 8.4 definido; 8.4.17 verificado | Instalador oficial durante el build | `docker/8.4/Dockerfile`, `compose.yaml`, contenedor compartido activo | Listo; `Imagick` está cargado. Este worktree no tiene un contenedor propio |
| CI de tests | Sin runtime definido | Sin runtime definido | Solo existe `.github/workflows/deploy.yml` | Bloqueado: no existe workflow de tests/build independiente |
| Runner de deploy | 8.4.22 CLI (`/usr/bin/php8.4`) | 2.10.1 | Verificación directa en `prod-priv-01` + GitHub Actions run 106 | Listo; sin deprecations observadas |
| Staging | No inventariado / no configurado en el repo | No inventariado | No hay workflow ni host de staging versionado | Bloqueado; corresponde a CLA-525 |
| Producción, PHP-FPM | 8.4, servicio activo | — | `systemctl is-active php8.4-fpm` + reload exitoso en run 106 | Listo |
| Producción, CLI/queues/scheduler | 8.4.22, `/usr/bin/php8.4` | 2.10.1 | Procesos reales de Supervisor inspeccionados | Listo; dos workers Redis y `schedule:work` en estado RUNNING |

No queda ningún runtime productivo PHP 8.2: CLI, workers y scheduler ejecutan `/usr/bin/php8.4` 8.4.22, PHP-FPM 8.4 está activo y Composer 2.10.1 funciona sin deprecations observadas bajo ese runtime.

## Plataforma local

- PHP CLI: 8.4.24.
- Extensiones cargadas: `calendar`, `ctype`, `curl`, `dom`, `exif`, `fileinfo`, `ftp`, `gd`, `gettext`, `intl`, `mbstring`, `mysqli`, `mysqlnd`, `openssl`, `pcntl`, `PDO`, `pdo_mysql`, `pdo_sqlite`, `posix`, `readline`, `SimpleXML`, `sockets`, `sodium`, `sqlite3`, `xml`, `xmlreader`, `xmlwriter`, `xsl`, `zip` y OPcache, además de las extensiones core.
- `Imagick` 3.8.1 está cargado en el PHP CLI del host y soporta 240 formatos. El contenedor Sail activo también lo carga con PHP 8.4.17.
- Node.js: 22.22.0.
- npm: 10.9.4.
- MySQL local: imagen 8.4, publicada por el worktree principal en `127.0.0.1:3308` durante este inventario.
- Laravel: 12.50.0; Filament: 5.2.0; Livewire: 4.1.3; Laravel Modules: 12.0.4; Spatie Permission: 6.24.0.

El `Dockerfile` de Sail usa Ubuntu 24.04, PHP 8.4 y extensiones MySQL/SQLite/Redis/Imagick/Intl/GD/Zip, además de ODBC y `sqlsrv`. `compose.yaml` construye desde `docker/8.4`; la etiqueta incoherente `sail-8.5/app` se corrigió a `sail-8.4/app`.

## Composer y lockfile

- Composer 2.7.1, instalado por APT, emitía deprecations de `E_STRICT` bajo PHP 8.4.
- Se instaló Composer 2.10.3 para el usuario en `~/.local/bin/composer` desde `getcomposer.org`; el SHA-256 publicado fue verificado antes de instalar.
- `composer.json` exige ahora PHP `^8.3`.
- `lara-zeus/spatie-translatable` pasó de `*` a `^2.0`; el lockfile resolvió 2.0.1. Esa versión declara PHP `^8.1`, Filament `^5.0` y `spatie/laravel-translatable ^6.0`, compatibles con el baseline actual.
- La actualización parcial cambió solo esa dependencia. Laravel, Filament y las demás dependencias raíz quedaron inmóviles.
- Composer 2.10 aplica bloqueo de seguridad durante una resolución completa. El lock actual contiene versiones afectadas, por lo que una regeneración global debe realizarse dentro de CLA-520/CLA-518 y no ocultarse desactivando el audit de forma permanente.

## Baseline de seguridad

| Check | Resultado |
|---|---|
| `npm audit --audit-level=moderate` | PASS, 0 vulnerabilidades |
| `composer audit` | FAIL esperado: 60 advisories en 21 paquetes |

Entre los paquetes afectados están Laravel 12.50.0, Filament 5.2.0, Guzzle, League CommonMark, Dompdf, Spatie MediaLibrary, phpseclib y varios componentes Symfony. El saneamiento está separado en CLA-520 porque requiere una actualización coordinada y regresión completa, no un cambio incidental del baseline.

## Jobs, scheduler, workers y despliegue

- `CACHE_STORE=array php artisan schedule:list` enumera 24 entradas: FieldOps, Intelligence, Mailing, Performance, Prospects, Safety, Analytics, Employee y Website.
- El host local no tiene crontab de scheduler ni procesos locales de queue/scheduler activos.
- `deploy.sh` instala dependencias con Composer, ejecuta `npm ci`/build, migra con `--force`, optimiza caches, recarga `php8.4-fpm`, ejecuta `queue:restart` e inicia `claesen-worker:*` y `claesen-scheduler` mediante Supervisor.
- `monitor.sh` todavía comprobaba `php8.3-fpm`; se alineó a `php8.4-fpm`.
- `infrastructure/supervisor/cafca-sync-worker.conf` apunta a `/home/totti/claesen_api_web_oficial`, corre como `totti` y declara `cafca-worker`, mientras el deploy productivo usa `/srv/www/claesen` y nombres `claesen-*`. Se registra como artefacto posiblemente obsoleto; no se reescribe sin contrastarlo con Supervisor real en CLA-522.
- El único workflow de GitHub es de despliegue directo a producción al hacer push a `main`; no ejecuta suite, build ni audit como gate previo.
- La ejecución 106 del workflow, sobre `b81e8a3` el 2026-08-29, corrió en el runner `prod-priv-01` 2.336.0. El paso de deploy terminó correctamente: Composer instaló 144 paquetes, Artisan aplicó la migración 036, PHP-FPM 8.4 se recargó, dos workers y `claesen-scheduler` reiniciaron y la aplicación salió de mantenimiento.
- Verificación directa: `claesen-worker_00`, `claesen-worker_01` y `claesen-scheduler` están RUNNING. Los tres procesos usan `/usr/bin/php8.4`; los workers ejecutan `queue:work redis --queue=default` y el scheduler ejecuta `schedule:work`.
- **Restricción de seguridad:** `backoffice.claesen.local` es exclusivamente LAN y no debe exponerse a Internet. Las demás aplicaciones de la empresa se comunican mediante túnel. Esta condición es deliberada y no debe “corregirse” publicando el backoffice.
- El job 106 terminó en rojo únicamente porque su health check ejecuta `curl https://backoffice.claesen.local/`: el DNS interno resuelve `192.168.254.52`, pero no hay listener aceptando conexiones en `192.168.254.52:443` desde el host de diagnóstico. La última ejecución exitosa fue la 95, el 2026-07-07. El deploy completó; CLA-523 debe sustituir ese check por uno que represente el listener interno o la ruta real del túnel, sin ampliar exposición de red.

## Baseline de tests y migraciones

- `composer validate --strict --no-check-publish`: PASS tras fijar las restricciones.
- Instalación limpia desde `composer.lock`: PASS; 179 paquetes instalados en `/tmp/cla515-vendor` con scripts deshabilitados, sin usar ni modificar el vendor del proyecto.
- `npm run build`: PASS con Vite 7.3.6.
- `php artisan about`: PASS.
- `CACHE_STORE=array php artisan schedule:list`: PASS.
- La base `claesen_analytics_web_testing` está previamente inconsistente: `fo_terrain_types` existe, pero su migración no figura como aplicada. No se borró ni se alteró manualmente.
- Todas las migraciones pasan desde cero en la base aislada `testing_cla515`, incluida `2026_08_28_036_add_fieldops_infrastructure_permissions`.
- Suite completa canónica, sin otra ejecución sobre esa base: **1072 passed, 200 failed, 2 skipped; 3232 assertions; 2223.18 s**.
- PHPUnit emitió cinco warnings por metadata en doc-comments: cuatro en `LocalizationTest` y uno en `MirrorSyncStatusPageManualSyncTest`.
- Fallos de baseline confirmados, agrupados por causa observable:
  - placeholder `Tests\\Feature\\ExampleTest`: `/` responde 302 y el test espera 200;
  - configuración/locale: `LocalizationTest`, `MicrosoftAuthRedirectTest` y `WorkDetailsTest` reciben valores distintos de los esperados;
  - aislamiento/estado compartido de FieldOps: `RoleAlreadyExists: super_admin`, colisión del código único `soccer` y casos de permisos/roles baseline;
  - Mailing: selección/idempotencia A/B, notificaciones duplicadas y jobs programados/follow-up no despachados como esperan los tests;
  - Website/migraciones: el rollback intenta borrar el índice `fo_luminaires_one_active_per_position`, aún requerido por una foreign key, lo que además hace varios tests extremadamente lentos;
  - Website/media: dos casos fallaron porque la clase `Imagick` no existía en el host local. Tras instalar `php8.4-imagick`, ambos pasan de forma focalizada: 2 passed, 17 assertions.
- Este resultado no prueba una regresión causada por elevar el requisito de PHP o fijar la dependencia: prueba que el baseline previo del repositorio no está verde. La estabilización corresponde principalmente a CLA-524; los gaps de permisos FieldOps deben resolverse en su ticket funcional propio.

## Seguimientos posteriores a CLA-515

- Definir runtime y gate de tests/build/audit en CI; con el baseline actual la ventana debe superar 40 minutos o, preferiblemente, reducir antes los rollbacks lentos de Website.
- Registrar el reemplazo del health check en CLA-523: debe validar la ruta interna/túnel real y mantener la prohibición de exposición pública.
- Decidir si la ausencia deliberada de CI de tests y staging en este punto del programa se acepta como baseline de CLA-515 o requiere waiver explícito; su implementación/certificación corresponde a CLA-524/CLA-525.

## Secuencia posterior

1. CLA-520: sanear advisories manteniendo Laravel 12.
2. CLA-518: definir la matriz de compatibilidad y el lockfile objetivo.
3. CLA-519: actualizar el núcleo y configuración a Laravel 13.
4. CLA-516, CLA-517 y CLA-526: Filament/media, Laravel Modules y paquetes Spatie.
5. CLA-521/CLA-522: certificar autenticación mult-dominio y subsistemas operativos.
6. CLA-524: PHPUnit 12 y estabilización de la suite.
7. CLA-525/CLA-523: staging, E2E, producción, observabilidad y rollback.
