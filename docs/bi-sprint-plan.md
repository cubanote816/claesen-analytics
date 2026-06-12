# Plan Sprint BI — CAFCA Intelligence Hub
> Basado en análisis profundo directo de SQL Server CLAESEN (2026-06-12)
> Última actualización: 2026-06-12 — v3: dedup_key, rerun policy, amount_activity_cost, AP Guardian separado, árbol de sprints corregido

---

## 1. Hallazgos del análisis de BD (resumen ejecutivo)

### 1.1 Mapeo `estimate_item.art_type` — definitivo

| art_type | Filas | Significado | Ejemplo real |
|----------|-------|-------------|--------------|
| `0` | 79.377 | Línea descriptiva / texto libre | "Mast in conische buisvorm 16m hoogte" |
| `1` | 841 | Solo material (catálogo) | "OptiVision LED BVP527", cables |
| `2` | 222 | Solo mano de obra | "Sleufwerken in volle grond" |
| `5` | 9.412 | Suministro + instalación | "Leveren en plaatsen Philips BVP525 LED" |

### 1.2 MAMO — dónde vive

`estimate` **no tiene** `perc_M/A/E/O`. El MAMO vive en `estimate_calculation` (`factor_material`, `factor_labor`, `factor_equipment`, `factor_subcontract` + costos extra detallados). `estimate.total_costprice` y `total_price` son el resultado final.

### 1.3 Stock — descartado

`stock_history` tiene 29 filas, última entrada 2020-04-28. Sistema abandonado. No se modela stock.

### 1.4 Gaps críticos en el mirror actual

El mirror existente tiene campos faltantes que bloquean tanto el Billing Guardian como el simulador:

| Tabla mirror | Campos faltantes | Impacto |
|--------------|-----------------|---------|
| `intelligence_mirror_projects` | `contract_price`, `type`, `state` | Billing Guardian no puede detectar proyectos activos con contrato |
| `intelligence_mirror_costs` | `invoice` (boolean) | No se pueden detectar costos no facturados |
| `intelligence_mirror_invoices` | `relation_id`, `date_expiration`, `fl_paid` | No se puede ligar factura al cliente ni calcular vencimiento exacto |

> ⚠️ Campo correcto es `date_expiration` (NO `date_due`). `fl_paid` es bit (0/1) y es más fiable que calcular `total_paid < total_price`.

Estos gaps se corrigen en **Sprint 1** antes de cualquier lógica de negocio.

### 1.5 Hallazgos adicionales confirmados (validación final)

#### `estimate.fl_approved` — siempre 0

`fl_approved` es 0 en **todos** los registros (6.676 presupuestos). La aprobación en CAFCA se rastrea por linkage: si existe un `project` que referencia el `estimate`, está aprobado. **No usar `fl_approved` como señal.**

Interpretación de `estimate.status` (7 valores activos, status=2 sin datos):

| status | cnt | % sent | Interpretación probable |
|--------|-----|--------|------------------------|
| 4 | 6.089 | 45% | Oferta en curso / activa (bulk principal) |
| 5 | 236 | 4% | Revisión interna |
| 3 | 208 | 84% | Enviada al cliente, pendiente respuesta |
| 1 | 58 | 19% | Borrador |
| 6 | 36 | 31% | Perdida / rechazada |
| 0 | 27 | 7% | Sin clasificar / legacy |
| 7 | 22 | 9% | Archivada |

> Gerencia debe confirmar vía `bi_config.estimate_status_labels`. Status=3 es el mejor proxy de "oferta enviada".

#### `relation.payment_term` — es smallint, no texto

`relation.payment_term` es un smallint (0, 1, 4, 5…) que referencia una tabla de términos de pago de CAFCA. El texto legible ("30 dagen", "60 dagen (dem)") está en `invoice.payment_term` (nvarchar). Para cálculo de vencimiento: **usar siempre `invoice.date_expiration`** directamente (campo ya calculado y persistido).

#### `workdoc.fl_needinvoiced` — no apto para detección

