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
- Requiere `MAILING_NDR_INBOX` configurado en `.env`
- Requiere permisos `Mail.Read` en la app Azure para ese buzón
- Marca los mensajes NDR como leídos (no los elimina)
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

**Notas:**
- Todos los syncs son idempotentes
- Usan `firstOrCreate` / `updateOrCreate` para evitar duplicados
- El historial queda en `SyncHistory`

---

## App (raíz)

### `sync:employees`

**Función:** Sincroniza empleados del SQL Server ERP → MySQL local.

```bash
php artisan sync:employees
```

---

## Comandos de mantenimiento Laravel

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
