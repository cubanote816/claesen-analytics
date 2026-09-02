# Laravel 13 — Certificación de staging y ensayo de rollback (CLA-525 / CLA-523)

> Checklist de ejecución para el candidato Laravel 13. Requiere un entorno
> equivalente a producción (workers + scheduler) y acceso SSH real. Los pasos de
> este documento son el trabajo que todos los "WAIVER diferencial" de la cadena
> CLA-519 → CLA-524 difirieron a este ticket.
>
> Rama candidata: `release/laravel-13-rc1` (en `origin`). Base técnica certificada durante
> FASE 0: `c6a5e89`. **El deploy debe usar el head más reciente de `release/laravel-13-rc1`
> con CI verde**, no un commit fijo. Consolida la cadena completa `cla-519 → … → cla-529`
> (en la base `c6a5e89`: 24 commits sobre `origin/main`, 0 merges — el conteo se mueve con
> cada commit nuevo). Draft **PR #9** contra `main` — NO mergear
> hasta cerrar CLA-525 y CLA-523.
>
> **Prerrequisitos de infraestructura:** no existe un entorno de staging Laravel. Este
> checklist no puede iniciarse hasta que:
> - **CLA-530** (aprovisionar staging separado de producción) esté **cerrado**;
> - exista una **implementación candidata de CLA-531** (pipeline de deploy endurecido +
>   rollback automático) desplegable en staging.
>
> **CLA-531 se valida durante el deploy y el ensayo de rollback de este checklist (§1, §5,
> §6) y debe cerrarse antes que CLA-525.** No usar `prod-priv-01` como staging.

---

## 0. Prerrequisitos

- [ ] CI de tests (`.github/workflows/tests.yml`) **verde** sobre el head exacto de `release/laravel-13-rc1`. Gate vigente: **`Static checks` `success` · `Build front-end assets` `success` · `PHPUnit (PHP 8.4)` `1322 passed / 0 failed / 0 errors / 2 skipped` (4285 assertions)** — resultado exacto; cualquier desviación es bloqueante. Confirmado en runs `33512861220` (head `4d0e32c`), `33526166018` (head `640242c`) y `33552786969` (head `c6a5e89`).
  - Las 4 familias de fallos que el baseline CLA-520 (1086/186/2) toleraba **ya no existen**: Mailing dispatch (`AbTestingTest`/`DispatchScheduledTest`/`FollowUpTest`) → CLA-529; `RoleAlreadyExists` por estado compartido → CLA-528; `MicrosoftAuthRedirectTest::test_module_config_merge_is_skipped_when_config_is_cached` → CLA-528; cascada `migrate:rollback` de Website → CLA-528 (`DatabaseTruncation`) + `f876942` (CLA-523 parcial).
  - Si CI vuelve a mostrar cualquier fallo o error, es un bloqueante — no hay "familias toleradas".
- [ ] Backup de MySQL de staging tomado y verificado restaurable (`/opt/claesen/scripts/backup-mysql.sh`).
- [ ] Anotar el release actual (`readlink /srv/www/claesen/current`) y su `composer.lock` — es el destino del rollback.
- [ ] Confirmar en staging: `php -v` = 8.4.x, `php -m | grep -i imagick` presente, `composer -V` = 2.10.x, `systemctl is-active php8.4-fpm`, `supervisorctl status claesen-worker:*` y `claesen-scheduler` en RUNNING.
- [ ] Sesiones activas de prueba abiertas **antes** del deploy (Filament + Safety PWA + FieldOps) para verificar que sobreviven el cambio de `session.serialization` (sigue en `'php'`, CLA-519 — no deberían cerrarse).

## 1. Desplegar el candidato en staging

Mismo flujo que `infrastructure/scripts/deploy.sh` (backup → `down` → clone → shared links → `composer install --no-dev` → `npm ci && npm run build` → `php artisan migrate --force` → `optimize:clear` → `filament:upgrade` → `optimize` → `storage:link` → permisos → `config:cache` → symlink → `reload php8.4-fpm` → `queue:restart` + supervisor → `up`).