Solo 11 registros en toda la historia con `fl_needinvoiced=true` (el más reciente: 2023). El campo no se mantiene activamente. La detección de trabajo no facturado se hace via **`followup_cost.invoice = false`**, no via workdoc flags.

`workdoc.fl_invoice=true` significa "tipo facturable" (tipo del wororder), NO "ha sido facturado".

#### `rpt_project_results` — columnas confirmadas

Vista tiene 24 columnas: `project_id/name/relation_id/name`, MAMO separado (`costprice_material/labor/equipment/subcontract/extra/transport/total`), `invoiced`, `profit`, `profit_percent`, `profit_percent_estimates`, `total_estimates`, `hours_regie`, `oH`, `project_uren`, `voorz_uren`, `current_costs_booked`. **No tiene columna `type`** — esa viene del JOIN con `project`.

#### Billing Guardian — datos reales validados (mayo 2026)

| Regla | Resultado en producción |
|-------|------------------------|
| `missing_customer_invoice` | **8 proyectos** con actividad mayo 2026 sin factura mayo (top: Limburg €20.643, Heuvelland €9.925) |
| `overdue_receivable` | **50 facturas abiertas**, €350.950 saldo pendiente. Más antigua: 2009 (6.012 días). |
| `unbilled_followup_cost` | P20240034 Hamburg Stealers: **€132.126 sin facturar** (solo en 2026). Top 15 suma ~€500k. |

Las reglas funcionan sobre datos reales. El Auditor Gate sigue siendo obligatorio antes de BI-052/053/054.

### 1.6 Tablas SQL Server confirmadas como utilizables

```
Alta prioridad (mirror ya existe o se añade en Sprint 1):
  invoice            → intelligence_mirror_invoices    (ampliar: +relation_id, +date_expiration, +fl_paid)
  project            → intelligence_mirror_projects    (ampliar: +contract_price, +type, +state)
  followup_cost      → intelligence_mirror_costs       (ampliar: +invoice boolean)
  relation           → intelligence_mirror_relations   (existe — Sprint 1 BI)
  estimate           → via intelligence_mirror_estimate_calc (nuevo Sprint 1)
  project_estimates  → intelligence_mirror_project_links (nuevo Sprint 1)

Media prioridad:
  workdoc            → intelligence_mirror_workdocs    (nuevo Sprint 1)
  rpt_project_results → intelligence_mirror_project_results (nuevo Sprint 1)

Descartadas:
  stock / stock_history  → abandonados desde 2020
  invoice_line           → resúmenes no granulares; baja prioridad
```

---

## 2. `bi_config` — configuración gestionada por gerencia

Tabla MySQL para valores que no están en la BD SQL Server y que gerencia debe definir.

```sql
CREATE TABLE intelligence_bi_config (
  id           bigint AUTO_INCREMENT PRIMARY KEY,
  config_key   varchar(100) NOT NULL UNIQUE,
  config_value json NOT NULL,
  label        varchar(200) NOT NULL,
  description  text NULL,
  updated_by   bigint NULL,
  updated_at   timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Entradas del seeder (valores iniciales)

| config_key | Propósito | Valor por defecto |
|------------|-----------|-------------------|
| `project_type_labels` | Nombres de `project.type` (0-8) | `{0:null, 1:null, …}` — gerencia rellena |
| `estimate_status_labels` | Nombres de `estimate.status` (0-7) | `{0:null, 4:null, …}` — gerencia rellena |
| `variant_margin_targets` | Márgenes objetivo por variante | `{economy:20, standard:27, premium:35}` |
| `labor_sync_schedule` | Ventana horaria para sync labor | `{start:null, end:null}` |
| `billing_guardian_rules` | Reglas configurables del Guardian | Ver §4.3 |

### `BiConfigPage` — pantalla Filament V5

```
Sección 1: Clasificación de proyectos
  KeyValue: type 0-8 → nombre (con badge de warning si vacío)

Sección 2: Estados de presupuesto CAFCA
  KeyValue: status 0-7 → nombre + color badge
  Info: "Status=4 corresponde al 91% de los presupuestos"

