# Análisis y Plan Revisado: Simulador Inteligente de Ofertas
**Fecha:** 2026-05-26  
**Módulo objetivo:** `Modules/Intelligence/`  
**Autor del análisis:** Claude (basado en revisión completa del código y BD)

---

## Diagnóstico Inicial: Lo que ya existe vs. el plan

Antes de hablar de fases, hay que reconocer que **el simulador no empieza desde cero**. Ya existe una base funcional en `Modules/Intelligence/` que es mucho más avanzada de lo que puede parecer:

| Lo que el plan describe | Estado real en el código |
|---|---|
| Buscar proyectos similares | **YA EXISTE** — `ProjectSimilarityService` con ranking por keywords, penalización por escala |
| Stock/materiales disponibles | **PARCIALMENTE** — `MirrorMaterial` con AI tags, pero **sin cantidades** de stock |
| Motor de contexto histórico | **YA EXISTE** — Mirror de proyectos, costos, labor en MySQL |
| Llamada a IA | **YA EXISTE** — Gemini 2.0 Flash con schema MAMO |
| UI de simulación | **YA EXISTE** — `OfferSimulator.php` con tabs Finance/SWOT/CAME |
| Caché semántico (anti-spam de API) | **YA EXISTE** — MD5 fingerprint, 48h TTL |
| 3 variantes de oferta | **NO EXISTE** |
| Motor de inflación | **NO EXISTE** (hay un comentario hardcodeado "4% p.j.") |
| Exportación CAFCA | **NO EXISTE** |
| Lifecycle de simulación (draft → aprobado) | **NO EXISTE** |
| Tracking ganado/perdido | **NO EXISTE** |

---

## Problemas Estructurales Detectados en el Código Actual

Estos son problemas reales que afectan la calidad de las simulaciones hoy:

### 1. El gap más crítico: `estimate_item` no está en el mirror

La tabla `estimate_item` de SQL Server contiene las líneas originales de los presupuestos reales que Claesen construyó en CAFCA. Esto es el **training data más valioso** del sistema: aprender no solo "cuánto costó" sino **qué líneas tenía una oferta de tipo Sportverlichting**. Actualmente el mirror NO incluye esta tabla. Sin ella, la IA inventa estructuras de oferta en lugar de aprender de estructuras reales.

### 2. El stock tiene catálogo pero no cantidades

`intelligence_mirror_materials` sabe que existe el producto y su precio, pero **no sabe cuántas unidades hay en el almacén**. El simulador confunde "catálogo activo" (`fl_current`) con "en stock". Hay que auditar si SQL Server tiene una tabla de `stock` o inventario con cantidades físicas.

### 3. La simulación no tiene ciclo de vida

`intelligence_offer_simulations` guarda el resultado JSON pero no tiene:
- `status` (borrador, en revisión, aprobado, exportado)
- `client_id` (a qué cliente va dirigida)
- `approved_by` / `approved_at`
- `version` (para generar V2 de la misma oferta)

Esto hace imposible la revisión humana estructurada.

### 4. La llamada a Gemini hace demasiado en una sola petición

El prompt actual pide simultáneamente: MAMO breakdown, líneas de presupuesto, selección de materiales, SWOT, CAME, estrategia y análisis de riesgos. Esto:
- Es caro en tokens
- Genera outputs menos precisos (el modelo se "distrae")
- No permite reintentar solo una parte si algo falla

### 5. Desacople entre `MirrorMaterial` modelo y migración

La migración base crea campos básicos, pero el modelo y `BudgetAssistantService` usan `category_ai`, `tags`, `usage_summary`, `last_learned_at` — varios de estos no aparecen en el `$fillable` del modelo (`MirrorMaterial.php:12-21`). Esta inconsistencia es una fuente de bugs silenciosos.

---

## Mapa Completo de Tablas SQL Server Identificadas

Todas las siguientes tablas son **solo lectura** sin excepción:

