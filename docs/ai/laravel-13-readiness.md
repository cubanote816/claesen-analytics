# Laravel 13 — baseline y readiness de PHP/Composer

> Tickets: CLA-515 (Done), CLA-520 (cierre técnico aprobado; Linear In Progress hasta el gate productivo) y CLA-518 (matriz en revisión técnica), subtickets de CLA-514.
> Fecha del inventario: 2026-08-29; matriz de compatibilidad actualizada el 2026-08-30.
> Alcance: baseline técnico y saneamiento compatible; permanece en Laravel 12 y no introduce APIs de Laravel 13.

## Decisión de readiness

Los entornos local y productivo están listos a nivel de PHP/Composer para ejecutar el trabajo de migración, pero el proyecto todavía **no está listo para cambiar a Laravel 13**. CLA-518 confirmó que el grafo objetivo es resoluble, pero el cambio real debe esperar a que todo el trabajo Laravel 12 pendiente esté integrado y a que existan los gates de CI/staging.

El requisito de plataforma vigente quedó elevado de PHP `^8.2` a `^8.3`, mínimo de Laravel 13. La matriz objetivo eleva el proyecto a PHP `^8.4`, porque `spatie/laravel-activitylog` 5 lo exige. La imagen de desarrollo y los scripts operativos versionados ya están alineados en PHP 8.4; CI y staging todavía deben certificarlo.

La matriz exacta, el lockfile simulado y el gate de revalidación son fuente de verdad en [`docs/ai/laravel-13-compatibility-matrix.md`](laravel-13-compatibility-matrix.md).

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
- Laravel: 12.68.0; Filament: 5.7.6; Livewire: 4.4.2; Sanctum: 4.3.3; Laravel Modules: 12.0.4; Spatie Permission: 6.24.0.

El `Dockerfile` de Sail usa Ubuntu 24.04, PHP 8.4 y extensiones MySQL/SQLite/Redis/Imagick/Intl/GD/Zip, además de ODBC y `sqlsrv`. `compose.yaml` construye desde `docker/8.4`; la etiqueta incoherente `sail-8.5/app` se corrigió a `sail-8.4/app`.

## Composer y lockfile

- Composer 2.7.1, instalado por APT, emitía deprecations de `E_STRICT` bajo PHP 8.4.
- Se instaló Composer 2.10.3 para el usuario en `~/.local/bin/composer` desde `getcomposer.org`; el SHA-256 publicado fue verificado antes de instalar.
- `composer.json` exige ahora PHP `^8.3`.
- `lara-zeus/spatie-translatable` pasó de `*` a `^2.0`; el lockfile resolvió 2.0.1. Esa versión declara PHP `^8.1`, Filament `^5.0` y `spatie/laravel-translatable ^6.0`, compatibles con el baseline actual.
- En CLA-515, la actualización parcial cambió solo esa dependencia; Laravel, Filament y las demás dependencias raíz quedaron inmóviles hasta el saneamiento controlado de CLA-520.
- Composer 2.10 aplica bloqueo de seguridad durante una resolución completa. CLA-520 regeneró de forma controlada las entradas afectadas del lock; no desactivar el audit ni el bloqueo de seguridad en actualizaciones posteriores.

### Saneamiento CLA-520

- Se actualizaron 34 paquetes en tres lotes con `--with-all-dependencies --minimal-changes`; no hubo instalaciones, eliminaciones ni saltos de major y `composer.json` no cambió.
- Versiones de mayor riesgo revisadas: Laravel 12.68.0, Filament 5.7.6, Livewire 4.4.2, Sanctum 4.3.3, Dompdf 3.1.6, Spatie MediaLibrary 11.23.5, Guzzle 7.15.5, CommonMark 2.10.0, phpseclib 3.0.57 y PsySH 0.12.24.
- Los componentes Symfony afectados quedaron dentro de las líneas compatibles ya permitidas: DomCrawler/HtmlSanitizer 8.1.x y HttpFoundation/HttpKernel/Mailer/Mime/Routing/Yaml 7.4.x.
- La instalación limpia desde el lock actualizado resolvió 179 paquetes en `/tmp` con scripts deshabilitados y sin reutilizar `vendor/`.

## Baseline de seguridad

| Check | Resultado |
|---|---|
| `npm audit --audit-level=moderate` | PASS, 0 vulnerabilidades |
| `composer audit` | PASS tras CLA-520: 0 advisories y 0 paquetes abandonados |