Sección 3: Márgenes objetivo por variante
  TextInput(%): Económica / Estándar / Premium
  Helper: "Análisis histórico CAFCA indica rango típico 15-35%"

Sección 4: Sincronización de horas de trabajo
  TimePicker: inicio / fin de ventana segura
  Warning: "followup_labor_analytical se bloquea durante uso activo de CAFCA"
  Botón: "Ejecutar sync ahora"

Sección 5: Reglas del Monthly Billing Guardian
  NumberInput: días sin factura para alerta (default 30)
  NumberInput: importe mínimo de actividad para alerta (default €500)
  Toggle: incluir proyectos sin contrato definido
```

---

## 3. Árbol de sprints

```
Sprint 0   — Integración BI→main (cherry-pick, 1-2 días)
  └─ Sprint 1  — Motor de datos + mirrors + config (1 semana)
       ├─ Sprint 2   — Motor financiero: inflación + 3 variantes (2 semanas)
       │    └─ Sprint 3  — UI simulador + exportación (2 semanas)
       │         └─ Sprint 4  — Métricas + patrones institucionales (2 semanas)
       └─ Sprint 2B  — Monthly Billing Guardian (2 semanas)  ← independiente de Sprint 2
            (puede ejecutarse en paralelo con Sprint 2 o Sprint 3 si hay capacidad)
```

**Dependencias:**
- Sprint 2B requiere Sprint 1 (mirrors con `invoiced`, `date_expiration`, `fl_paid`, `contract_price`).
- Sprint 2B **no requiere** Sprint 2 (motor de inflación/variantes es irrelevante para detección de facturas).
- Sprint 3 y Sprint 2B pueden ejecutarse en paralelo una vez Sprint 1 está completo.

---

## 4. Sprint 2B — Monthly Billing Guardian

### 4.1 Objetivo

Producir cada mes una lista cerrada de control que impide que gerencia cierre el mes sin revisar:

1. Qué se debe facturar a clientes (AR: facturación pendiente).
2. Qué facturas emitidas siguen sin cobrarse (AR: cobros pendientes).
3. Qué proyectos tienen actividad real pero no tienen factura generada.
4. Qué casos requieren revisión humana antes del cierre mensual.

**Regla de oro:** El sistema detecta y recomienda. No genera facturas. No escribe en CAFCA.

> **Fuera de alcance — AP Guardian (módulo futuro):** La gestión de pagos a proveedores (facturas de compra, órdenes de pago, conciliación AP) es un módulo independiente. Pertenece al lado Accounts Payable, requiere integración con `purchase_invoice` / `supplier_order` de CAFCA, y tiene un workflow de aprobación distinto. No se incluye en Sprint 2B. Registrado como deuda técnica en `docs/ai/known-risks.md` para decisión de gerencia.

### 4.2 Tabla `intelligence_billing_alerts`

```sql
CREATE TABLE intelligence_billing_alerts (
  id                  bigint AUTO_INCREMENT PRIMARY KEY,

  -- deduplicación determinista (construida por la app antes del upsert)
  -- formato: "{year}:{month}:{alert_type}:{project_id|''}:{invoice_id|''}"
  -- ejemplo: "2026:05:missing_customer_invoice:P20250063:"
  -- ejemplo: "2026:05:overdue_receivable::F25260007"
  dedup_key           varchar(191) NOT NULL,

  period_year         smallint NOT NULL,
  period_month        tinyint  NOT NULL,
  alert_type          varchar(50) NOT NULL,
  severity            enum('low','medium','high','critical') NOT NULL,
  status              enum('open','in_review','confirmed','dismissed','resolved')
                      NOT NULL DEFAULT 'open',

  -- contexto
  project_id          varchar(20) NULL,
  relation_id         int NULL,
  invoice_id          varchar(20) NULL,

  -- importes (semántica diferenciada por alert_type)
  amount_activity_cost decimal(12,2) NULL,  -- costes detectados en mirror_costs para el periodo
                                            -- solo para: missing_customer_invoice, unbilled_followup_cost
  amount_estimated    decimal(12,2) NULL,   -- solo cuando hay contract_price o estimate confiable
  amount_open         decimal(12,2) NULL,   -- saldo confirmado (overdue_receivable, partial_payment)

  -- evidencia y acción
  evidence_json       json NOT NULL,        -- datos concretos que originan la alerta
  recommendation      text NOT NULL,        -- texto para el gerente
  ai_analysis         text NULL,            -- análisis Gemini opcional

  -- workflow
  assigned_to         bigint NULL,          -- FK users.id
  reviewed_by         bigint NULL,
  reviewed_at         timestamp NULL,
  resolved_at         timestamp NULL,
  resolution_notes    text NULL,

  created_at          timestamp DEFAULT CURRENT_TIMESTAMP,
  updated_at          timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_dedup_key  (dedup_key),
  INDEX idx_period         (period_year, period_month),
  INDEX idx_project        (project_id),
  INDEX idx_status_type    (status, alert_type),
  INDEX idx_severity       (severity)
);
```

### 4.3 Tipos de alerta

| alert_type | Severity | Descripción |
|------------|----------|-------------|
| `missing_customer_invoice` | **high** | Actividad económica en el mes pero sin invoice emitida |
| `overdue_receivable` | **critical** | Invoice emitida no cobrada y fecha de vencimiento pasada |
| `partial_payment` | medium | Invoice cobrada parcialmente — saldo pendiente > 0 |
| `unbilled_followup_cost` | high | Costos con `followup_cost.invoice = false` en el mes |
| `project_billing_gap` | medium | Proyecto activo con contrato > €0 sin factura en X días (configurable) |
| `credit_note_review` | medium | Nota de crédito (id 'CN…') generada en el mes — requiere revisión |
| `project_closed_with_open_balance` | high | Proyecto no activo pero con facturas pendientes de cobro |
| `client_payment_pattern` | low | Cliente con historial de pagos tardíos detectado en period |
| `monthly_close_blocker` | **critical** | Resumen: hay alertas críticas/high sin resolver — bloquea cierre del mes |

### 4.4 `MonthlyBillingGuardianService`

```php
namespace Modules\Intelligence\Services;

