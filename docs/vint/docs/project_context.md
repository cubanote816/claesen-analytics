# CAFCA INTELLIGENCE HUB - Project Context

## 1. Project Overview

Stand-alone Business Intelligence (BI) and AI tool for "Claesen Verlichting".
**Goal:** Cost optimization, invoicing control, and data-driven budgeting.
**System Role:** Read-only access to legacy SQL Server ("Cafca"), business logic processing, and AI analysis (Google Gemini).

## 2. Tech Stack

-   **Backend:** Laravel 11 (PHP 8.4).
-   **Frontend/Admin:** FilamentPHP v3 (Admin Panel & Dashboards).
-   **Environment:** Docker (Laravel Sail) on WSL2 (Ubuntu 24.04).
-   **Source Database:** Microsoft SQL Server (`sqlsrv` driver).
-   **AI Engine:** Google Gemini 1.5 Flash (via API).

## 3. Infrastructure Status

-   **Cafca Connection:** Configured as `'sqlsrv'` pointing to `CLAESEN` database.
-   **Data Safety:** `App\Traits\ReadOnlyTrait` implemented to block write operations on external tables.
-   **Models:** Located in `App\Models\Cafca\` using connection `sqlsrv`.

## 4. Data Structure (CONFIRMED SCHEMA)

-   **`project`**: Main projects table.
    -   PK: `id` (string/nvarchar, NOT auto-incrementing).
    -   Key columns: `descr` (Description), `name`, `status`, `rel_code`.
-   **`invoice`**: Sales invoices.
    -   FK: `project_id` links to `project.id`.
-   **`followup_cost`**: Real costs (Labor + Materials).
    -   FK: `project_id` links to `project.id`.
    -   Key columns: `costprice`, `total_costprice`.
-   **`material`**: (To be verified) Likely catalog of items.

## 5. Roadmap

### Phase A: "Cash Flow Watchdog"

-   **Logic:** Alert on unbilled work (`Nacalc`) exceeding threshold (> €2.500) or time limit.

### Phase B: AI Auditor

-   **Logic:** Compare project purchases vs. sales invoices to detect margin leaks.

## 6. Coding Standards & AI Guidelines (CRITICAL)

1.  **Language (Code):** All code (variables, methods, classes) and **comments** MUST be in **English**.
2.  **Language (UI):** The interface must support **English** and **Dutch (Nederlands)**. Never hardcode strings in Blade/Filament. Use Laravel's localization helpers (`__('messages.welcome')` or `Translatable` traits).
3.  **Language (AI Output):**
    -   **Development (`APP_ENV=local`):** AI analysis/insights should be generated in **Spanish** (for developer review).
    -   **Production (`APP_ENV=production`):** AI analysis must match the **current user's interface language** (English or Dutch).
4.  **Safety First:** NEVER generate code that attempts to write (`create`, `update`, `delete`) to the `sqlsrv` connection.

## 7. Architecture (Clean Architecture & SOLID)

### A. Application Layers (Separation of Concerns)

1.  **Domain Layer (Models):** `App\Models\Cafca\`
    -   Responsible for Eloquent relationships and scopes only.
    -   Must use `ReadOnlyTrait`.
2.  **Service Layer (Use Cases):** `App\Services\`
    -   Contains pure business logic (e.g., `CashFlowService`, `GeminiAnalysisService`).
    -   Orchestrates data retrieval and AI prompt generation.
    -   **AI Prompt Strategy:** The service method asking for AI analysis must accept a `$targetLanguage` parameter.
        -   Logic: `$lang = app()->isLocal() ? 'es' : app()->getLocale();`
3.  **Interface Layer (Filament Resources):** `App\Filament\Resources\`
    -   Responsible for display and user input only.
    -   Controllers/Resources must be "skinny" and inject Services.

### B. SOLID Principles

-   **S:** Single Responsibility.
-   **O:** Open/Closed (AI strategies should be extendable).
-   **D:** Dependency Injection (Inject services, don't use `new`).

### C. Localization Strategy

-   Use Laravel's `lang/en` and `lang/nl` directories.
-   In Filament resources, use `->translateLabel()` or `__('...')`.

## 8. Localization Policy (STRICT)

-   **Default Locale:** `nl` (Nederlands).
-   **Fallback Locale:** `nl`.
-   **Detection:** 1. Browser Language, 2. Default (NL).
-   **Format:** Flat PHP array files with dot notation keys (e.g., 'status.label' => 'Status').
-   **Storage:** `lang/nl/`, `lang/en/`, `lang/es/`.

### 9. REGLAS DE COMUNICACIÓN EXTERNA (PDF & EMAIL)

-   Todos los documentos generados (PDF) y notificaciones (Email) dirigidos a la gerencia DEBEN estar exclusivamente en **Neerlandés (NL)**.
-   El PDF debe usar las traducciones de `lang/nl/common.php`.
-   Las notificaciones de alerta (Margen Crítico > 90%) deben usar plantillas en neerlandés.

## [2026-01-08] Dutch-Only External Communication

-   All automated emails and generated PDFs MUST be in Dutch (NL) [cite: 2026-01-08].
-   The system must use `App::setLocale('nl')` for these tasks to ensure the manager receives professional local documentation [cite: 2026-01-08].
