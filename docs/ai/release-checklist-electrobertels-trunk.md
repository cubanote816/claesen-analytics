# Checklist de release — `electrobertels/trunk` → `main` (producción)

> Release del programa multiempresa Electro Bertels + sección web de Bertels + rediseño de auth + upgrade de dependencias, con KNX apagado.
> Evidencia y decisiones: `odd/tasks/release-trunk-to-main.md` (L13 hechos de producción, L16 ensayo de migraciones). PR: #29.
> **Un push a `main` dispara el deploy** (`.github/workflows/deploy.yml` → `/opt/claesen/scripts/deploy.sh` en `prod-priv-01`, `192.168.254.106`). La app vive en `/srv/www/claesen`.

## Qué cambia en producción

- 56 migraciones nuevas sobre las 183 actuales (ninguna existente se modifica ni se borra). Ensayadas sobre una copia de producción del 2026-10-25: ~6 s, sin errores.
- Multiempresa con `ORGANIZATIONS_ENFORCE` **apagado** (no definido en el `.env` → `false`): Claesen se comporta como hoy.
- KNX **apagado** (`KNX_ENABLED` no definido → `false`): link oculto y todas sus rutas responden 404. Sus tablas se crean vacías.
- **MFA obligatorio para `super_admin` y `admin` que entran con email + contraseña local** (decisión del usuario 2026-10-25, ADR D8): en el primer acceso con contraseña tras el deploy, el admin tiene que configurar código por mail o app autenticadora antes de usar el panel. **Quien entra con "Sign in with Microsoft" no lo configura**: el segundo factor lo pone Entra ID (S2c, `b2b4f8c`). No se puede apagar por `.env`.
- Tres tareas programadas nuevas: `website:retry-failed-consultation-emails` (cada 15 min, envía mail), `website:apply-retention-policy` (05:00, inerte mientras `WEBSITE_RETENTION_ENABLED` no esté en `true`), `website:check-intake-abuse-alerts` (cada hora).
- Upgrade de dependencias (Filament 5.10.3, Laravel 13.38, Livewire 4.4.8, Symfony 8.1.9): `composer audit` limpio.

## Antes del merge

- [ ] **CI verde en el PR #29** sobre el commit que se va a mergear.
- [ ] **Backup manual verificado**, independiente del paso 0 de `deploy.sh` (ese paso continúa aunque el backup falle): `mysqldump --single-transaction` de `claesen_oficial` + `gzip -t` + sha256, guardado fuera del directorio de releases. El dump del 2026-10-25 sirve de referencia pero se rehace el mismo día del deploy.
- [ ] **Backup de archivos de media** (`storage/app/public` y `storage/app/private` de `/srv/www/claesen/shared`): `website:migrate-media-to-private-disk` mueve 109 originales.
- [ ] **Avisar a los admins** que, si entran con email + contraseña, en el primer acceso se les va a pedir configurar MFA; con "Sign in with Microsoft" no.
- [ ] **Confirmar en Entra ID** que Conditional Access o Security Defaults exigen MFA a las cuentas de `claesen-verlichting.be` y `electrobertels.be` (la exención de S2c se apoya en eso). *Parcial 2026-10-26:* log de una cuenta admin con "Multifactor authentication", políticas "Per-user MFA, Security Defaults" (L23). Falta: per-user MFA *Enabled/Enforced* en el resto de los admins del backoffice y confirmar que el log es de la app del backoffice.
- [x] **Mail de producción funcionando** (el código MFA por mail sale por el mailer por defecto, a través de la cola). *Confirmado por el usuario 2026-10-26* (sin comprobación técnica del worker de la cola ni de `failed_jobs`). Si algún admin con contraseña no recibe el código, que elija la app autenticadora.
- [ ] Confirmar en `shared/.env` (sin mostrar valores): `KNX_ENABLED` y `ORGANIZATIONS_ENFORCE` **no** están definidos o están en `false`; `WEBSITE_RETENTION_ENABLED` no está en `true`. Confirmado el 2026-10-25 para los dos primeros (L13).
- [ ] Ventana de deploy fuera del horario de uso (el deploy entra en modo mantenimiento).