class MonthlyBillingGuardianService
{
    public function analyzeMonth(int $year, int $month): BillingGuardianReport;

    // Reglas individuales (privadas, cada una devuelve array de alertas)
    private function detectMissingCustomerInvoices(int $year, int $month): array;
    private function detectOverdueReceivables(int $year, int $month): array;
    private function detectPartialPayments(int $year, int $month): array;
    private function detectUnbilledFollowupCosts(int $year, int $month): array;
    private function detectProjectBillingGaps(int $year, int $month): array;
    private function detectCreditNotes(int $year, int $month): array;
    private function detectClosedProjectsWithBalance(int $year, int $month): array;
    private function detectClientPaymentPatterns(int $year, int $month): array;

    // Persistencia — upsert con política por status (ver §4.4.1)
    private function upsertAlerts(array $alerts): void;
    private function computeDeduplicationKey(string $alertType, ?string $projectId, ?string $invoiceId, int $year, int $month): string;
    private function generateMonthlyCloseBlocker(int $year, int $month): void;
}
```

#### §4.4.1 — Política de rerun y upsert por status

Cuando el Guardian se ejecuta de nuevo para el mismo periodo (rerun manual, scheduler, --dry-run), el comportamiento de `upsertAlerts` depende del status actual de la alerta:

| Status actual | Acción en rerun | Razón |
|---------------|----------------|-------|
| `open` | **Actualizar** evidence_json, amounts, recommendation | La alerta no ha sido tocada; refrescar con datos actuales |
| `in_review` | **Actualizar** evidence_json y amounts, preservar reviewer/assigned_to | El gerente la está mirando; actualizar datos pero no interrumpir |
| `confirmed` | **No sobrescribir** — solo actualizar `amount_open` si cambió | La decisión humana es inamovible; solo refrescar el saldo |
| `dismissed` | **No reabrir** — la alerta ya fue descartada conscientemente | Si el dato vuelve a existir, la próxima ejecución del periodo *siguiente* la recogerá |
| `resolved` | **No reabrir** — issue cerrado con resolution_notes | Igual que dismissed |

```php
// Pseudocódigo de upsertAlerts
foreach ($alerts as $alert) {
    $key = $this->computeDeduplicationKey(...);
    $existing = BillingAlert::where('dedup_key', $key)->first();

    if (!$existing) {
        BillingAlert::create([...$alert, 'dedup_key' => $key]);
        continue;
    }

    match ($existing->status) {
        'open', 'in_review' => $existing->update([
            'evidence_json'       => $alert['evidence_json'],
            'amount_activity_cost'=> $alert['amount_activity_cost'],
            'amount_estimated'    => $alert['amount_estimated'],
            'amount_open'         => $alert['amount_open'],
            'recommendation'      => $alert['recommendation'],
            'severity'            => $alert['severity'],
        ]),
        'confirmed' => $existing->update(['amount_open' => $alert['amount_open']]),
        default     => null, // dismissed / resolved: no action
    };
}
```

> `dedup_key` se construye como: `"{year}:{month}:{alert_type}:{project_id|''}:{invoice_id|''}"`. Los valores NULL se convierten en cadena vacía para garantizar unicidad determinista.

---

**Lógica clave — `detectMissingCustomerInvoices`:**
```
Para cada proyecto con actividad en [year-month]:
  Actividad = cualquiera de:
    - intelligence_mirror_costs.date BETWEEN inicio_mes AND fin_mes AND project_id = p.id
    - intelligence_mirror_labor.date BETWEEN ...  (si disponible)
    - intelligence_mirror_workdocs.date BETWEEN ...

  Factura = intelligence_mirror_invoices con:
    - project_id = p.id
    - date BETWEEN inicio_mes AND fin_mes
    - id NOT LIKE 'CN%'  (no es nota de crédito)

  Si actividad existe Y factura NO existe:
    → alert_type = 'missing_customer_invoice'
    → severity = 'high'
    → evidence_json = {
        costs_in_month: €X.XXX,
        hours_in_month: N,
        workdocs_in_month: N,
        last_invoice_date: '...',
        days_since_last_invoice: N
      }
    → recommendation = "Proyecto {name} tiene €{cost} en costes en {mes} sin factura emitida.
                        Última factura: {date} ({days} días). Revisar si corresponde emitir factura parcial."
    → amount_activity_cost = costs_in_month (costes detectados en mirror_costs — siempre disponible)
    → amount_estimated     = contract_price o total_price del estimate activo, solo si existe y es confiable
                             NULL si el proyecto no tiene contrato definido
    → Excluir si contract_price IS NULL Y no hay estimate activo (proyecto interno/no facturable)
