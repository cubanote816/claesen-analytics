# Commands Runbook — CAFCA Intelligence Hub

> Todos los comandos Artisan del proyecto con descripción, opciones y notas operativas.
> Última actualización: 2026-06-02 (DOCS-AI-001 / CLA-105)

---

## Tests

```bash
# Suite completa
php artisan test

# Solo módulos
php artisan test --testsuite=Modules

# Módulo específico
php artisan test --testsuite=Modules --filter=Mailing
php artisan test --testsuite=Modules --filter=Safety
php artisan test --testsuite=Modules --filter=Intelligence
php artisan test --testsuite=Modules --filter=Performance
php artisan test --testsuite=Modules --filter=Prospects
php artisan test --testsuite=Modules --filter=Website
php artisan test --testsuite=Modules --filter=Cafca

# Archivo concreto
php artisan test Modules/Mailing/tests/Feature/CampaignWorkflowTest.php
```

---

## E2E (Playwright)

Specs en `tests/e2e/` (fuera de las suites de PHPUnit). Playwright **no** levanta la app: debe estar corriendo con Sail (por defecto `http://localhost:8001`).

```bash
# Instalación (una vez)
npm ci
npx playwright install chromium

# Ejecutar
npm run test:e2e
E2E_BASE_URL=http://localhost:8001 npm run test:e2e   # otra URL base
npx playwright test --ui                              # modo interactivo
```

Reporte HTML en `playwright-report/` (`npx playwright show-report`); trazas y artefactos en `test-results/e2e/`. Ambos ignorados por git.

Decisiones abiertas:
- Aún no está integrado en CI.
- Los flujos autenticados requieren un usuario de prueba y una estrategia para MFA.

---

## Scheduler

```bash
# Ver todas las tareas programadas
php artisan schedule:list

# Ejecutar el scheduler manualmente (usado en crontab)
php artisan schedule:run

# Configurar en crontab del servidor (corre cada minuto)
# * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

---

## Queue

```bash
# Iniciar worker de colas
php artisan queue:work

# Worker con colas específicas
php artisan queue:work --queue=default,mailing

# Worker que procesa un job y termina (para cron)
php artisan queue:work --once

# Ver jobs fallidos
php artisan queue:failed

# Reintentar job fallido
php artisan queue:retry <id>