## Durante el deploy (automático, `deploy.sh`)

0 backup · 1 mantenimiento · 2 clone · 3 shared · 4 `composer install --no-dev` · 5 `npm ci && npm run build` · 6 `migrate --force` · 7 `optimize:clear`, `filament:upgrade`, `optimize`, `storage:link` · 8 permisos + `config:cache` · 9 activar release + `reload php8.4-fpm` + reinicio de workers y scheduler · 10 salir de mantenimiento.

- [ ] Seguir el job "Deploy to prod-priv-01" en Actions. **El paso "Verify deployment" falla desde el 2026-09-22 aunque el deploy llegue** (falso negativo, ver "Pendiente"): el criterio de éxito es que el paso `Deploy` termine bien y el commit desplegado sea el mergeado.

## Inmediatamente después

En `/srv/www/claesen/current`, como el usuario que corre la app:

- [ ] `php artisan migrate:status` → 0 Pending y 239 Ran.
- [ ] `php artisan core:backfill-user-organizations --dry-run` y después `--apply` → 0 usuarios sin organización (las migraciones dejan `users.organization_id` en NULL; es idempotente).
- [ ] `php artisan website:migrate-media-to-private-disk --dry-run` → revisar el conteo (≈109) y después correrlo sin `--dry-run`. Es idempotente.
- [ ] `git -C /srv/www/claesen/current log -1 --format='%h %s'` → el commit mergeado.

## Verificación

- [ ] Login de un admin con email + contraseña → pide configurar MFA → configurarlo → entra al panel.
- [ ] Login con Microsoft de un admin de Claesen → entra al panel **sin** pedir configurar MFA.
- [ ] Dashboard, una lista de proyectos de la web, una consulta y una campaña cargan sin error.
- [ ] La web pública de Claesen sigue mostrando proyectos e imágenes (después de mover la media).
- [ ] `/api/v1/knx/me/session` → 404 y `/bertels/knx` → 404 (KNX apagado).
- [ ] `php artisan schedule:list` muestra las tres tareas nuevas.
- [ ] Logs (`storage/logs/laravel.log`) sin errores nuevos en los primeros 30 minutos.

## Rollback

- **Antes de dar de alta a Bertels:** volver al release anterior (symlink `current` al release previo + `reload php8.4-fpm`) y `php artisan migrate:rollback --step=56 --force` (ensayado: vuelve limpio a 183). Ojo: `remap_consultation_request_statuses_to_lead_workflow` pierde `cancelled` al volver, y los datos escritos después del deploy en tablas o columnas nuevas se pierden.
- **Bertels quedó dado de alta el 2026-10-27** (organización id 2, sitio id 2). Desde ahí rige lo siguiente.
- **Después de dar de alta a Bertels:** `migrate:rollback` **falla** a mitad (`create_organizations_and_sites_tables.php:60`) y deja el esquema a medio volver. El rollback es **restaurar el backup previo al deploy** (o el previo al alta: `/opt/claesen/backups/prod-db-pre-bertels-20261027T144502Z.sql.gz`).

## Fuera de este release (pendiente)

- **Paso "Verify deployment" del deploy:** hipótesis sin verificar — en el servidor `backoffice.claesen.local` todavía resuelve a la IP vieja `192.168.254.52`. Comprobar con `getent hosts backoffice.claesen.local`.
- **Alta de Bertels (P7):** organización y sitio a mano, `mail_from_address`/`mail_from_name`/`mail_notification_email` (las migraciones de remitente de Bertels no encuentran la fila y no hacen nada), grupo de Azure, Application Access Policy de Graph, SPF/DKIM de `electrobertels.be`, webhook de publicación por sitio, `NOT NULL` y `ORGANIZATIONS_ENFORCE`.
- **CLA-577:** `ANTHROPIC_API_KEY` sigue con valor: las fotos de los técnicos van a la API directa de Anthropic.
- **Destinatario provisional:** `set_site_lead_notification_recipients` pone en Claesen una dirección marcada como provisional en la propia migración.
- **Bug de horario de verano** en timestamps locales (`docs/ai/known-risks.md`).