El baseline de CLA-515 contenía 60 advisories en 21 paquetes, incluidos Laravel, Filament, Guzzle, CommonMark, Dompdf, MediaLibrary, phpseclib y Symfony. CLA-520 eliminó esa exposición manteniendo Laravel 12 y sin desactivar el bloqueo de seguridad.

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

### Regresión diferencial de CLA-520

- Suite completa en `testing_cla520`: **1086 passed, 186 failed, 2 skipped; 3270 assertions; 1131.20 s**.
- Diferencial contra CLA-515: 14 tests adicionales pasan y no apareció ninguna familia nueva de fallos. Persisten Example/locale-config, estado compartido FieldOps, Mailing y rollback Website.
- Login/sesiones/permisos, FieldOps tenant/media, Filament, PDF Safety, media Website y payload Microsoft Graph se ejecutaron focalizadamente. El único fallo focalizado no FieldOps fue el caso NL de `WorkDetailsTest`, ya documentado en el baseline de locale/config.
- Filament 5.7 escapa ahora las URLs generadas en atributos y Livewire 4.4 serializa de forma más segura JavaScript embebido. Se adaptaron cuatro archivos de test para verificar la representación segura equivalente; no se cambió código de aplicación.
- `npm run build`, `php artisan about`, scheduler, Pint focalizado y `git diff --check` pasan.

## Seguimientos posteriores a CLA-515

- Definir runtime y gate de tests/build/audit en CI; con el baseline actual la ventana debe superar 40 minutos o, preferiblemente, reducir antes los rollbacks lentos de Website.
- Registrar el reemplazo del health check en CLA-523: debe validar la ruta interna/túnel real y mantener la prohibición de exposición pública.
- Decidir si la ausencia deliberada de CI de tests y staging en este punto del programa se acepta como baseline de CLA-515 o requiere waiver explícito; su implementación/certificación corresponde a CLA-524/CLA-525.

## Matriz y simulación CLA-518

- El lock real permanece en Laravel 12.68.0. CLA-518 no modifica `composer.json`, `composer.lock`, aplicación ni base de datos.
- Una copia aislada de los manifests resolvió Laravel 13.29.0 con 177 paquetes: 2 instalaciones, 107 actualizaciones y 4 eliminaciones. La instalación limpia, validación estricta, requisitos de plataforma y auditoría pasan.
- Majors raíz coordinados: Tinker 3, plugin Filament Media 5.7, Laravel Modules 13, Activitylog 5, Permission 8, Query Builder 7 y PHPUnit 12. Filament permanece en 5.7, Livewire en 4.4, MediaLibrary en 11 y Collision en 8.
- Activitylog 5 es el bloqueante de mayor riesgo: exige PHP 8.4 y una transformación reversible de `activity_log` antes de retirar columnas legacy. Esa tarea pertenece a CLA-526 y requiere backup/rollback probado.
- El código contiene puntos de adaptación para CSRF, cache, sesión y bootstrap, además de regresiones obligatorias para dominios/túnel, módulos, permisos, mail, colas y scheduler. Se asignaron a CLA-519/516/517/521/522/526.
- Antes de CLA-519 se deben integrar las ramas Laravel 12 activas, congelar cambios de dependencias/bootstrap y repetir `why-not`, resolución completa y audit sobre la rama base final. Cualquier cambio posterior relevante invalida la matriz.
- La existencia de una solución del solver no autoriza todavía el upgrade ni el despliegue; CI/staging, PHPUnit 12 y el rollback productivo siguen en CLA-524/525/523.

## Secuencia posterior

1. CLA-520: cierre técnico y waiver diferencial aprobados; pendiente únicamente del gate de despliegue/observación reservado a CLA-523.
2. CLA-518: matriz y lockfile objetivo simulados; GO técnico y waiver documental aprobados.
3. Gate previo a CLA-519: integrar el trabajo Laravel 12 pendiente, congelar dependencias/bootstrap y revalidar el solver sobre la rama base final.
4. CLA-519: actualizar el núcleo y configuración a Laravel 13.
5. CLA-516, CLA-517 y CLA-526: Filament/media, Laravel Modules y paquetes Spatie.
6. CLA-521/CLA-522: certificar autenticación mult-dominio y subsistemas operativos.
7. CLA-524: PHPUnit 12 y estabilización de la suite.
8. CLA-525/CLA-523: staging, E2E, producción, observabilidad y rollback.
