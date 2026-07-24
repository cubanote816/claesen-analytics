# Production Readiness — CAFCA Intelligence Hub

> Checklist para staging y producción. Ejecutar en orden antes de cualquier deploy.
> Última actualización: 2026-06-02 (DOCS-AI-001 / CLA-105)

---

## Criterios para NO desplegar

Detener el deploy si cualquiera de estas condiciones se cumple:

- [ ] **Test Gate no pasado** — falta tests requeridos o waiver documentado (ver `test-gate-harness.md`)
- [ ] Tests de la suite relevante fallan
- [ ] Hay migraciones sin ejecutar en staging que están en `main`
- [ ] El scheduler no está activo en producción
- [ ] Variables de entorno requeridas no están configuradas
- [ ] Existe una migración `down()` sin probar si el rollback es necesario
- [ ] Hay jobs en cola fallando sin investigar
- [ ] El commit no tiene GO técnico del auditor

---

## Checklist general de deploy

### Pre-deploy

- [ ] Backup de la base de datos MySQL antes de cualquier migración
- [ ] Revisar `git log main..HEAD` — confirmar que no hay cambios no intencionados
- [ ] `php artisan test --testsuite=Modules` pasa en staging
- [ ] Variables de entorno del módulo afectado configuradas en staging/producción
- [ ] Comunicar ventana de mantenimiento si la migración es destructiva

### Migraciones

```bash
# Ver qué migraciones están pendientes
php artisan migrate:status

# Ejecutar migraciones (staging primero, producción después)
php artisan migrate --force

# Verificar que no hay errores
php artisan migrate:status
```

- [ ] Ejecutar en staging y verificar sin errores
- [ ] Probar rollback en staging si hay duda: `php artisan migrate:rollback`
- [ ] Ejecutar en producción solo tras validación en staging

### Post-deploy

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl reload php8.4-fpm   # obligatorio — ver nota opcache abajo
```

- [ ] Reiniciar queue workers después de deploy
- [ ] Verificar que el scheduler sigue activo: `php artisan schedule:list`
- [ ] Smoke tests de los endpoints afectados

**opcache.validate_timestamps=0 en prod-priv-01** (`/etc/php/8.4/fpm/conf.d/10-opcache-prod.ini`): PHP-FPM nunca vuelve a leer archivos en disco por su cuenta. `config:cache` reescribe el archivo pero los workers siguen sirviendo el bytecode viejo hasta un `systemctl reload php8.4-fpm`. `infrastructure/scripts/deploy.sh` ya hace este reload (paso 9) para deploys completos — pero si editás `shared/.env` a mano **sin** pasar por deploy.sh, correr `infrastructure/scripts/reload-config.sh` (nuevo, ver `commands-runbook.md`) o el reload manual. Sin esto, un cambio de `.env` puede parecer aplicado (el archivo cacheado ya tiene el valor nuevo) pero el tráfico real sigue viendo el valor viejo — así se manifestó CLA-232 (login de Azure roto por `SESSION_DOMAIN` desincronizado).

---

## Checklist específico — Módulo Mailing

### Migraciones Fase 2 (ya aplicadas si estás en `main`)

```bash
# Tablas Fase 2:
# mailing_campaigns: audience_type, audience_filters, scheduled_at, timezone
# mailing_campaigns: ab_subject_b, ab_split_percent, ab_winner_*, ab_test_started_at
# mailing_campaigns: followup_campaign_id, followup_trigger, followup_delay_hours, followup_dispatched_at
# mailing_messages: ab_variant
# mailing_contact_preferences (tabla nueva)
# mailing_deliverability_alerts (tabla nueva)
php artisan migrate --force
```

### Variables de entorno requeridas

```env
MAILING_NDR_INBOX=<inbox para NDR bounces>
MAILING_SEND_DELAY_MS=<delay en ms entre envíos, ej: 500>
MAILING_UNSUBSCRIBE_DOMAIN=<dominio para links de baja>
MAILING_TRACKING_DOMAIN=<dominio para tracking, por defecto APP_URL>
```

### Scheduler (debe estar en crontab)

```bash
# Añadir al crontab del servidor
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