- [ ] `composer install` resuelve sin conflictos (177 paquetes, mismos que el sandbox de CLA-518).
- [ ] `php artisan migrate --force` aplica limpio. Confirmar en particular:
  - `2026_08_30_100000_upgrade_activity_log_table_to_activitylog_v5` → `activity_log` gana `attribute_changes`, pierde `batch_uuid`. **Si `activity_log` en producción tiene muchas filas**, el backfill `eachById` puede tardar — cronometrar y decidir si se hace en ventana de mantenimiento (CLA-526).
  - Las 3 migraciones históricas `2026_02_09_14034*` no fallan (fallback `config('activitylog.table_name', 'activity_log')`, CLA-526).
- [ ] `php artisan about` → Laravel 13.29.0 / PHP 8.4.x.
- [ ] `php artisan schedule:list` → una sola entrada por tarea, `next due` coherente.
- [ ] Workers y scheduler vuelven a RUNNING tras `queue:restart` + `supervisorctl restart`.

## 2. E2E por rol (matriz RBAC + tenant isolation)

Para cada rol, login real y recorrer el flujo. Marcar OK / anomalía + evidencia (screenshot / request-id).

| Flujo | super_admin | admin | project_manager | technician | viewer | client |
|---|---|---|---|---|---|---|
| Login al panel Filament (`backoffice.claesen.local`) | ✅ | ✅ | ❌ redirige a `/auth/no-access` | ❌ | ✅ | ❌ |
| Login Azure OAuth (round-trip completo, sin doble cookie `laravel_session`) | ✅ | ✅ | ✅ (welcome page) | — | ✅ | — |
| Login Safety PWA / FieldOps / Client Portal según corresponda | — | — | PWA | PWA | — | Client Portal |
| CRUD de un recurso Filament (crear + editar + borrar) | ✅ | ✅ | — | — | ❌ solo lectura | — |
| Subida de media privada (foto/documento a Complex/Terrain/Structure/Luminaire) + descarga + borrado | ✅ | ✅ | — | vía PWA | — | — |
| Generación de PDF con membrete (`core::pdf.letterhead`) | ✅ | ✅ | — | — | ✅ | — |
| Envío de email real (campaña Mailing o notificación FieldOps) con logo embebido `cid:` | ✅ | ✅ | — | — | — | — |
| Llamada IA (Gemini traducción / Watchdog / identificación visual Claude) | ✅ | ✅ | — | — | — | — |
| Sync CAFCA (`intelligence:sync-mirror` + `fieldops:sync-clients-from-relations` + `...-complexes-from-relation-deliveries`) | ✅ | — | — | — | — | — |
| Aislamiento tenant FieldOps (un `client` solo ve sus complejos/terrenos/estructuras/media/histórico) | — | — | — | — | — | ✅ fail-closed |
| Sesión expirada (419) → modal branded, no `confirm()` nativo (CLA-208) | ✅ | ✅ | — | — | ✅ | — |
| Rankings de empleados (widget + dashboard) — **cache `database` con `serializable_classes => false`** (CLA-522) | ✅ | ✅ | — | — | ✅ | — |
| Portfolio Website: filtro por categoría + traducciones nl/en/fr (Query Builder 7, CLA-526) | público | | | | | |

## 3. Subsistemas

- [ ] **Cache:** `php artisan cache:clear` y volver a poblar; verificar que el widget de rankings y `BiConfigService` sirven datos correctos desde `database` store.
- [ ] **Queues:** encolar un job real (p.ej. una campaña Mailing o `TranslateModelAttributesJob`), verificar que un worker lo procesa y que un fallo controlado va a `failed_jobs` sin romper el worker.
- [ ] **Scheduler:** dejar correr un tick; verificar `withoutOverlapping` (locks) y que no hay doble ejecución.
- [ ] **Mail:** `MicrosoftGraphTransport` — adjunto inline (`isInline` + `contentId`) llega como imagen, no como adjunto suelto (CLA-234).
- [ ] **DB SQL Server ReadOnly:** intentar (en tinker de staging) un `save()`/`update()` sobre un modelo Cafca → debe lanzar `LogicException` (`ReadOnlyTrait`). Ningún flujo escribe en `sqlsrv`.
- [ ] **CSRF / Sanctum multidominio (CLA-521):** desde cada frontend real (Filament, Safety, FieldOps, Client Portal) hacer un POST autenticado; verificar 200, cookie de sesión con el `Domain` correcto (host exacto para Filament/localhost, `.claesen-verlichting.be` para los SPA de producción), y que un POST cross-site sin token sigue devolviendo 419.