```
project
  ├── id (string, no-incrementing)
  ├── name, type (int 0-8), relation_id
  ├── fl_active, fl_locked
  ├── ts_modif, ts_crea
  ├── estimated_total_hours_to_execute
  ├── contract_price
  └── yard_manager (FK → employee.id)

invoice
  ├── id (string, prefijo 'CN' = nota de crédito)
  ├── project_id, date
  ├── total_price_vat_excl, total_price, total_paid
  └── (balance = total_price - total_paid)

relation  (clientes)
  ├── id, zipcode, city
  └── (+ campos de contacto, VAT, idioma — no mapeados aún)

employee
  ├── id, name, functie (función/rol)
  ├── zip, city, country, street
  ├── fl_active, birthday
  ├── employment_date, termination_date
  └── costprice / hourly_rate

labor  (tipos/definiciones de trabajo — WU codes)
  ├── id, ref (ej: WU NORM, WU VERPL)
  └── descr_l1

followup_labor_analytical  (logs de horas reales)
  ├── seqnr (PK), project_id, employee_id, labor_id
  ├── hours, date
  └── labor_descr (permite filtrar: Werf, Laden, Mobiliteit)

followup_cost  (costos MAMO reales)
  ├── id, project_id, art_id (FK → material)
  ├── descr / name
  ├── price_type (MAMO: M/A/E/O)
  ├── costprice, quantity
  ├── extra_type (seguros, planos, etc.)
  ├── date, ts_crea
  └── invoice (boolean — clave para "Auditor de Olvidos")

material  (catálogo)
  ├── id, ref, descr_l1
  ├── costprice, date
  └── fl_current (activo en catálogo, ≠ en stock físico)

project_estimates
  ├── project_id
  └── estimate_id

estimate_item  ← NO MAPEADO AÚN (CRÍTICO)
  ├── estimate_id, (project_id implícito)
  ├── sequence/orden
  ├── type (titulo/subtitulo/partida/texto)
  ├── ref, description
  ├── quantity, unit, unit_price
  ├── total_hours, material_cost, labor_cost
  └── (columnas exactas pendientes de auditar)
```

**Posibles tablas no descubiertas aún (auditar):**
- `stock` / `warehouse` / `inventory` — cantidades físicas en almacén
- `invoice_line` — líneas de factura (necesaria para "Auditor de Olvidos")
- `workdoc` / `workdoc_labor` — documentos de trabajo (mencionado como fallback en PROJECT_CONTEXT.md)

---

## Plan por Fases Revisado

### Pre-fase obligatoria: Auditoría de Datos

**Objetivo:** Saber exactamente con qué datos reales contamos antes de construir nada.

**Tarea A — Auditar tablas desconocidas en SQL Server:**
```sql
-- Buscar tabla de stock
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_NAME LIKE '%stock%' 
   OR TABLE_NAME LIKE '%inventory%' 
   OR TABLE_NAME LIKE '%warehouse%'
   OR TABLE_NAME LIKE '%invoice_line%'
   OR TABLE_NAME LIKE '%workdoc%'

-- Confirmar columnas de estimate_item
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'estimate_item'
ORDER BY ORDINAL_POSITION

-- Confirmar columnas de relation (cliente)
SELECT COLUMN_NAME, DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'relation'
ORDER BY ORDINAL_POSITION
```

**Tarea B — Cuantificar calidad de datos históricos:**
```sql
-- ¿Cuántos proyectos tienen estimate_items?
SELECT COUNT(DISTINCT pe.project_id) 
FROM project_estimates pe
JOIN estimate_item ei ON pe.estimate_id = ei.estimate_id

-- ¿Cuántos proyectos tienen costos reales?
SELECT COUNT(DISTINCT project_id) FROM followup_cost

-- Distribución por categoría (type)
SELECT type, COUNT(*) FROM project GROUP BY type
```

---

### Fase 1: CAFCA Offer Schema (documento de referencia)

**Objetivo:** Crear `docs/cafca_offer_schema.md` con la estructura exacta que el simulador debe producir.

La fuente de verdad no son las imágenes de pantallas CAFCA sino los datos reales:
- `estimate_item` → estructura real de líneas de oferta históricas
- `followup_cost.extra_type` → categorías de costos extra no contempladas aún
- Tipos de línea reales: `titulo` (chapter), `subtitulo`, `partida` (item), `texto` (free text)

**Campos a documentar:**
```
Cabecera: número oferta, cliente (relation.id), fecha, validez (días), estado
Cliente: nombre, dirección, contacto, idioma, email, VAT
Proyecto: dirección de obra, distancia km, referencia
Parámetros MAMO: %M, %A, %E, %O (los 4 sliders de CAFCA Calculatie)
Líneas: tipo, secuencia, ref, descripción, cantidad, unidad, precio_unitario_material,
        precio_unitario_labor, horas_por_unidad
Textos legales: condiciones iniciales, texto intro, condiciones finales
Estado: borrador, enviado, aprobado, bloqueado, estadística
```