Commands programados de Mailing:
- `mailing:dispatch-scheduled` — despacha campañas programadas
- `mailing:parse-bounces` — parsea NDR del inbox
- `mailing:ab-select-winner` — selecciona ganador A/B
- `mailing:dispatch-followups` — despacha follow-ups
- `mailing:check-deliverability-alerts` — verifica umbrales

### Queue workers

```bash
# Verificar que los workers están activos
php artisan queue:work --queue=default,mailing

# O via Supervisor (recomendado en producción)
supervisorctl status
```

### Smoke tests de Mailing en staging

```bash
# 1. Comandos con dry-run
php artisan mailing:parse-bounces --dry-run
php artisan mailing:dispatch-scheduled --dry-run
php artisan mailing:ab-select-winner --dry-run
php artisan mailing:dispatch-followups --dry-run
php artisan mailing:check-deliverability-alerts --dry-run

# 2. Tests de la suite
php artisan test --testsuite=Modules --filter=Mailing
```

### Validación funcional en staging

- [ ] Crear campaña interna (a email de prueba)
- [ ] Verificar header `List-Unsubscribe` en correo recibido
- [ ] Verificar header `X-Mailing-Token` en correo recibido
- [ ] Verificar que el pixel de apertura responde 200 (GIF 1x1)
- [ ] Verificar que el click redirect funciona y registra evento
- [ ] Probar flujo de baja via link en el correo
- [ ] Verificar que la baja aparece en `mailing_suppression_list`
- [ ] Probar página de preferencias de categoría
- [ ] Verificar que scheduled campaigns se despachan con `mailing:dispatch-scheduled`
- [ ] Verificar que las alertas de entregabilidad se generan si se supera umbral

---

## Checklist específico — Módulo Safety

### Variables de entorno

```env
# Safety usa disco local (ya configurado en config/safety.php)
# No hay variables de entorno adicionales requeridas más allá de las de Laravel
```

### Validación funcional en staging

- [ ] `GET /v1/login` con credenciales válidas retorna token Sanctum
- [ ] `GET /v1/safety/checklists` con token válido retorna checklists
- [ ] `POST /v1/safety/inspections` crea inspección correctamente
- [ ] `GET /v1/safety/inspections/{id}/pdf` descarga PDF
- [ ] `GET /v1/safety/compliance` retorna estado de compliance
- [ ] Verificar que `project_manager` no puede ver inspecciones de otros usuarios

---

## Checklist específico — Módulo Website

### Variables de entorno requeridas

```env
GITHUB_TOKEN=<token con permisos repo dispatch>
GITHUB_REPO=<owner/repo del frontend Astro>
```

### Backfill de media (solo primera vez o si cambian conversiones)

```bash
php artisan website:regenerate-media
# Opciones disponibles:
php artisan website:regenerate-media --collection=gallery
php artisan website:regenerate-media --collection=featured_image
php artisan website:regenerate-media --project=<id>
```

### Validación funcional en staging

- [ ] `GET /v1/website/projects` retorna solo proyectos `published = true`
- [ ] `GET /v1/website/projects/{slug}` retorna detalle con URLs WebP
- [ ] `POST /v1/website/consultations` crea consulta
- [ ] Editar un proyecto en Filament → verificar que `NotifyAstroFrontendJob` se despacha
- [ ] Verificar URLs de imagen tienen conversión `.webp`

---

## Checklist específico — Módulo Performance

### Scheduler

```bash
# Reporte Watchdog: lunes por la mañana → orelvys.cuellar@claesen-verlichting.be
php artisan schedule:list | grep watchdog
```

### Smoke tests

```bash
php artisan performance:sync-all --dry-run  # si soporta
php artisan performance:populate-project-insights --limit=1
```

---

## Checklist específico — Claesen-Client (portal cliente, FieldOps Fase 5)

> Hostname objetivo: `client.claesen-verlichting.be` (documentado en `docs/ai/known-risks.md` y `docs/ai/fieldops-maintenance-roadmap.md`, todavía sin provisionar). Este checklist requiere acceso SSH a `sbapu03` (nginx edge) y `prod-priv-01` (backend), y acceso al proveedor de DNS — ninguno disponible desde un checkout de este repo. Los artefactos de `infrastructure/nginx/sbapu03/client.claesen-verlichting.be.conf` y `cors-map.conf` ya están preparados en el repo; este runbook es la guía para aplicarlos.

### 1. DNS