## 4. Observación (dejar rodar ≥ 30 min con tráfico de prueba)

- [ ] `storage/logs/laravel.log` — **cero** `error`/`critical` nuevos. (Ojo `LOG_LEVEL` — ver `project_safety_mirror_incident`: `error` puede ocultar warnings; bajar a `warning` durante la ventana.)
- [ ] Tiempos de respuesta de las páginas Filament pesadas (mapas FieldOps, dashboards) — comparar con el release anterior.
- [ ] `SHOW PROCESSLIST` / slow query log — sin queries nuevas lentas por el cambio de Eloquent/Activitylog 5.
- [ ] Consumo de memoria/CPU de los workers estable.

## 5. Ensayo de rollback (CLA-523)

Objetivo: volver al release anterior recuperando aplicación, workers y lockfile **sin pérdida de datos**.

- [ ] `PREV=$(ls -dt /srv/www/claesen/releases/*/ | sed -n '2p')` — el release inmediatamente anterior (KEEP_RELEASES=3).
- [ ] `php /srv/www/claesen/current/artisan down --refresh=15`
- [ ] **Datos:** las migraciones de Laravel 13 son aditivas salvo `activity_log` (drop de `batch_uuid`). Decidir política:
  - Opción A (recomendada para el ensayo): **no** revertir migraciones — el schema L13 es compatible hacia atrás con el código L12 para todo salvo `activity_log`. Restaurar solo el código.
  - Opción B (rollback total): `php artisan migrate:rollback --step=N --force` hasta antes de `2026_08_30_100000`; la migración v5 tiene `down()` reversible real (re-crea `batch_uuid`, funde `attribute_changes` de vuelta en `properties`) — verificado en CLA-526. Cronometrar el `eachById` inverso.
- [ ] `sudo rm -f /srv/www/claesen/current && sudo ln -s "$PREV" /srv/www/claesen/current`
- [ ] `cd "$PREV" && composer install --no-dev --optimize-autoloader` (restaura el `vendor/` del lockfile L12 anterior).
- [ ] `sudo -u www-data php "$PREV/artisan" config:cache`
- [ ] `sudo systemctl reload php8.4-fpm` (opcache `validate_timestamps=0`, CLA-232 — sin esto sigue el bytecode nuevo).
- [ ] `php "$PREV/artisan" queue:restart && sudo supervisorctl restart claesen-worker:* claesen-scheduler`
- [ ] `php "$PREV/artisan" up`
- [ ] **Verificar:** `php artisan about` → Laravel 12.x; login funciona; sesiones que estaban abiertas antes del rollback siguen válidas (serialización `php` en ambos lados); un job de cola se procesa; `schedule:list` OK; backup restaurable no necesitó usarse.
- [ ] Documentar tiempo total de rollback y cualquier paso manual no cubierto por `deploy.sh`.

## 6. Health check del workflow (CLA-523)

El paso "Verify deployment" de `.github/workflows/deploy.yml` hace `curl https://backoffice.claesen.local/` y acepta 200/302. Desde el runner de `prod` esto puede dar **curl exit 7** (sin listener en esa ruta/puerto desde el host — CLA-515). Rediseñar:

- [ ] Reemplazar por un check que corra **en el servidor** tras el symlink: `sudo -u www-data php /srv/www/claesen/current/artisan about` + un `curl` local (`http://127.0.0.1` con `Host: backoffice.claesen.local`) al endpoint de salud `/up`, no al dominio público.
- [ ] Añadir verificación de workers/scheduler (`supervisorctl status`) al paso de health check.
- [ ] Fallar el workflow (y disparar el rollback automático) si `/up` no responde 200 o si algún worker no está RUNNING.

---

## Mapeo a criterios de aceptación

| Criterio CLA-525 | Sección |
|---|---|
| Checklist de producción completado con evidencias | Todo el documento, marcado con evidencia |
| Sin errores nuevos de severidad alta | §4 (logs), §0 (delta de CI vs baseline) |
| Matriz RBAC y tenant isolation validada | §2 |
| Sesiones existentes y nuevas siguen el comportamiento aprobado | §0 (pre-deploy), §2 (419 modal), §5 (post-rollback) |
| Rollback recupera aplicación, workers y lockfile sin pérdida de datos | §5 |
