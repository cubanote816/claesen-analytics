# SYSTEM INSTRUCTIONS & PROJECT CONTEXT (CAFCA INTELLIGENCE HUB)

> **⚠️ CRITICAL AI BEHAVIOR RULES:**
>
> 1.  **READ-ONLY LEGACY:** You are strictly FORBIDDEN from generating code that performs write operations (`save`, `update`, `create`, `delete`) on the `sqlsrv` connection. Always implement the `ReadOnlyTrait`.
> 2.  **FILAMENT V5 SYNTAX:** Do NOT use Filament V3/V4 classes. Use strictly `Filament\Schemas\Schema` for Forms and Infolists.
> 3.  **LANGUAGE PROTOCOL:**
>     - **Code/Variables/Comments:** English.
>     - **UI/Labels/Notifications/PDFs:** Dutch (Neerlandés) ONLY.
> 4.  **DATA INTEGRITY:** Never assume IDs are integers. Sanitize inputs (trim) from Legacy DB.

---

# Contexto Estratégico y Arquitectónico Integral: Claesen Verlichting

## 1. Resumen Ejecutivo y Alineación Estratégica

Este documento define la configuración del entorno para el desarrollo del **CAFCA Intelligence Hub**. El sistema es un puente técnico entre una operación industrial (Legacy ERP) y una capa de inteligencia de negocios (AI).

**Objetivo:** Crear un "Guardián del Flujo de Caja" y un "Auditor mediante IA" bajo una política de "Cero Complacencia".

**Stack Tecnológico:**

- **Backend:** Laravel 12 (PHP 8.4).
- **Frontend:** FilamentPHP V5 (Bleeding Edge).
- **Legacy DB:** SQL Server (ReadOnly).
- **App DB:** MySQL (Local Storage).
- **AI Engine:** Google Gemini 3 Flash.

## 2. Perfil Corporativo y Análisis del Dominio

### 2.1 Identidad

- **Empresa:** Claesen (BV).
- **Actividad:** Contratista de nicho en iluminación exterior e infraestructura.
- **Modelo:** "In eigen beheer" (Gestión propia integral: obra civil + eléctrica).

### 2.2 Unidades de Negocio (Modelo MAMO)

El sistema debe clasificar los proyectos según su naturaleza ("Aard"):

1.  **Infraestructura Deportiva (Sportverlichting):** Estadios (UEFA), Tenis, Padel.
2.  **Iluminación Industrial:** Aeropuertos, Puertos, Ferrocarriles (Alto riesgo/seguridad).
3.  **Monumental y Pública:** Estética, LED RGB, DMX.
4.  **Mástiles:** Telecom, Eólica.

**Modelo de Costos MAMO:**
La IA debe distinguir los costos en:

- **M**aterial
- **A**rbeid (Mano de Obra)
- **M**aterieel (Maquinaria)
- **O**nderaanneming (Subcontratas)

## 3. Puntos de Dolor y Lógica de Negocio

El sistema debe detectar y alertar sobre:

### 3.1 Patologías Financieras

1.  **WIP Traps (Trampas de Flujo de Caja):**
    - _Regla:_ Si (Costo Real - Facturado) > 2.500 € → ALERTA.
    - _Vacío 30 Días:_ Proyectos activos sin facturación en >30 días.
2.  **Auditor de Olvidos:** Materiales en `followup_cost` no reflejados en `invoice_line`.
3.  **Horas Quemadas:** Consumo de horas > Progreso físico.
4.  **Paradoja KNX:** Detección de servicios de programación con márgenes negativos históricos.
5.  **Stock Estancado:** Inventario sin rotación > 365 días.

### 3.2 Directivas Estratégicas

- **"Sell the Smoke":** Priorizar visualización inmediata (Dashboards) sobre perfección del backend.
- **Validación Pre-Mortem:** Comparar ofertas nuevas con 5 "Nearest Neighbors" históricos.
- **Soberanía Lingüística:** Todo output al usuario final en **Neerlandés**.

## 4. Arquitectura Técnica

### 4.1 Restricción de "Solo Lectura" (Safety First)

- **Namespace:** `App\Models\Cafca`
- **Trait Obligatorio:** `ReadOnlyTrait`
- **Comportamiento:** Interceptar `save()`, `update()`, etc., y lanzar `LogicException`.

### 4.2 Esquema Híbrido

- **Host (Legacy):** SQL Server. Datos transaccionales.
- **Parasite (App):** MySQL. Tabla `project_insights` y configuración de Filament.

## 5. Anatomía de Datos Legados (SQL Server)

### 5.1 Tablas Nucleares

- `project`: PK `id` (String/Non-incrementing). Banderas `fl_locked`, `fl_active`.
- `invoice`: Facturación. `total_price_vat_excl`.
- `relation`: Clientes.
- `employee`: Técnicos.

### 5.2 Tablas Analíticas (High Volume)

- `followup_labor_analytical`: Logs de horas. Crucial indexar por `project_id`.
- `followup_cost`: Materiales y externos. Columna crítica: `invoice` (boolean) para detectar fugas.

### 5.3 Fallback

Si `followup_labor_analytical` falla, reconstruir costos vía `workdoc` JOIN `workdoc_labor`.

## 6. Base de Datos Local (MySQL)

### Tabla `project_insights`

Almacena la inteligencia generada para evitar latencia.

- `project_id`: String (FK lógico).
- `insight_type`: Enum (pre-calc, post-mortem, audit).
- `efficiency_score`: 0-100.
- `critical_leak`: String.
- `golden_rule`: Text (AI Advice).
- `full_dna`: JSON Snapshot.

## 7. Servicios de IA (Prompts & Logic)

### 7.1 AuditProjectPrompt

- **Input:** Financieros + Presupuesto.
- **Output JSON:** `{ critical_leak, golden_rule, detailed_analysis_nl }`.

### 7.2 TechnicianAnalysisPrompt (RRHH)

- **Arquetipos:** The Sprinter, The Diesel, Road Warrior, Burnout Risk.

### 7.3 Cash Flow Watchdog

- Lógica determinista para alertas de facturación inmediata.

## 8. Hoja de Ruta de Implementación

1.  **Fase 1 (Cimientos):** Modelos Eloquent `CafcaModel` con `ReadOnlyTrait` y limpieza de IDs (trim).
2.  **Fase 2 (Cerebro):** Servicio `GeminiService` con DTOs optimizados para tokens.
3.  **Fase 3 (Motor RAG):** Jobs asíncronos con caché semántico (hash md5) para ahorrar costos de API.
4.  **Fase 4 (UI Filament V5):** Strict Schema implementation. UI en Neerlandés.

---

**FIN DEL CONTEXTO**
