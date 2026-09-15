# Laravel 13 — matriz de compatibilidad y lockfile objetivo

> Ticket: CLA-518, subticket de CLA-514.
> Fecha de corte: 2026-08-30.
> Baseline inspeccionado: commit `dea0d19`, Laravel 12.68.0, PHP `^8.3`.
> Alcance: diagnóstico y contrato de actualización. Este ticket no modifica `composer.json`, `composer.lock`, código de aplicación ni base de datos.

## Decisión

La migración es técnicamente viable, pero no debe ejecutarse todavía sobre este branch. Composer resolvió e instaló una combinación estable con Laravel 13 en un sandbox aislado; sin embargo, la actualización conjunta comprende 107 upgrades, 2 instalaciones y 4 eliminaciones. Además, `spatie/laravel-activitylog` 5 requiere una migración de datos y eleva el mínimo efectivo del proyecto a PHP 8.4.

La implementación corresponde a CLA-519 y solo puede comenzar cuando:

1. todo el trabajo todavía activo sobre Laravel 12 se haya integrado en la rama base acordada;
2. se abra una ventana de congelación para cambios de dependencias y bootstrap;
3. se repita esta resolución contra el `composer.json` y `composer.lock` resultantes;
4. los responsables de CLA-516, CLA-517 y CLA-526 confirmen los majors coordinados;
5. CI y staging puedan certificar PHP 8.4 antes del corte productivo.

No se recomienda fusionar ahora la rama de CLA-518 con las ramas funcionales en curso para “adelantar” Laravel 13. Este documento sí puede integrarse sin colisión de dependencias; el cambio real de framework debe esperar al gate anterior.

## Matriz de dependencias raíz

“Resuelto” es el resultado del sandbox del 2026-08-30, no un pin perpetuo. CLA-519 debe conservar las restricciones objetivo y regenerar el lockfile como una unidad, sin copiar entradas aisladas.

| Grupo | Paquete | Actual | Restricción objetivo | Resuelto | Compatibilidad / acción | Ticket |
|---|---|---:|---:|---:|---|---|
| Plataforma | `php` | `^8.3` | `^8.4` | 8.4.24 local | Laravel 13 acepta PHP 8.3, pero Activitylog 5 exige 8.4 | CLA-519 / CLA-525 |
| Núcleo | `laravel/framework` | 12.68.0 | `^13.0` | 13.29.0 | Major coordinado; aplicar guía oficial y diff del skeleton | CLA-519 |
| Núcleo | `laravel/tinker` | 2.11.0 | `^3.0` | 3.0.2 | Major requerido por la guía de Laravel 13 | CLA-519 |
| UI | `filament/filament` | 5.7.6 | `^5.7` | 5.7.6 | Permanece en Filament 5; ejecutar su upgrade tool y regresión del panel | CLA-516 |
| UI/media | `filament/spatie-laravel-media-library-plugin` | 3.3.30 | `^5.7` | 5.7.6 | Major obligatorio para alinear el plugin con Filament 5.7 | CLA-516 |
| Módulos | `nwidart/laravel-modules` | 12.0.4 | `^13.0` | 13.0.0 | Revisar configuración, autoload raíz y los nueve manifests modulares | CLA-517 |
| Auth | `laravel/sanctum` | 4.3.3 | `^4.0` | 4.3.3 | Compatible; certificar cookies, stateful domains y túneles | CLA-521 |
| Auth | `laravel/socialite` | 5.26.1 | `^5.26` | 5.30.1 | Minor coordinado; validar OAuth Microsoft | CLA-521 |
| Auth | `socialiteproviders/microsoft-azure` | 5.2.0 | `^5.2` | 5.2.1 | Compatible; verificar callback y provider listener | CLA-521 |
| Mensajería | `resend/resend-laravel` | 1.1.0 | `^1.4` | 1.4.0 | La línea 1.4 declara Laravel 13 | CLA-522 |
| Spatie | `spatie/laravel-activitylog` | 4.11.0 | `^5.0` | 5.1.0 | **Bloqueante de datos:** PHP 8.4 y migración de `activity_log` | CLA-526 |
| Spatie | `spatie/laravel-permission` | 6.24.0 | `^8.0` | 8.3.0 | Dos majors; auditar eventos, comandos, enums y modelos Core extendidos | CLA-526 |
| Spatie | `spatie/laravel-query-builder` | 6.4.1 | `^7.0` | 7.3.3 | Major; revisar `PortfolioService` y API de includes/agregados | CLA-526 |
| Spatie | `spatie/laravel-translatable` | 6.12.0 | `^6.14` | 6.14.1 | Minor compatible con Laravel 13 | CLA-526 |
| Spatie | `spatie/laravel-medialibrary` | 11.23.5 | `^11.0` | 11.23.5 | Sin major; probar conversiones, uploads e Imagick | CLA-516 |
| Spatie/Filament | `lara-zeus/spatie-translatable` | 2.0.1 | `^2.0` | 2.0.1 | Compatible con Filament 5 y Translatable 6 | CLA-516 / CLA-526 |
| Testing | `phpunit/phpunit` | 11.5.50 | `^12.0` | 12.5.34 | Major requerido; corregir metadata y estabilizar baseline | CLA-524 |
| Testing | `nunomaduro/collision` | 8.8.3 | `^8.9.5` | 8.9.5 | No requiere major; esa línea acepta Laravel 13 y PHPUnit 12 | CLA-524 |
| Tooling | `laravel/pail` | 1.2.4 | `^1.2.7` | 1.2.7 | Actualización compatible con Laravel 13 | CLA-519 |
| Tooling | `laravel/sail` | 1.52.0 | `^1.67` | 1.67.0 | Mantiene PHP 8.4; reconstruir imagen limpia | CLA-525 |