```

**Lógica — `detectOverdueReceivables`:**
```
intelligence_mirror_invoices WHERE:
  fl_paid = false                         ← campo bit, más fiable que calcular diferencia
  AND id NOT LIKE 'CN%'
  AND date_expiration < TODAY             ← campo correcto (no date_due, no existe)
  AND (total_price - total_paid) > €config('billing_guardian_rules.min_amount')

→ alert_type = 'overdue_receivable'
→ severity = (days_overdue > 60) ? 'critical' : 'high'
→ evidence_json = { invoice_id, client_name, amount_open, days_overdue, date_expiration }
→ recommendation = "Factura {id} de {client}: €{open} pendiente desde hace {days} días."

Datos reales (hoy): 50 facturas abiertas, €350.950 saldo total.
```

**Lógica — `detectUnbilledFollowupCosts`:**
```
intelligence_mirror_costs WHERE:
  date BETWEEN inicio_mes AND fin_mes
  AND invoiced = false    ← campo añadido en Sprint 1
  AND (cost_price * quantity) > €config('billing_guardian_rules.min_cost_amount')

Agrupar por project_id:
→ alert_type = 'unbilled_followup_cost'
→ evidence_json = { count_items, total_amount, cost_types: [M, A, E, O] }
→ recommendation = "{N} costes por €{total} no marcados como facturados en {mes}."
```

### 4.5 Comando Artisan

```bash
php artisan intelligence:billing-guardian --month=2026-05
php artisan intelligence:billing-guardian --current-month
php artisan intelligence:billing-guardian --previous-month
php artisan intelligence:billing-guardian --dry-run   # analiza sin persistir
```

Salida esperada:
```
Monthly Billing Guardian — Análisis mayo 2026
════════════════════════════════════════════
✅  Proyectos analizados:        87
⚠️  Alertas generadas:           23
   ├── critical: 3
   ├── high:    11
   ├── medium:   7
   └── low:      2