# Limpiar todos los jobs fallidos
php artisan queue:flush
```

---

## Módulo Mailing

### `mailing:dispatch-scheduled`

**Función:** Despacha campañas con `scheduled_at <= now()` y estado `approved`. Usa claim atómico para evitar ejecuciones paralelas.

```bash
php artisan mailing:dispatch-scheduled
php artisan mailing:dispatch-scheduled --dry-run   # previsualiza sin cambios
```

**Notas:**
- Programado en scheduler (frecuencia recomendada: cada minuto o cada 5 minutos)
- Usa `DB::table()->lockForUpdate()` para claim atómico
- Si la campaña ya fue reclamada, la omite silenciosamente
- Si la audiencia está vacía, marca como completada sin enviar

---

### `mailing:parse-bounces`

**Función:** Lee el inbox NDR configurado (`MAILING_NDR_INBOX`) via Microsoft Graph, correlaciona NDRs con `mailing_messages` por `X-Mailing-Token`, y registra eventos de `bounced_hard` o `bounced_soft`.

```bash
php artisan mailing:parse-bounces
php artisan mailing:parse-bounces --dry-run   # previsualiza sin cambios
```

**Notas:**
- Buzón: `MAILING_NDR_INBOX`; si no está, usa el remitente de campañas (`MAILING_FROM_ADDRESS`), porque los rebotes vuelven al remitente (Graph no permite otro Return-Path). CLA-656.
- Requiere `Mail.ReadWrite` de aplicación **solo sobre ese buzón** (Exchange RBAC for Applications, alcance limitado; nunca tenant-wide).
- Marca como leídos SOLO los rebotes reconocidos (no los elimina). Lo que no es un rebote (respuestas automáticas, correo humano) se **mueve sin leer** a la carpeta `MAILING_NDR_UNPROCESSED_FOLDER` (por defecto `Mailing - not processed`), para que no ocupe el lote del parser ni quede escondido.
- Procesa en lotes de `MAILING_NDR_BATCH_SIZE` (por defecto: 50)
- Los hard bounces añaden al contacto a `mailing_suppression_list`

---

### `mailing:ab-select-winner`

**Función:** Para campañas A/B activas con variantes enviadas, calcula el CTR de cada variante y selecciona el ganador. Despacha la campaña ganadora al resto de la audiencia.

```bash
php artisan mailing:ab-select-winner
php artisan mailing:ab-select-winner --dry-run   # previsualiza sin cambios
```

**Notas:**
- Solo actúa sobre campañas con `ab_test_started_at` no nulo
- El ganador se selecciona por CTR (clics únicos / enviados), no por aperturas
- Mínimo de muestra: `mailing.ab_min_sample` (por defecto: 5 por variante)
- Si no hay suficiente muestra, loga warning pero procede

---

### `mailing:dispatch-followups`

**Función:** Para campañas completadas con follow-up configurado, despacha la campaña follow-up a la audiencia filtrada por el trigger (clicked/not_clicked/opened/not_opened).

```bash
php artisan mailing:dispatch-followups
php artisan mailing:dispatch-followups --dry-run   # previsualiza sin cambios
```

**Notas:**
- Solo actúa sobre campañas en `completed` con `followup_campaign_id` no nulo
- Usa claim atómico para `followup_dispatched_at`
- Si la audiencia filtrada está vacía, marca como despachado sin enviar (safe)
- Los triggers basados en apertura (`opened`/`not_opened`) son señal débil (Apple MPP)

---

### `mailing:check-deliverability-alerts`

**Función:** Evalúa las campañas completadas en los últimos `mailing.alert_check_days` días y crea alertas si se superan los umbrales de hard bounce (>5%) o spam complaint (>0.08%).

```bash
php artisan mailing:check-deliverability-alerts
php artisan mailing:check-deliverability-alerts --dry-run   # previsualiza sin cambios
```

**Notas:**
- Las alertas se guardan en `mailing_deliverability_alerts`
- También dispara notificaciones DB para usuarios admin
- No crea alertas duplicadas para la misma campaña si ya existe una activa

---

## Módulo Core

### `core:issue-activation-code`

**Función:** Emite el código de activación de un solo uso que una cuenta recién provisionada necesita para ponerse su **primera** contraseña (CLA-653). Imprime el código y el enlace de reset; **no crea cuentas**.

```bash
php artisan core:issue-activation-code persona@claesen-verlichting.be
php artisan core:issue-activation-code persona@electrobertels.be --panel=bertels
```

**Por qué existe:** el alta desde el recurso Users guarda `password = null, password_set_at = null`, y `PasswordResetService::sendResetLink()` rechaza toda cuenta que no completó el setup — es decir, exactamente la que nunca tuvo contraseña. Sin esto, una cuenta local nueva no podía iniciar sesión nunca, y «olvidé mi contraseña» también la rechazaba.

**Notas:**
- Rechaza, con salida distinta de cero: dirección desconocida, dirección que coincide con **más de una** cuenta (mayúsculas/espacios), cuenta desactivada, cuenta de Microsoft (esas autentican por Entra y no tienen contraseña local) y cuenta que **ya** completó el setup — para esa última está el reset del login.
- El código se **imprime**, no se envía por correo: la cuenta recién creada puede no tener buzón todavía, y quien corre el comando ya tiene acceso al servidor.
- **Tratar la salida como credencial:** es de un solo uso y dura `PasswordResetService::CODE_TTL_MINUTES` (60 min), pero hasta usarse alcanza para fijar la contraseña de esa cuenta. Entregarlo por un canal privado.
- El correo de bienvenida de un *External worker* (CLA-685) **no** pasa por este comando: se envía solo al crear la cuenta y dura 72 h; si caduca, usar la acción **Resend activation email** de Users > Edit. Este comando sigue emitiendo códigos de 60 min.
- No reemplaza el alta: las cuentas se crean en Users (*Internal employee* desde Cafca, o *External worker* para quien no está en Cafca, CLA-683); este comando solo les da el código para su primera contraseña.

---

## Módulo Safety

### `safety:check-compliance`

**Función:** Verifica el estado de compliance de inspecciones de seguridad. Identifica proyectos activos sin inspección en los últimos `config('safety.compliance_days')` días (30 días por defecto).

```bash
php artisan safety:check-compliance
```

**Notas:**
- Programado en scheduler
- Usa `ComplianceService::getComplianceReport()`
- Puede disparar notificaciones a usuarios según configuración

---

### `safety:aggregate-adoption`

**Función:** Agrega métricas diarias de adopción (MAU, DAU, fricción técnica) y purga eventos crudos mayores a 90 días.

```bash
php artisan safety:aggregate-adoption
php artisan safety:aggregate-adoption --date="2026-06-22"  # Fuerza una fecha específica
```

**Notas:**
- Programado diariamente a la 01:00 AM.
- Por defecto lee los eventos de `Carbon::yesterday()`.
- Previene el crecimiento infinito de la tabla de eventos con la purga de 90 días.

---

## Módulo Website

### `website:regenerate-media`

**Función:** Regenera las conversiones de media (thumb, optimized, gallery) en WebP para todos los proyectos o para un subconjunto.

```bash
# Todos los proyectos
php artisan website:regenerate-media