---

### Fase 2: Completar el Mirror de Datos

**Objetivo:** Asegurar que MySQL tiene toda la información necesaria para que la IA trabaje sin tocar SQL Server en tiempo real.

```
SQL Server (lectura) → Mirror MySQL

NUEVO intelligence_mirror_estimate_items
  project_id, estimate_id, sequence, line_type
  ref, description, quantity, unit
  unit_price_material, unit_price_labor, hours_per_unit
  → Añadir a SyncMirrorDataService::syncEstimateItems()

NUEVO intelligence_mirror_relations  (clientes)
  id, name, zipcode, city, country, language, vat_number
  → Añadir a SyncMirrorDataService::syncRelations()

NUEVO intelligence_mirror_stock  (si existe la tabla)
  material_id, qty_available, location, last_counted_at
  → Añadir a SyncMirrorDataService::syncStock()

EXISTENTE intelligence_offer_simulations  (ampliar)
  + status: enum(draft, reviewed, approved, exported, won, lost)
  + client_id: string (FK lógico → mirror_relations)
  + variant: enum(economy, standard, premium)
  + parent_simulation_id: bigint (para vincular las 3 variantes)
  + approved_by, approved_at, exported_at
  + cafca_ref: string (referencia asignada por CAFCA)
  + outcome_notes: text
```

**Corregir también:**
- `MirrorMaterial.$fillable` — añadir `category_ai`, `tags`, `usage_summary`, `last_learned_at`, `last_price_date`
- `MirrorEmployee` — añadir campo `hourly_cost` que actualmente no está en `$fillable`

---

### Fase 3: Motor de Análisis Histórico + Memoria Institucional

**Mejora al `ProjectSimilarityService` actual:**

Crear tabla `intelligence_offer_patterns` que captura patrones aprendidos automáticamente:

```sql
CREATE TABLE intelligence_offer_patterns (
  id bigint AUTO_INCREMENT PRIMARY KEY,
  category varchar(50),           -- Sportverlichting, Industrie, etc.
  scale_tier varchar(20),         -- small(<25k), medium(25-150k), large(>150k)
  typical_sections json,          -- secciones típicas con sus ítems frecuentes
  typical_mamo json,              -- ratios M/A/E/O medios reales históricos
  avg_margin_estimated decimal,   -- margen que se puso en la oferta
  avg_margin_real decimal,        -- margen que resultó de verdad
  win_rate decimal,               -- % ofertas ganadas en esta categoría/escala
  risk_flags json,                -- patrones de pérdida ("KNX programming siempre negativo")
  sample_project_ids json,        -- IDs de proyectos de referencia
  sample_count integer,           -- cuántos proyectos respaldan este patrón
  last_updated_at timestamp
);
```

Esta tabla se alimenta vía `AuditProjectJob` cada vez que un proyecto cierra. La IA la usa como "memoria institucional comprimida" para nuevas simulaciones.

**Añadir a la similaridad:** combinar `estimate_item` histórico con el análisis de similitud para devolver no solo métricas sino líneas típicas de oferta por categoría.

---

### Fase 4: Motor de Inflación

**Tabla:**
```sql
CREATE TABLE intelligence_price_indices (
  id bigint AUTO_INCREMENT PRIMARY KEY,
  year integer,
  month integer,
  index_type enum('general_cpi', 'steel', 'copper', 'cable', 'led_fixtures', 'labor_BE'),
  country_code varchar(5) DEFAULT 'BE',
  index_value decimal(8,4),       -- base 100 = enero 2020
  source varchar(100),            -- 'NBB', 'Eurostat', 'manual'
  INDEX idx_year_month (year, month),
  INDEX idx_type (index_type)
);
```

**Seed inicial:** datos NBB Belgium 2021–2026 cargados manualmente.  
**Actualización:** artisan command trimestral.