🔒  Mes listo para cerrar: NO (3 alertas críticas abiertas)

Alertas nuevas: 21  |  Actualizadas: 2  |  Duplicados evitados: 0
```

### 4.6 Scheduler

```php
// En el ServiceProvider o Kernel:
$schedule->command('intelligence:billing-guardian --previous-month')
         ->monthlyOn(2, '07:00');  // día 2 de cada mes a las 7:00
```

### 4.7 `MonthlyBillingControlPage` — Filament V5

```
Header KPIs (InfoStats):
  Facturas sugeridas    | N          | color: warning
  Importe estimado      | €XXX.XXX   | color: warning
  Facturas vencidas     | N          | color: danger
  Saldo pendiente       | €XXX.XXX   | color: danger
  Alertas críticas      | N          | color: danger
  Mes listo para cerrar | SÍ / NO    | color: success / danger

Selector de periodo: [Mes anterior ▼] [Analizar ahora]

Tab 1: "Facturas por generar" (missing_customer_invoice)
  Tabla: Proyecto | Actividad detectada | Costes mes | Días sin factura | Importe estimado | [Confirmar] [Descartar]

Tab 2: "Facturas por cobrar" (overdue_receivable + partial_payment)
  Tabla: Factura | Cliente | Total | Cobrado | Pendiente | Vencida hace | [Marcar en revisión]

Tab 3: "Costes sin facturar" (unbilled_followup_cost)
  Tabla: Proyecto | Nº costes | Importe | Tipos MAMO | [Revisar]

Tab 4: "Notas de crédito y cierre" (credit_note_review + monthly_close_blocker)
  Tabla de notas de crédito del mes
  Sección: "Estado del cierre mensual" con checklist visual

Tab 5: "Historial" — alertas de meses anteriores con su resolución
```

**Flujo de workflow por alerta:**
```
open
  → [Gerente abre alerta]
in_review
  → [Confirma que debe facturarse / reclamarse] → confirmed
  → [Descarta — no aplica]                     → dismissed
confirmed
  → [Genera factura en CAFCA + registra número] → resolved
dismissed
  → (fin, queda documentado)
resolved
  → (fin, con resolution_notes + invoice_id opcional)
