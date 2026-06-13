# Monthly Billing Guardian — Documentación técnica

> Módulo Intelligence — Sprint 2B | Completado: 2026-06-13

---

## Propósito

El **Monthly Billing Guardian** detecta automáticamente anomalías de facturación analizando los datos del mirror MySQL. Es un sistema de detección y recomendación: **nunca escribe en SQL Server ni genera facturas directamente**. La validación y la acción sobre las alertas son siempre responsabilidad del gestor.

---

## Arquitectura

```
intelligence:billing-guardian (Artisan)
       ↓
MonthlyBillingGuardianService::analyzeMonth(year, month, dryRun)
       ↓
  7 reglas de detección (mirror MySQL, ReadOnly)
       ↓
  upsertAlerts() — §4.4.1 rerun policy
       ↓
  generateMonthlyCloseBlocker()
       ↓
  intelligence_billing_alerts (tabla persistente)
       ↓
MonthlyBillingControlPage (Filament V5) — workflow UI
```

---

## Tabla: `intelligence_billing_alerts`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | bigint PK | — |
| `dedup_key` | varchar(191) UNIQUE | Clave determinista por periodo/tipo/proyecto/factura |
| `period_year` | smallint | Año del período analizado |
| `period_month` | tinyint | Mes del período analizado |
| `alert_type` | varchar | Ver tipos más abajo |
| `severity` | enum | critical \| high \| medium \| low |
| `status` | enum | open \| in_review \| confirmed \| dismissed \| resolved |
| `project_id` | varchar(15) nullable | ID del proyecto mirror |
| `relation_id` | int nullable | FK a `intelligence_mirror_relations` |
| `invoice_id` | varchar(50) nullable | ID de factura (prefijo CN% = nota de crédito) |
| `amount_activity_cost` | decimal(14,2) | Coste de actividad detectado |
| `amount_estimated` | decimal(14,2) | `contract_price` del proyecto |
| `amount_open` | decimal(14,2) | Saldo abierto (overdue/partial/closed) |
| `evidence_json` | json | Evidencia estructurada por tipo de regla |
| `recommendation` | text | Texto NL para el gestor |
| `ai_analysis` | text nullable | Reservado para análisis futuro con Gemini |
| `assigned_to`, `reviewed_by` | FK users nullable | Auditoría del workflow |
| `reviewed_at`, `resolved_at` | timestamp nullable | — |
| `resolution_notes` | text nullable | Notas del revisor |

### Formato `dedup_key`

```
"{year}:{month:02d}:{alert_type}:{project_id|''}:{invoice_id|''}"
```

Ejemplo: `"2026:06:overdue_receivable::F25260007"` — NULLs se convierten en string vacío para respetar el índice UNIQUE (MySQL ignora NULLs en UNIQUE).

---

## Reglas de detección

### BI-052 — `missing_customer_invoice`
**Trigger:** proyecto con actividad económica (`SUM cost_price × quantity > min_activity_amount`) en el período y **sin factura no-CN emitida en ese mes**.

- Excluye proyectos sin contrato y sin estimate vinculado (salvo `include_projects_without_contract = true`).
- Evidence: `costs_in_month`, `hours_in_month`, `workdocs_in_month`, `last_invoice_date`, `days_since_last_invoice`.
- Severity: `high`.

### BI-053 — `overdue_receivable`
**Trigger:** factura no pagada (`fl_paid=false`, no CN%), `date_expiration < hoy`, saldo `(total_price − total_paid) > min_amount`.

- Semántica snapshot: una factura impagada re-alerta en el siguiente período (intencional).
- Severity: días vencida > 60 → `critical`; si no → `high`.

### BI-053 — `partial_payment`
**Trigger:** factura parcialmente pagada (`total_paid > 0`), saldo > `min_amount`, **aún no vencida** (o sin fecha).

- Exclusión mutua con `overdue_receivable`: al vencer, pasa a overdue.
- Severity: `medium`.

### BI-054 — `unbilled_followup_cost` (Auditor Gate: APPROVED)
**Trigger:** costes con `invoiced=false` en el período, agrupados por proyecto. Si `SUM(cost_price × quantity) > min_cost_amount` → alerta.

**Decisión aprobada:** evaluación a nivel proyecto (no por ítem individual) porque detecta acumulación de costes pequeños no facturados.

- Evidence: `count_items`, `total_amount`, `cost_types[]`.
- Severity: `medium` (≤ €10k) | `high` (> €10k).

### BI-055 — `project_billing_gap`
**Trigger:** proyectos activos (`fl_active=true`) con actividad en el período sin ninguna factura no-CN en los últimos `days_without_invoice` días (default 30).

- Sin actividad en el período → no dispara (evita ruido en proyectos pausados).
- Severity: `medium`.