**Algoritmo `InflationEngineService`:**
```
1. Tomar last_price_date del material (o year del proyecto histórico)
2. Calcular meses transcurridos hasta hoy
3. Seleccionar índice apropiado por tipo de material:
   - Cable → index_type = 'copper' o 'cable'
   - Masten → index_type = 'steel'  
   - LED fixtures → index_type = 'led_fixtures'
   - Mano de obra → index_type = 'labor_BE'
   - Default → index_type = 'general_cpi'
4. factor = (index_value_hoy / index_value_fecha_origen)
5. precio_actualizado = precio_original * factor
```

---

### Fase 5: Motor de Stock

**Distinción importante:**

| Nivel | Qué es | Tabla actual | Estado |
|---|---|---|---|
| Catálogo activo | Materiales que Claesen compra habitualmente | `intelligence_mirror_materials` con `fl_active` | ✅ Existe |
| Stock físico | Unidades disponibles en almacén ahora mismo | Pendiente de auditar | ❌ Falta |

Si no existe tabla de stock en SQL Server, alternativa práctica: añadir a `intelligence_mirror_materials`:
```
estimated_stock_qty    integer  (actualizable manualmente)
last_stock_check_at   datetime
days_in_stock         integer  (calculado: hoy - last_price_date)
```

**Lógica de priorización en `StockSuggestionService`:**
```
Prioridad 1: Disponible en stock + días_en_stock > 180  (liquidar primero)
Prioridad 2: Disponible en stock + es producto activo
Prioridad 3: Producto activo en catálogo (pedido normal)
Prioridad 4: Producto discontinued → sugerir sustituto moderno
Prioridad 5: Búsqueda externa / presupuesto de mercado
```

**Output al gerente:**
```
Materiales aprovechables en stock:
├── 24 × Philips BVP518 LED (en stock 8 meses → priorizar liquidación)
├── 180 m cable 5G6 (stock normal)
└── 6 × driver Tridonic 100W (stock normal)

Impacto estimado:
├── Reducción compra externa: €8.400
├── Mejora de margen: +3.2%
└── Reducción lead time: 2 semanas
```

---

### Fase 6: Generador de 3 Variantes

**Cambio arquitectónico clave:** en lugar de 3 llamadas a Gemini (caro y lento), una sola llamada genera las 3 variantes.

**Perfiles MAMO por variante:**

| Variante | Material | Arbeid | Margin target | Extras |
|---|---|---|---|---|
| Económica | -15% (más stock, marcas B) | -5% | 18% | — |
| Estándar | baseline | baseline | 25% | — |
| Premium | +20% (mejores marcas, garantías) | +15% (horas de calidad) | 35% | Garantía 5 años, IoT monitoring, mantenimiento |

**Estructura de respuesta Gemini (3-en-1):**
```json
{
  "base_analysis": { "...análisis compartido...", "risk_flags": [] },
  "variants": {
    "economy":  { "projected_cost": 0, "breakdown": {}, "offer_lines": [], "notes": "" },
    "standard": { "projected_cost": 0, "breakdown": {}, "offer_lines": [], "notes": "" },
    "premium":  { "projected_cost": 0, "breakdown": {}, "offer_lines": [], "notes": "" }
  },
  "swot": { "strengths": [], "weaknesses": [], "opportunities": [], "threats": [] },
  "came_strategy": "..."
}
```

Las 3 simulaciones se guardan con el mismo `parent_simulation_id` y diferente `variant`.

---

### Fase 7: Revisión Humana (Filament V5)

**Nuevo recurso:** `Modules/Intelligence/Filament/Resources/OfferSimulationResource.php`

```
ListOfferSimulations
  → tabla: status badge, variant, client, projected_cost, created_at
  → filtros: status, category, date range
  → acciones: View, Approve, Export, Regenerate

ViewOfferSimulation
  → comparativa de las 3 variantes side-by-side
  → proyectos similares consultados con sus márgenes reales
  → material de stock utilizado vs compra externa
  → riesgos detectados por la IA
  → botones: Aprobar variante / Editar líneas / Regenerar / Exportar

Flujo de estados:
draft → (gerente revisa) → reviewed → (director técnico valida) → approved → (exportar) → exported
                                                                          ↓
                                                              (resultado real) → won / lost
```

---

### Fase 8: Exportación hacia CAFCA

**Camino 1 — MVP (2 semanas):** Excel estructurado

Usar `maatwebsite/laravel-excel` para generar un Excel con la estructura de importación de CAFCA.