# Solo una colección
php artisan website:regenerate-media --collection=gallery
php artisan website:regenerate-media --collection=featured_image

# Solo un proyecto
php artisan website:regenerate-media --project=<id>
```

**Notas:**
- Debe ejecutarse en producción después de WEB-005/WEB-006 (primera vez)
- Puede tardar varios minutos dependiendo del número de proyectos
- Idempotente: puede ejecutarse varias veces sin daño

---

## Módulo Intelligence

### `intelligence:sync-mirror`

**Función:** Sincroniza datos del SQL Server ERP → MySQL local (tablas mirror en Performance).

```bash
php artisan intelligence:sync-mirror
```

**Notas:**
- Solo lee de SQL Server (nunca escribe)
- Escribe en tablas `mirror_*` en MySQL
- Programado periódicamente (frecuencia según config)

---

### `intelligence:build-material-brain`

**Función:** Construye el índice de materiales para `MaterialIntelligenceService` (similarity search).

```bash
php artisan intelligence:build-material-brain
```

---

### `intelligence:map-warehouse-categories`

**Función:** Mapea categorías del almacén ERP a categorías locales para análisis.

```bash
php artisan intelligence:map-warehouse-categories
```

---

## Módulo Performance

### `performance:sync-all`

**Función:** Ejecuta todos los syncs necesarios para Performance (wrapper de múltiples syncs).

```bash
php artisan performance:sync-all
```

---

### `performance:populate-project-insights`

**Función:** Genera insights analíticos para todos los proyectos usando datos mirror y Gemini.

```bash
php artisan performance:populate-project-insights
```

**Notas:** Llama a Gemini por cada proyecto. Puede generar costos si hay muchos proyectos. Usar con límite si está disponible.

---

### `performance:analyze-technicians`

**Función:** Analiza arquetipos de técnicos con IA (Gemini).

```bash
php artisan performance:analyze-technicians
```

---

### `performance:send-watchdog-report`

**Función:** Envía el reporte Watchdog de riesgo financiero a `orelvys.cuellar@claesen-verlichting.be`.

```bash
php artisan performance:send-watchdog-report
```

**Notas:**
- Programado para lunes por la mañana
- Alerta si (Costo Real − Facturado) > €2,500 (WIP Trap)
- Alerta inmediata si > €20,000 (threshold `WATCHDOG_IMMEDIATE_THRESHOLD`)

---

## Módulo Prospects

### `prospects:sync-master`

**Función:** Orquesta todos los syncs de federaciones en un solo command.

```bash
php artisan prospects:sync-master
```

---

### Syncs individuales de federaciones

```bash
php artisan prospects:sync-rbfa-graphql   # RBFA — fútbol belga
php artisan prospects:sync-lbfa           # LBFA — fútbol belga francófono
php artisan prospects:sync-aft            # AFT — tenis
php artisan prospects:sync-hockey         # Hockey belga
php artisan prospects:sync-tpv            # TPV
php artisan prospects:sync-val            # VAL
```

**Motivos de fallo de `prospects:sync-rbfa-graphql` (CLA-663):** cada serie (o club en el enriquecimiento) que falla guarda su motivo: `HTTP <código> <content-type>: <inicio de la respuesta sin HTML>` (p. ej. la página «Access Denied» de Akamai con HTTP 200 `text/html`, o el primer mensaje GraphQL como `PersistedQueryNotFound`) o `<Clase de excepción>: <mensaje>` (máx. ~200 caracteres). Dónde verlos:
- `SyncHistory.logs` (pantalla de historial): líneas `❌ Series <id> (<provincia>) failed: <motivo>` si el fallo es parcial; si fallan **todas** las series, líneas agrupadas `❌ <N> series failed: <motivo>` antes de `Error: All RBFA discovery series failed.`
- `storage/logs/laravel.log`: un único `WARNING All RBFA discovery series failed.` con los motivos y sus recuentos, solo cuando fallan todas.

**Notas:**
- Todos los syncs son idempotentes
- Usan `firstOrCreate` / `updateOrCreate` para evitar duplicados
- El historial queda en `SyncHistory`

---

## Módulo FieldOps

### `fieldops:generate-maintenance-work-orders`

**Función:** Genera una orden de trabajo para cada plan de mantenimiento recurrente cuyo `next_due_at` ya venció y adelanta el plan al siguiente ciclo.

```bash
php artisan fieldops:generate-maintenance-work-orders --dry-run
php artisan fieldops:generate-maintenance-work-orders
```

**Notas:**
- `--dry-run` cuenta planes vencidos sin escribir.
- Cada plan se bloquea dentro de una transacción para evitar duplicados por ejecuciones concurrentes.
- Programado cada hora con `withoutOverlapping()` desde `FieldOpsServiceProvider`.
- No crea `FoMaintenanceRecord`; ese historial nace únicamente al validar/cerrar una orden ejecutada.

### `fieldops:check-request-alerts`

**Función:** Revisa solicitudes de mantenimiento de cliente contra los umbrales operacionales de Fase 5 — sin primera respuesta del backoffice (`no_first_response`) y resueltas sin confirmación del cliente (`awaiting_confirmation`) — y notifica a `admin`/`super_admin` vía notificación de base de datos.

```bash
php artisan fieldops:check-request-alerts --dry-run
php artisan fieldops:check-request-alerts
```

**Notas:**
- Umbrales en `config('fieldops.request_alerts')`: `FIELDOPS_REQUEST_ALERT_FIRST_RESPONSE_HOURS` (default 24h), `FIELDOPS_REQUEST_ALERT_CONFIRMATION_WAIT_DAYS` (default 7 días).
- Idempotente vía `updateOrCreate` en la clave única `(maintenance_request_id, alert_type)` de `fo_maintenance_request_alerts` — no duplica ni renotifica mientras la alerta siga abierta.
- Se auto-resuelve solo (marca `resolved_at`) en la corrida siguiente una vez que la condición deja de cumplirse; la misma fila se reutiliza si la solicitud vuelve a incumplir en un ciclo posterior (reapertura), por lo que sí puede volver a notificar.
- Programado cada hora con `withoutOverlapping()` desde `FieldOpsServiceProvider`, junto a `fieldops:generate-maintenance-work-orders`.
- Ver el runbook operacional completo (qué hacer con cada tipo de alerta) en `docs/ai/production-readiness.md`, sección "Claesen-Client".

---

## App (raíz)

### `sync:employees`

**Función:** Sincroniza empleados del SQL Server ERP → MySQL local.

```bash
php artisan sync:employees
```

---

## Comandos de mantenimiento Laravel

> **Producción (prod-priv-01):** `opcache.validate_timestamps=0` (`/etc/php/8.4/fpm/conf.d/10-opcache-prod.ini`) — PHP-FPM no relee archivos por su cuenta. Cualquier `config:cache`/`config:clear` corrido fuera de un deploy completo (edición manual de `shared/.env`, por ejemplo) requiere además `sudo systemctl reload php8.4-fpm`, o el cambio queda escrito en el archivo cacheado pero invisible para el tráfico real hasta el próximo deploy. Usar `infrastructure/scripts/reload-config.sh` para este caso (config:clear + config:cache como `www-data` + reload de PHP-FPM en un solo paso). `infrastructure/scripts/deploy.sh` ya hace el reload como parte de un deploy normal (paso 9), no hace falta correrlo aparte ahí.

```bash
# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Migraciones
php artisan migrate
php artisan migrate:status
php artisan migrate:rollback
php artisan migrate --force           # en producción

# Modelos
php artisan model:prune               # si hay modelos con Prunable

# Sail (Docker)
sail up -d
sail down
sail artisan ...
sail composer ...
```