### BI-055 — `credit_note`
**Trigger:** facturas `id LIKE 'CN%'` emitidas en el período.

- Visibilidad gerencial. No bloquea cierre mensual.
- Severity: `low`.

### BI-055 — `closed_with_balance`
**Trigger:** proyectos con `fl_active=false` que tienen facturas no CN% con `fl_paid=false` y saldo > `min_amount`.

- Severity: `high` (siempre anómalo: proyecto cerrado con saldo abierto).

### Bloqueador mensual — `monthly_close_blocker`
Generado automáticamente por `generateMonthlyCloseBlocker()`. Mientras haya alertas `critical` o `high` en estado `open` o `in_review`, existe un bloqueador activo. Se auto-resuelve cuando no quedan pendientes.

---

## Política de reejección §4.4.1

Al re-ejecutar el Guardian para el mismo período:

| Estado actual | Acción |
|--------------|--------|
| `open` | Actualiza evidence, amounts, recommendation, severity |
| `in_review` | Igual que open (datos frescos, decisión humana pendiente) |
| `confirmed` | Solo actualiza `amount_open` (la decisión humana no se toca) |
| `dismissed` | Sin acción — nunca se reabre automáticamente |
| `resolved` | Sin acción — nunca se reabre automáticamente |

---

## Configuración (`billing_guardian_rules`)

| Clave | Default | Descripción |
|-------|---------|-------------|
| `days_without_invoice` | 30 | Días sin factura para `project_billing_gap` |
| `min_amount` | 500 | Umbral € para `overdue_receivable` y `partial_payment` |
| `min_activity_amount` | 500 | Umbral € para `missing_customer_invoice` (BI-052) |
| `min_cost_amount` | 500 | Umbral € para `unbilled_followup_cost` (BI-054) |
| `include_projects_without_contract` | false | Alertar proyectos sin contrato ni estimate |

Configurable desde **Filament → Intelligence Hub → BI Configuratie**.

---

## Comando Artisan

```bash
# Período específico
php artisan intelligence:billing-guardian --month=2026-05

# Mes actual
php artisan intelligence:billing-guardian --current-month

# Mes anterior (modo scheduler)
php artisan intelligence:billing-guardian --previous-month

# Dry run (detecta sin persistir)
php artisan intelligence:billing-guardian --month=2026-05 --dry-run
```

### Scheduler

Se ejecuta el **día 2 de cada mes a las 07:00 hora de Bruselas**, analizando el mes anterior.

```php
$schedule->command('intelligence:billing-guardian --previous-month')
    ->monthlyOn(2, '07:00')
    ->timezone('Europe/Brussels')
    ->withoutOverlapping();
```

---

## UI — `MonthlyBillingControlPage`

Ruta: `/billing-control` | Nav: Intelligence Hub

### KPIs
- Total alertas del período
- Open / In review / Confirmed
- Critical / High (activos)

### Tabs
| Tab | Tipos incluidos |
|-----|----------------|
| Alle | Todas |
| Facturatie | `missing_customer_invoice`, `project_billing_gap` |
| Vorderingen | `overdue_receivable`, `partial_payment` |
| Kosten | `unbilled_followup_cost`, `closed_with_balance` |
| Creditnotas | `credit_note` |
| System | `monthly_close_blocker` |

### Workflow (BI-059)

```
open → [Review] → in_review → [Confirm] → confirmed → [Resolve] → resolved
                            → [Dismiss] → dismissed → [Resolve] → resolved
                                                     → [Reopen] → open
```

Botones por fila, visibles según el estado actual. Solo aparecen las transiciones válidas.

---

## Tests

| Archivo | Tests | Assertions |
|---------|-------|------------|
| `BiConfigServiceTest` | 11 | — |
| `LaborSyncWindowTest` | 8 | — |
| `MirrorModelsTest` | 8 | — |
| `BillingGuardianUpsertTest` | 11 | — |
| `BillingGuardianMissingInvoiceTest` | 10 | — |
| `BillingGuardianOverdueTest` | 14 | 28 |
| `BillingGuardianUnbilledCostTest` | 15 | 26 |
| `BillingGuardianRemainingRulesTest` | 18 | 32 |
| **Total** | **95** | **200** |

---

## Producción — acciones requeridas

```bash
# 1. Ejecutar migraciones (si no están ya en producción)
php artisan migrate

# 2. Sembrar BiConfig (firstOrCreate — nunca sobreescribe)
php artisan db:seed --class=Modules\\Intelligence\\Database\\Seeders\\BiConfigSeeder

# 3. Primera ejecución manual (dry-run para validar)
php artisan intelligence:billing-guardian --previous-month --dry-run

# 4. Primera ejecución real
php artisan intelligence:billing-guardian --previous-month
```

El scheduler se activa automáticamente al hacer deploy — no requiere configuración adicional.