Las dependencias raíz no listadas conservaron su major en la resolución: Anthropic SDK 0.42, Dompdf 3.1, Pint 1.x, Faker 1.x, Mockery 1.x, Symfony CSS Selector 8.x y DOM Crawler 8.x. No se deben elevar por iniciativa propia durante CLA-519 salvo que el solver o una incompatibilidad demostrada lo exijan.

## Impacto transitivo relevante

El dry-run resolvió 177 paquetes: 2 instalaciones, 107 actualizaciones y 4 eliminaciones. Los cambios transitivos que merecen pruebas explícitas son:

| Componente | Cambio observado | Riesgo que debe cubrirse |
|---|---|---|
| Symfony | componentes del framework convergen en 8.1 | HTTP kernel, routing, mailer, MIME, DOM y comandos |
| Livewire | permanece en 4.4.2 | hidratación, navegación SPA y acciones Filament |
| `phpseclib/phpseclib` | 3.x → 4.x | autenticación/firmas de integraciones y Microsoft |
| `pragmarx/google2fa-qrcode` | 3.x → 4.x | flujo 2FA de Filament |
| `guzzlehttp/uri-template` | 1.x → 2.x | clientes HTTP e integraciones externas |
| `hamcrest/hamcrest-php` | 2.x → 3.x | mocks y suite PHPUnit |
| PHPUnit ecosystem | 11.x → 12.x | atributos, deprecations, mocks y cobertura |

Composer elimina `paragonie/random_compat`, `sebastian/code-unit`, `sebastian/code-unit-reverse-lookup` y `symfony/polyfill-php83`; agrega los polyfills PHP 8.2 y PHP 8.6 requeridos por el grafo resultante. Estos son efectos del solver, no paquetes que deban declararse en la raíz.

## Bloqueantes de código y configuración

El solver en verde prueba compatibilidad declarativa, no compatibilidad funcional. El inventario del repositorio dejó estos puntos obligatorios:

| Área | Evidencia local | Cambio / validación requerida | Ticket |
|---|---|---|---|
| CSRF | `AdminPanelProvider` importa y registra `VerifyCsrfToken`; Sanctum usa `ValidateCsrfToken` | adoptar `PreventRequestForgery` donde corresponda y probar la nueva validación `Sec-Fetch-Site` | CLA-519 / CLA-521 |
| Cache | `config/cache.php` ya usa prefijo con guiones, pero no define `serializable_classes` | comparar skeleton 13 y auditar clases serializadas | CLA-519 / CLA-522 |
| Sesiones | cookie ya usa el nombre compatible; no existe clave `serialization` | mantener serialización PHP durante el corte; evaluar JSON después para no invalidar sesiones sin decisión explícita | CLA-519 / CLA-521 |
| Bootstrap | `bootstrap/app.php` registra `ResolveSessionCookieDomain`, `statefulApi()` y scheduler | revisar el diff del skeleton sin perder middleware, API stateful ni tareas | CLA-519 / CLA-521 / CLA-522 |
| Rutas | Laravel 13 cambia la prioridad de rutas con dominio | probar dominios del backoffice, callbacks y APIs mediante LAN/túnel; nunca exponer el backoffice a Internet | CLA-521 |
| Módulos | `config/modules.php`, autoload `Modules\\` y nueve `Modules/*/composer.json` | aplicar la guía v13 y certificar discovery/autoload por módulo | CLA-517 |
| Activitylog | `ConsultationRequest` importa `LogOptions` y `LogsActivity` desde namespaces v4, y llama `dontSubmitEmptyLogs()`; existe `activity_log` con `batch_uuid` | adaptar namespaces/método; añadir `attribute_changes`, mover `attributes`/`old` desde `properties` y retirar `batch_uuid` solo tras verificar rollback/backup | CLA-526 |
| Permission | modelos Core extienden Spatie y el permiso está distribuido por varios módulos | revisar eventos/comandos renombrados, enums, cache y políticas | CLA-526 |
| Query Builder | uso de aplicación en `Website/Services/PortfolioService.php` | regresión de filtros, includes, orden y respuesta pública | CLA-526 |
| Mail | `Mail::extend()` en `MailingServiceProvider` | comprobar que el binding/extensión sigue resolviendo con el nuevo container | CLA-522 |
| Colas/scheduler | workers Redis y `schedule:work` son persistentes en producción | certificar payloads, excepciones/eventos y reinicio ordenado de workers | CLA-522 / CLA-523 |

Otros cambios de Laravel 13 que deben convertirse en checks aunque hoy no se detecte uso directo son: `upsert()` con `uniqueBy` no vacío, serialización de relaciones en collections, `Container::call()`, eventos `JobAttempted`/`QueueBusy`, timing del scheduler y serialización Unicode de `Js`.

## Evidencia de resolución aislada

La simulación se ejecutó copiando únicamente `composer.json` y `composer.lock` a `/tmp`. Los comandos tuvieron scripts deshabilitados y no reutilizaron ni modificaron `vendor/` del proyecto.

Restricciones aplicadas al sandbox:

```text
php ^8.4
laravel/framework ^13.0
laravel/tinker ^3.0
filament/filament ^5.7
filament/spatie-laravel-media-library-plugin ^5.7
nwidart/laravel-modules ^13.0
resend/resend-laravel ^1.4
spatie/laravel-activitylog ^5.0
spatie/laravel-permission ^8.0
spatie/laravel-query-builder ^7.0
spatie/laravel-translatable ^6.14
laravel/pail ^1.2.7
laravel/sail ^1.67
nunomaduro/collision ^8.9.5
phpunit/phpunit ^12.0
```

Resultados:

| Check | Resultado |
|---|---|
| `composer why-not laravel/framework ^13 --locked` sobre el lock actual | Detectó correctamente las restricciones incompatibles del baseline Laravel 12 |
| `composer update --dry-run --with-all-dependencies --no-scripts` en sandbox | PASS: grafo resoluble |
| actualización e instalación materializada en sandbox | PASS: 177 paquetes instalados |
| `composer why-not laravel/framework ^13 --locked` sobre el lock objetivo | PASS: Laravel 13.29.0 instalado, sin bloqueante restante |
| `composer validate --strict --no-check-publish` | PASS |
| `composer check-platform-reqs --lock` | PASS en PHP 8.4.24 |
| `composer audit` del lock objetivo | PASS: 0 advisories |
| `composer audit` del lock real Laravel 12 | PASS: 0 advisories |

El directorio temporal no es un artefacto entregable. CLA-519 debe repetir la operación desde una rama limpia y conservar juntos el manifest y el lockfile generados.

## Orden de ejecución y ownership

1. **Gate de integración:** integrar y cerrar los cambios Laravel 12 pendientes; congelar dependencias/bootstrap; actualizar la rama desde la base elegida y repetir el solver de CLA-518.
2. **CLA-519 — Core:** modificar restricciones y lockfile en una sola operación, adoptar cambios del skeleton, CSRF/cache/sesión y realizar smoke tests de arranque.
3. **CLA-516 y CLA-517 — UI/módulos:** certificar Filament/media y Laravel Modules sobre el nuevo núcleo.
4. **CLA-526 — Spatie/datos:** aplicar majors de Permission, Query Builder y Activitylog; ejecutar una migración reversible y verificada para `activity_log`.
5. **CLA-521 y CLA-522 — comportamiento operativo:** auth mult-dominio/LAN/túnel, sesiones, Sanctum, Microsoft, mail, Redis, colas, cache y scheduler.
6. **CLA-524 — suite:** subir a PHPUnit 12, eliminar incompatibilidades y medir el diferencial contra el baseline conocido.
7. **CLA-525 — CI/staging:** certificar PHP 8.4, instalación limpia, build, tests, E2E y observación fuera de producción.
8. **CLA-523 — producción:** ejecutar despliegue, observabilidad y rollback probado. No reemplazar el health check LAN fallido exponiendo el backoffice públicamente.