- [ ] Crear registro `A`/`AAAA` (o `CNAME`) para `client.claesen-verlichting.be` apuntando a la IP pública de `sbapu03`, igual que `service.`/`safety.`/`fieldops.claesen-verlichting.be`.
- [ ] Confirmar propagación: `dig +short client.claesen-verlichting.be`.

### 2. TLS (Let's Encrypt, en `sbapu03`)

```bash
# Requiere que el DNS ya resuelva y el vhost HTTP (puerto 80) del paso 3 exista
# para servir el ACME challenge en /.well-known/acme-challenge/.
sudo certbot certonly --nginx -d client.claesen-verlichting.be
```

### 3. Nginx (en `sbapu03`)

```bash
# Copiar el vhost preparado en este repo:
sudo cp infrastructure/nginx/sbapu03/client.claesen-verlichting.be.conf /etc/nginx/sites-available/
sudo ln -s /etc/nginx/sites-available/client.claesen-verlichting.be.conf /etc/nginx/sites-enabled/

# Aplicar la entrada nueva de cors-map.conf (agrega client.claesen-verlichting.be
# al allowlist compartido con service./safety./fieldops.claesen-verlichting.be):
sudo cp infrastructure/nginx/sbapu03/cors-map.conf /etc/nginx/conf.d/  # o la ruta real del include existente

sudo nginx -t && sudo systemctl reload nginx
```

- [ ] Crear el directorio de destino del build estático: `sudo mkdir -p /var/www/client.claesen-verlichting.be/public`.
- [ ] **No existe todavía pipeline de CI/CD para Claesen-Client** (a diferencia del módulo Website, que sí tiene GitHub Actions + webhook). Hasta que se decida uno, desplegar el build manualmente:
  ```bash
  # Desde /home/totti/Claesen-Client
  npm run build
  rsync -avz --delete dist/ usuario@sbapu03:/var/www/client.claesen-verlichting.be/public/
  ```

### 4. Backend (`.env` en `prod-priv-01`, vía `shared/.env`)

```bash
CLIENT_PORTAL_URL=https://client.claesen-verlichting.be
# CLIENT_PORTAL_URL se agrega automáticamente a SANCTUM_STATEFUL_DOMAINS y a
# config/cors.php — no hace falta listarlo también a mano en SANCTUM_STATEFUL_DOMAINS.
```

Aplicar sin un deploy completo (regla de `CLAUDE.md`, nunca solo `config:cache` a mano):

```bash
infrastructure/scripts/reload-config.sh
```

- [ ] **No se necesita tocar** `Modules/Core/Http/Middleware/ResolveSessionCookieDomain.php` — ya es agnóstico de subdominio (`*.claesen-verlichting.be`, resuelve por Origin/Referer, no por Host).

### 5. Verificación funcional

```bash
# TLS y vhost sirven el SPA:
curl -sI https://client.claesen-verlichting.be/ | head -5

# CORS: el origin del portal debe reflejarse (no la lista default vacía):
curl -sI -H "Origin: https://client.claesen-verlichting.be" \
  https://backend.claesen-verlichting.be/api/v1/me | grep -i access-control-allow-origin
```

- [ ] Login OAuth desde `https://client.claesen-verlichting.be` completa el flujo y la cookie de sesión persiste entre navegaciones (confirma `ResolveSessionCookieDomain` + `SANCTUM_STATEFUL_DOMAINS` en conjunto).
- [ ] `VITE_DATA_MODE=api` y `VITE_API_BASE_URL=https://backend.claesen-verlichting.be` configurados en el build de producción de Claesen-Client (no el mock mode usado en dev/demos).

### Pendiente de decisión (no bloquea este runbook, pero sí producción real)

- Pipeline de CI/CD de Claesen-Client (mismo patrón que Website: build + sync automático en vez de `rsync` manual).
- Monitorización/alertas y métricas de adopción (ítems finales del checklist de Fase 5, todavía sin empezar).

---

## Rollback

Si el deploy falla después de migrar:

```bash
# Rollback de última migración
php artisan migrate:rollback

# Rollback a un batch específico
php artisan migrate:rollback --batch=N

# Verificar estado
php artisan migrate:status
```

**Importante:** el rollback no restaura datos eliminados por la migración. El backup previo es la única garantía real.