```
Pestaña 1: Cabecera oferta (campos de offer_header)
Pestaña 2: Líneas (tipo, ref, descripción, qty, unidad, precio_mat, precio_arb, horas)
Pestaña 3: Parámetros MAMO (los 4 porcentajes para CAFCA Calculatie)
Pestaña 4: Textos legales (intro, condiciones, outro)
```

**Camino 2 — Avanzado:** JSON CAFCA-compatible

```json
{
  "cafca_import_version": "1.0",
  "generated_at": "2026-05-26T10:00:00Z",
  "generated_by": "AI Offer Simulator v2",
  "status": "DRAFT - Requires human validation before CAFCA entry",

  "offer_header": {
    "reference": null,
    "date": "2026-05-26",
    "validity_days": 30,
    "client_id": null,
    "project_type": "Sportverlichting"
  },

  "mamo_parameters": {
    "M_pct": 20,
    "A_pct": 80,
    "E_pct": 20,
    "O_pct": 0
  },

  "offer_lines": [
    {
      "sequence": 1,
      "type": "chapter",
      "description": "Lichtmasten en fundaties",
      "quantity": null,
      "unit": null,
      "unit_price_material": null,
      "unit_price_labor": null,
      "hours_per_unit": null
    },
    {
      "sequence": 2,
      "type": "partida",
      "description": "Lichtmast staal 8m",
      "ref": "MAT-001",
      "quantity": 6,
      "unit": "stk",
      "unit_price_material": 850.00,
      "unit_price_labor": 0,
      "hours_per_unit": 4.5,
      "source": "warehouse",
      "source_location": "Magazijn Balen"
    }
  ],

  "legal_texts": {
    "intro": "...",
    "conditions": "...",
    "outro": "..."
  },

  "simulation_metadata": {
    "similarity_references": [],
    "inflation_factor_applied": 1.094,
    "risk_flags": [],
    "ai_confidence_score": 0.82,
    "stock_items_used": 3,
    "stock_savings_eur": 8400
  }
}
```

> **Regla absoluta:** El JSON se exporta para importación manual. Nunca se inserta directamente en SQL Server aunque sea posible técnicamente. La restricción read-only es sagrada.

---

### Fase 9: Dashboard de Métricas

Extender el módulo `Performance` (que ya tiene widgets) en lugar de crear un dashboard separado.

**Nuevos widgets:**

```
OfferFunnelWidget
  Generadas → Revisadas → Aprobadas → Exportadas → Ganadas / Perdidas
  (visualización tipo funnel/embudo)

EstimationAccuracyWidget
  Para simulaciones con outcome 'won':
  simulation.projected_cost vs. real cost final del proyecto
  Error medio de estimación por categoría

StockImpactWidget
  Total ahorrado usando stock en ofertas aprobadas
  Materiales más frecuentemente aprovechados
  Stock crítico (> 365 días sin usar)

OfferPerformanceWidget
  Win rate por categoría
  Margen estimado vs. margen real (cuando existen datos)
  Tiempo medio desde simulación hasta aprobación
  Categorías más rentables
```

---

## Arquitectura Técnica Definitiva

```
Filament V5 (AdminPanelProvider)
    │
    ├── Modules/Intelligence/Filament/Pages/OfferSimulator.php      (input UI)
    └── Modules/Intelligence/Filament/Resources/OfferSimulationResource.php  ← NUEVO
              │
              ▼
    Modules/Intelligence/Services/BudgetAssistantService.php   (orquestador)
              │
    ┌─────────┴──────────────────────────────────────────────────┐
    │  Modules/Intelligence/Services/                            │
    │  ├── ProjectSimilarityService        ✅ (mejorar con RAG)  │
    │  ├── InflationEngineService          ❌ NUEVO              │
    │  ├── StockSuggestionService          ~50% (añadir qty)     │
    │  ├── ThreeVariantGeneratorService    ❌ NUEVO              │
    │  └── CafcaExportService              ❌ NUEVO              │
    └────────────────────────────────────────────────────────────┘
              │
    Modules/Intelligence/Jobs/GenerateBudgetSimulationJob (async)
              │
    ┌─────────┴──────────────────────────────────────────────────┐
    │  MySQL Mirror (lectura + escritura)                         │
    │  intelligence_mirror_projects           ✅                 │
    │  intelligence_mirror_employees          ✅                 │
    │  intelligence_mirror_labor_types        ✅                 │
    │  intelligence_mirror_labor              ✅                 │
    │  intelligence_mirror_materials          ✅ (falta qty)     │
    │  intelligence_mirror_costs              ✅                 │
    │  intelligence_mirror_invoices           ✅                 │
    │  intelligence_mirror_estimate_items     ❌ FALTA           │
    │  intelligence_mirror_relations          ❌ FALTA           │
    │  intelligence_mirror_stock              ❌ PENDIENTE       │
    │  intelligence_offer_patterns            ❌ FALTA           │
    │  intelligence_price_indices             ❌ FALTA           │
    │  intelligence_offer_simulations         ✅ (ampliar)       │
    │  performance_project_insights           ✅                 │
    └────────────────────────────────────────────────────────────┘
              │
    ══════════╪══════════════════════════════════════════════════
    SQL Server CLAESEN  ← SOLO LECTURA — JAMÁS ESCRIBIR
    ══════════╪══════════════════════════════════════════════════
              │
    project, invoice, relation, employee, labor,
    followup_labor_analytical, followup_cost,
    material, project_estimates, estimate_item,
    [stock?], [invoice_line?], [workdoc?]
```