```

### 4.8 Tickets del sprint

| Ticket | Tarea | Dependencias |
|--------|-------|-------------|
| **BI-050** | Migración `intelligence_billing_alerts` | Sprint 1 completo |
| **BI-051** | `MonthlyBillingGuardianService` — estructura + `analyzeMonth` | BI-050 |
| **BI-052** | Regla: proyectos con actividad mensual sin invoice | BI-051 |
| **BI-053** | Regla: facturas vencidas + cobros parciales | BI-051 |
| **BI-054** | Regla: costes followup_cost no facturados | BI-051 |
| **BI-055** | Regla: proyectos cerrados con saldo pendiente + notas de crédito | BI-051 |
| **BI-056** | Comando Artisan `intelligence:billing-guardian` | BI-051→055 |
| **BI-057** | Scheduler mensual (día 2, 7:00) | BI-056 |
| **BI-058** | `MonthlyBillingControlPage` Filament V5 — KPIs + 5 tabs | BI-050 |
| **BI-059** | Workflow open → in_review → confirmed/dismissed → resolved | BI-058 |
| **BI-060** | `BillingGuardianRules` en `BiConfigPage` — min importes, días | Sprint 1 |
| **BI-061** | Tests con datos mirror (mocks de mirror tables) | BI-051→059 |
| **BI-062** | Documentar reglas en `docs/bi-monthly-billing-guardian.md` | — |

> **⚠️ AUDITOR GATE — obligatorio antes de cerrar BI-052, BI-053 y BI-054**
>
> Una alerta incorrecta puede provocar una factura duplicada o una omisión de cobro.
> Antes de marcar cualquiera de estos tres tickets como Done, presentar al auditor:
>
> **5 ejemplos reales por cada regla implementada** (extraídos de datos mirror reales):
>
> | # | Tipo | Qué mostrar |
> |---|------|-------------|
> | 1 | Caso A — debe disparar | Proyecto/factura + datos que cumplen la condición + alerta esperada |
> | 2 | Caso B — debe disparar | Segundo caso distinto (diferente severity, importe, antigüedad) |
> | 3 | Caso C — debe disparar | Tercer caso (edge del rango — justo por encima del threshold) |
> | 4 | Caso N — NO debe disparar | Proyecto/factura que tiene la forma pero no cumple la condición (exclusión explícita) |
> | 5 | Caso L — límite | Dato en el exacto umbral configurado — ¿dispara o no? Documentar el comportamiento |
>
> Ejemplo para BI-052 (`missing_customer_invoice`):
> - A: P20250063 Limburg — €20.643 costes mayo, última factura hace 132 días → debe disparar `high`
> - B: P20260029 Derriks — €5.600 costes mayo, nunca ha tenido factura → debe disparar `high`
> - C: Proyecto con €501 costes en el mes (justo por encima del threshold de €500) → debe disparar
> - N: P20170023 Stadsbader — €150 costes en mayo, ya tiene factura en abril (contrato marco, facturación diferida) → NO debe disparar si `days_since_invoice < config.billing_gap_days`
> - L: Proyecto con exactamente €500 costes (igual al threshold) → documentar si usa `>` o `>=`
>
> Sin estos 5 ejemplos validados por el auditor, el ticket **no puede cerrarse**.

---

## 5. Sprint 0 — Integración (1-2 días)

**Rama:** `feature/bi-foundation`

| Ticket | Tarea |
|--------|-------|
| BI-000 | Cherry-pick 3 commits de `BI` sobre `main` (`8d563e8`, `a8eedcf`, `5796a32`) |
| BI-001 | Verificar no-colisión de las 6 migraciones con `main` |
| BI-002 | Añadir mapeo `art_type` a `docs/ai/module-contracts.md` |

---

## 6. Sprint 1 — Motor de datos + config (1 semana)

**Rama:** `feature/bi-sprint1-data`

### 6.1 Ampliar mirrors existentes (campos faltantes críticos)

| Ticket | Tarea |
|--------|-------|
| **BI-010** | Migración: añadir `contract_price`, `type`, `state` a `intelligence_mirror_projects` |
| **BI-011** | Migración: añadir `invoiced` (boolean) a `intelligence_mirror_costs` + actualizar sync |
| **BI-012** | Migración: añadir `relation_id`, `date_expiration`, `fl_paid` a `intelligence_mirror_invoices` + sync |

### 6.2 Mirrors nuevos

| Ticket | Tarea |
|--------|-------|
| **BI-013** | Migración + sync `intelligence_mirror_estimate_calc` (factores MAMO de `estimate_calculation`) |
| **BI-014** | Migración + sync `intelligence_mirror_project_links` (`project_estimates`) |
| **BI-015** | Migración + sync `intelligence_mirror_project_results` (`rpt_project_results`) |
| **BI-016** | Migración + sync `intelligence_mirror_workdocs` (`workdoc`: id, project_id, relation_id, date, status, fl_invoice, fl_finished, fl_paid, total_price, total_paid) |

### 6.3 Config + sync

| Ticket | Tarea |
|--------|-------|
| **BI-017** | Migración `intelligence_bi_config` + seeder (5 entradas) |
| **BI-018** | `BiConfigService` — leer/escribir con cache Redis |
| **BI-019** | `BiConfigPage` Filament V5 — 5 secciones |
| **BI-020** | Warning en sync labor + respeta ventana de `bi_config.labor_sync_schedule` |
| **BI-021** | Tests: sync completo + counts + config service |

---

## 7. Sprint 2 — Motor Financiero (2 semanas)

**Rama:** `feature/bi-sprint2-engine`

| Ticket | Tarea |
|--------|-------|
| BI-030 | Migración `intelligence_price_indices` + seed NBB Belgium 2021-2026 |
| BI-031 | `InflationEngineService` |
| BI-032 | `ThreeVariantGeneratorService` — 3 variantes en 1 llamada Gemini; márgenes de `bi_config` |
| BI-033 | Integrar `estimate_item` histórico (tipos 1, 2, 5) en prompt |
| BI-034 | Integrar `estimate_calculation` en análisis MAMO histórico |
| BI-035 | Tests |

**Variantes (márgenes leídos de `bi_config.variant_margin_targets`):**

| Variante | Material | Labor | Margen (configurable) |
|----------|----------|-------|-----------------------|
| Económica | -10% | baseline | default 20% |
| Estándar | baseline | baseline | default 27% |
| Premium | +15% | +10% | default 35% |

---

## 8. Sprint 3 — UI Simulador + Exportación (2 semanas)

**Rama:** `feature/bi-sprint3-ui`

| Ticket | Tarea |
|--------|-------|
| BI-070 | `OfferSimulationResource` Filament V5 — lista + detalle |
| BI-071 | Comparativa 3 variantes side-by-side |
| BI-072 | Workflow: `draft → reviewed → approved → exported → won/lost` |
| BI-073 | Ampliar `intelligence_offer_simulations`: `approved_by/at`, `exported_at`, `cafca_ref`, `outcome`, `outcome_notes` |
| BI-074 | Export Excel (`maatwebsite/laravel-excel`) |
| BI-075 | Export JSON CAFCA-compatible |
| BI-076 | Warning badge si `project_type_labels` tiene nulls en `bi_config` |
| BI-077 | Tests |

---

## 9. Sprint 4 — Métricas + Memoria institucional (2 semanas)

**Rama:** `feature/bi-sprint4-metrics`

| Ticket | Tarea |
|--------|-------|
| BI-080 | Migración `intelligence_offer_patterns` |
| BI-081 | `OfferPatternService` — extrae patrones de proyectos cerrados |
| BI-082 | Integrar patrones en `BudgetAssistantService` |
| BI-083 | Win/loss tracking |
| BI-084 | Widget `OfferFunnelWidget` |
| BI-085 | Widget `EstimationAccuracyWidget` |
| BI-086 | Widget `OfferPerformanceWidget` |
| BI-087 | Tests |

---

## 10. Riesgos

| Riesgo | Severidad | Mitigación |
|--------|-----------|------------|
| Integración `BI`↔`main` (215 commits divergencia) | **ALTO** | Sprint 0 obligatorio |
| Alerta incorrecta en Billing Guardian → factura duplicada | **ALTO** | Auditor gate antes de BI-052/053/054 |
| `followup_labor_analytical` bloqueada en prod | MEDIO | Warning + ventana configurable en `bi_config` |
| `project_type_labels` / `estimate_status_labels` vacíos | BAJO | Fallback `"Tipo N"` + warning badge — no bloquea |
| Márgenes default (20/27/35%) fuera de realidad Claesen | BAJO | Editables en `BiConfigPage` |
| Stock eliminado — gerencia podría pedirlo | BAJO | Documentado: stale desde 2020 |

---

## 11. Comandos de referencia

```bash
# Sprint 0: integración
git checkout main
git cherry-pick 8d563e8 a8eedcf 5796a32

# Sync completo (solo en Sail)
./vendor/bin/sail artisan intelligence:sync-mirror --force

# Billing Guardian
php artisan intelligence:billing-guardian --previous-month
php artisan intelligence:billing-guardian --month=2026-05
php artisan intelligence:billing-guardian --dry-run

# Tests
php artisan test --testsuite=Modules --filter=Intelligence
```