Cada ticket debe mantener su alcance. En particular, CLA-519 no debe esconder la migración destructiva de Activitylog dentro de una actualización masiva sin pruebas y backup.

## Estrategia de rollback

Antes del corte de CLA-523:

- etiquetar y conservar el último commit desplegable con Laravel 12;
- respaldar la base, con verificación específica de `activity_log` antes de su transformación;
- conservar `composer.json` y `composer.lock` como pares inseparables para Laravel 12 y Laravel 13;
- drenar o detener workers y evitar jobs con payload incompatible durante la ventana;
- decidir conscientemente la compatibilidad de sesiones, cache y payloads antes de reanudarlos.

Si el rollback es necesario, desplegar el commit Laravel 12 y ejecutar `composer install` desde su lockfile; reiniciar PHP-FPM, workers y scheduler. No arrancar Laravel 12 contra una base ya transformada por Activitylog 5 sin haber probado primero el `down()` o restaurado el backup. No reutilizar caches, sesiones ni jobs serializados si la certificación demuestra incompatibilidad.

El rollback operativo se prueba en staging en CLA-525 y se ejecuta, si fuera necesario, únicamente bajo CLA-523.

## Gate de revalidación para CLA-519

CLA-519 no recibe GO de implementación hasta que una revisión sobre la rama base final confirme:

- `git diff` de `composer.json` y `composer.lock` desde `dea0d19`, incluyendo todos los merges Laravel 12;
- nueva salida de `composer why-not laravel/framework ^13 --locked`;
- nueva resolución completa con `--with-all-dependencies`, sin desactivar auditoría de seguridad;
- PHP 8.4 en desarrollo, CI, staging, FPM, CLI, workers y scheduler;
- plan aprobado de migración/rollback de Activitylog;
- responsables y tests asignados a CLA-516, CLA-517, CLA-521, CLA-522, CLA-524, CLA-525 y CLA-526.

Un cambio posterior en dependencias, `bootstrap/app.php`, autenticación, módulos o Activitylog invalida esta aprobación y obliga a repetir el gate.

## Fuentes primarias

- [Laravel 13 upgrade guide](https://laravel.com/framework/docs/13.x/upgrade)
- [Filament 5 upgrade guide](https://filamentphp.com/docs/5.x/upgrade-guide)
- [Laravel Modules 13 upgrade guide](https://laravelmodules.com/docs/13/getting-started/upgrade)
- [Laravel Modules package](https://packagist.org/packages/nwidart/laravel-modules)
- [Spatie Activitylog upgrade guide](https://github.com/spatie/laravel-activitylog/blob/main/UPGRADING.md)
- [Spatie Activitylog package](https://packagist.org/packages/spatie/laravel-activitylog)
- [Spatie Permission changelog](https://github.com/spatie/laravel-permission/blob/main/CHANGELOG.md)
- [Spatie Permission package](https://packagist.org/packages/spatie/laravel-permission)
- [Spatie Query Builder changelog](https://github.com/spatie/laravel-query-builder/blob/main/CHANGELOG.md)
- [PHPUnit 12.5 manual](https://docs.phpunit.de/en/12.5/)

## Test Gate de CLA-518

**WAIVER — CLA-518**

- Motivo: ticket exclusivamente diagnóstico/documental; no modifica aplicación, dependencias reales, lockfile ni base de datos.
- Riesgo residual: el solver no demuestra compatibilidad de runtime y cualquier merge posterior sobre Laravel 12 puede invalidar la matriz.
- Cobertura alternativa: `why-not` sobre lock actual y objetivo, dry-run, lock e instalación materializados en sandbox, validación estricta, requisitos de plataforma y auditoría de seguridad.
- Aprobación: GO técnico del usuario recibido el 2026-08-30.