---

## Plan de Sprints Recomendado (MVP Realista)

### Sprint 1 — Completar la Base de Datos (2 semanas)

- [ ] Auditar SQL Server: columnas exactas de `estimate_item`, buscar tabla stock/inventory
- [ ] Migración + sync de `intelligence_mirror_estimate_items`
- [ ] Migración + sync de `intelligence_mirror_relations`
- [ ] Ampliar `intelligence_offer_simulations` con campos de lifecycle (status, client_id, variant, parent_id, approved_by, etc.)
- [ ] Corregir `MirrorMaterial.$fillable` vs. columnas reales de la BD
- [ ] Corregir `MirrorEmployee.$fillable`

### Sprint 2 — Motor de Inflación + 3 Variantes (2 semanas)

- [ ] Crear `intelligence_price_indices` con seed manual NBB Belgium 2021–2026
- [ ] Crear `InflationEngineService`
- [ ] Crear `ThreeVariantGeneratorService` (3 variantes en 1 llamada Gemini)
- [ ] Separar la llamada Gemini en 2 fases: determinista (MAMO ratios) + creativa (líneas + estrategia)
- [ ] Integrar `estimate_item` histórico en el prompt para aprender estructura real de ofertas

### Sprint 3 — UI de Revisión + Exportación (2 semanas)

- [ ] Crear `OfferSimulationResource` en Filament V5 con lista + detalle
- [ ] UI de comparación de 3 variantes side-by-side
- [ ] Acciones: Aprobar / Rechazar / Regenerar con workflow de estados
- [ ] Export básico a Excel via `maatwebsite/laravel-excel`
- [ ] Export a JSON estructurado CAFCA-compatible

### Sprint 4 — Aprendizaje y Métricas (2 semanas)

- [ ] Crear `intelligence_offer_patterns` con job de actualización automática post-auditoría
- [ ] Win/loss tracking manual (gerencia marca resultado de la oferta)
- [ ] Widgets de métricas en Performance dashboard (funnel, accuracy, stock impact)
- [ ] Alimentar `ProjectSimilarityService` con datos de win rate por categoría

---

## Recomendación Estratégica

El plan es sólido y la visión correcta. Lo que cambia es el **punto de partida**: no estamos en cero, estamos al 40%. El simulador ya funciona y genera simulaciones reales.

**El riesgo mayor no es técnico sino de datos.** Si el mirror no incluye `estimate_item`, la IA no puede aprender la estructura real de una oferta CAFCA y seguirá inventando líneas en lugar de replicar patrones probados.

> **Regla de oro antes de cualquier nueva feature:** ejecutar el Sprint 1 de datos. Una vez que el mirror incluye las líneas históricas de presupuesto y los clientes, todo lo demás (inflación, variantes, exportación) se construye sobre tierra firme.

**Sobre CAFCA:** no intentar automatizar la inserción directa. El flujo correcto es:
```
AI genera borrador → gerente valida → exporta Excel/JSON → introduce en CAFCA manualmente
```
Cuando gerencia confíe en la precisión de las estimaciones (tras 3–6 meses de uso), se puede evaluar una integración más directa.
