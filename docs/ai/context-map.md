# Mapa de contexto — CAFCA Intelligence Hub

> Generado desde exploración real del código. Actualizar cuando cambie la arquitectura.
> Última actualización: 2026-06-02 (DOCS-AI-001 / CLA-105)

---

## Stack

| Capa | Tecnología | Notas |
|------|------------|-------|
| Backend | Laravel 12 / PHP 8.2+ | `laravel/framework ^12.0` |
| Admin UI | Filament V5 | `filament/filament ^5.0` — **solo V5, nunca V3/V4** |
| DB local | MySQL 8.4 | Base de datos principal del proyecto |
| DB legacy | SQL Server 192.168.254.102 | ReadOnly — conexión `sqlsrv` |
| Módulos | nwidart/laravel-modules ^12.0 | 8 módulos bajo `Modules/` |
| Auth | Laravel Sanctum + Azure OAuth | `socialiteproviders/microsoft-azure` |
| RBAC | spatie/laravel-permission ^6.x | Roles: `super_admin`, `project_manager` |
| IA | Google Gemini 1.5 Flash | Via `GeminiService` |
| Media | spatie/laravel-medialibrary ^11 | Conversiones WebP para Website |
| Infra | Docker Sail, Redis, Meilisearch | Redis: colas + cache |
| Email | Microsoft Graph (transporte actual) | Via `MicrosoftGraphMailer` |
| Contrato email | `App\Contracts\MarketingCampaignInterface` | Abstracción intercambiable |

---

## Módulos

### Cafca — `Modules/Cafca/`

**Propósito:** Modelos ReadOnly del ERP SQL Server legacy. Acceso de solo lectura.

**Modelos principales:**
- `CafcaModel` — base con `ReadOnlyTrait` (lanza `LogicException` si se intenta mutar)
- `Project` — proyectos del ERP
- `Labor` — horas de trabajo
- `Invoice` — facturas
- `Employee` — empleados
- `EstimateItem` — líneas de presupuesto
- `FollowupCost` — costos de seguimiento
- `LegacyEmployee`, `LegacyMaterial` — datos legacy
- `ProjectEstimate` — estimaciones de proyectos

**Servicios:**
- `CafcaSyncService` — orquesta sincronización general
- `EmployeeSyncService` — sync de empleados SQL Server → MySQL
- `InvoiceSyncService` — sync de facturas
- `PerformanceSyncMasterService` — sync maestro para Performance

**Regla crítica:** Los IDs del ERP son strings. Siempre `trim()` en modelos Cafca.

---

### Core — `Modules/Core/`

**Propósito:** Auth, RBAC, panel Filament V5.

**Componentes clave:**
- `Modules/Core/Services/Auth/` — Azure OAuth flow
- `Modules/Core/Http/Middleware/` — middlewares de auth
- `app/Providers/Filament/AdminPanelProvider.php` — configuración del panel Filament
- `app/Http/Middleware/SetPanelLocale.php` — locale NL/EN según navegador

**Roles RBAC:**
- `super_admin` — acceso total
- `project_manager` — acceso a sus propios recursos

---

### Intelligence — `Modules/Intelligence/`

**Propósito:** IA con Gemini, Mirror SQL→MySQL, Similarity, Budget Assistant.

**Servicios:**
- `GeminiService` — llamadas a Google Gemini 1.5 Flash con Semantic Cache (hash MD5)
- `BudgetAssistantService` — asistente de presupuesto con IA
- `ProjectSimilarityService` — nearest neighbors para proyectos similares
- `MaterialIntelligenceService` — inteligencia sobre materiales
- `SyncMirrorDataService` — copia SQL Server → MySQL local

**Commands:**
- `intelligence:sync-mirror` — sincroniza el mirror local
- `intelligence:build-material-brain` — construye índice de materiales
- `intelligence:map-warehouse-categories` — mapeo de categorías de almacén

**Patrón Semantic Cache:** hash MD5 del payload → evita llamadas redundantes a Gemini.

---

### Performance — `Modules/Performance/`

**Propósito:** Insights de proyectos, arquetipos de técnicos, Watchdog, SWOT.

**Modelos Mirror** (MySQL local, copia del ERP):
- `MirrorProject`, `MirrorCost`, `MirrorEmployee`, `MirrorInvoice`
- `MirrorLabor`, `MirrorLaborType`, `MirrorMaterial`

**DTOs:**
- `ProjectAiPayload`, `EmployeeAiPayload`, `GeminiContextDTO`

**Jobs:**
- `AuditProjectJob` — auditoría IA de proyecto
- `AnalyzeEmployeeJob` — análisis IA de técnico

**Commands:**
- `performance:sync-all` — sync completo de mirrors
- `performance:populate-project-insights` — genera insights de proyectos
- `performance:analyze-technicians` — analiza arquetipos de técnicos
- `performance:send-watchdog-report` — envía reporte Watchdog (lunes AM)

**Umbrales de negocio:**
- WIP Trap: (Costo Real − Facturado) > €2,500 → ALERTA
- Watchdog: threshold €20,000 (`WATCHDOG_IMMEDIATE_THRESHOLD`)
- Vacío 30 días: proyectos activos sin factura → alerta

---

### Prospects — `Modules/Prospects/`

**Propósito:** Fuente de verdad de audiencias. CRM, sync federaciones deportivas.

**Modelos:**
- `Prospect` — contacto/prospecto (fuente de verdad para audiencias Mailing)
- `ProspectLocation`, `Region`, `SyncHistory`

**Commands (sync federaciones):**
- `prospects:sync-master` — orquesta todos los syncs
- `prospects:sync-rbfa-graphql` — RBFA (fútbol belga)
- `prospects:sync-lbfa` — LBFA (fútbol belga francófono)
- `prospects:sync-aft` — AFT (tenis)
- `prospects:sync-hockey` — Hockey belga
- `prospects:sync-tpv`, `prospects:sync-val` — otras federaciones

**Jobs:**
- `ExecuteSyncJob`, `MasterSyncJob`, `SendMasterSyncFinishedNotificationJob`

**Relación con Mailing:** `mailing_messages.prospect_id` referencia `Prospect`. Mailing no duplica datos de contacto.

---

### Safety — `Modules/Safety/`

**Propósito:** Checklists de seguridad en obra, inspecciones, incidents, compliance.

**Modelos:**
- `Checklist` — plantilla de checklist
- `Question` — pregunta de checklist
- `Inspection` — inspección realizada
- `Answer` — respuesta con foto opcional
- `SafetyAdoptionEvent` — registros en bruto de eventos de adopción
- `SafetyAdoptionDailyRollup` — métricas agregadas diarias (MAU, fricción, etc)
- `SafetyEnabledUserSnapshot` — foto diaria del denominador de adopción

**Services:**
- `ComplianceService` — calcula estado de compliance (30 días)
- `SafetyAdoptionMetricsService` — agregador de adopción, MAU/DAU y purgador a 90 días

**Jobs:**
- `GenerateSafetyPdfJob` — genera PDF de inspección

**Commands:**
- `safety:check-compliance` — verifica compliance periódicamente
- `safety:aggregate-adoption` — agrega eventos de adopción diarios y purga historial

**Rutas API** (`/v1/safety/*`):
- `POST /v1/login`
- `GET /v1/safety/inspections` — paginado + filtros
- `POST /v1/safety/inspections` — crear inspección
- `GET /v1/safety/inspections/{id}` — detalle
- `GET /v1/safety/inspections/{id}/pdf` — descarga PDF
- `GET /v1/safety/inspections/{id}/answers/{answer}/photo` — foto
- `GET /v1/safety/checklists` — listar checklists
- `GET /v1/safety/compliance` — estado de compliance
- `GET /v1/safety/projects` — proyectos disponibles
- `GET /v1/safety/workers` — técnicos disponibles

**Política:** `InspectionPolicy` — `project_manager` solo ve sus propios recursos.

**Disco:** `config('safety.disk')` → `local` (privado, no público).

---

### Mailing — `Modules/Mailing/`

**Propósito:** Plataforma de campañas: templates, envío, tracking, supresión, A/B, follow-up, alertas.

**Modelos:**
- `Campaign` — campaña de email con workflow de estados
- `EmailTemplate` — plantilla versionada con categoría
- `CampaignMessage` — mensaje individual enviado
- `MessageEvent` — evento de tracking (append-only)
- `SuppressionEntry` — lista de supresión
- `TrackedLink` — link trackeado para click redirect
- `ContactPreference` — preferencias de categoría por prospecto
- `DeliverabilityAlert` — alertas de entregabilidad

**Enums:**
- `CampaignStatus`: `draft → review → approved → sending → completed|failed|cancelled`
- `MessageEventType`: `sent|delivered|opened|clicked|bounced_hard|bounced_soft|complained|unsubscribed`
- `SuppressionReason`: `unsubscribed|hard_bounce|spam_complaint|soft_bounce_limit|manual`
- `AudienceType`: `all_subscribed|segment|manual`
- `TemplateCategory`: `commercial|transactional`
- `FollowUpTrigger`: `clicked|not_clicked|opened|not_opened`
- `BounceClassification`: `HARD|SOFT|UNKNOWN`
- `DeliverabilityAlertType`: `hard_bounce_high|spam_complaint_high`

**Services:**
- `MicrosoftGraphMailer` — implementación actual de `MarketingCampaignInterface`
- `SaaSMailer` — stub para ESP externo futuro (MAI-026, bloqueado)
- `SuppressionService` — gestiona lista de supresión
- `SegmentResolverService` — resuelve audiencias dinámicas
- `BounceParserService` — parsea NDR bounces del inbox dedicado
- `PreferenceService` — gestiona preferencias de categoría
- `MicrosoftGraphService` — cliente Graph API

**Jobs:**
- `ExecuteCampaignJob` — ejecuta el envío de una campaña

**Commands:**
- `mailing:dispatch-scheduled` — despacha campañas programadas
- `mailing:parse-bounces` — parsea NDR del inbox configurado
- `mailing:ab-select-winner` — selecciona ganador de A/B test por CTR
- `mailing:dispatch-followups` — despacha follow-ups automáticos
- `mailing:check-deliverability-alerts` — verifica umbrales de entregabilidad

**Rutas web** (públicas):
- `GET/POST /prospects/unsubscribe/{prospect}/{token}` — página de baja
- `GET/POST /mailing/preferences/{prospect}/{token}` — preferencias de categoría
- `GET /mailing/track/open/{token}` — pixel de apertura
- `GET /mailing/track/click/{token}/{hash}` — redirect de clic

**Rutas API:**
- `POST /v1/unsubscribe-direct` — baja directa
- `POST /v1/mailing/unsubscribe/{prospect}/{token}` — RFC 8058 one-click

**Header de correlación:** `X-Mailing-Token` en todos los correos salientes para correlacionar NDR bounces.

---

### Website — `Modules/Website/`

**Propósito:** API pública para sitio Astro. Portfolio, consultas, media/WebP.

**Modelos:**
- `Project` — proyecto público con media (spatie/medialibrary)
- `ConsultationRequest` — solicitud de consulta pública
- `ConsultationActivity`, `ConsultationReminder`, `ConsultationNotification`
- `Message` — mensajes del formulario de contacto

**Services:**
- `PortfolioService` — lógica de portfolio público
- `ConsultationService` — gestión de consultas
- `WebsiteService` — servicio general

**Repositories:**
- `EloquentProjectRepository` — implementa `ProjectRepositoryInterface`
- `EloquentMessageRepository` — implementa `MessageRepositoryInterface`

**Jobs:**
- `NotifyAstroFrontendJob` — dispara `repository_dispatch` en GitHub (`backend_update`) para rebuild de Astro

**Observers:**
- `ProjectObserver`, `MediaObserver` — disparan `NotifyAstroFrontendJob` al guardar
- `ConsultationRequestObserver` — notificaciones al crear consulta

**Commands:**
- `website:regenerate-media` — backfill de conversiones WebP

**Rutas API** (`/v1/website/*`):
- `GET /v1/website/projects` — listado de proyectos públicos
- `GET /v1/website/projects/categories` — categorías disponibles
- `GET /v1/website/projects/years` — años disponibles
- `GET /v1/website/{slug}` — detalle de proyecto
- `POST /v1/website/consultations` — crear consulta

**Conversiones de media:**
- `thumb`: 300×200, WebP q85
- `optimized`: 1200×1200, WebP q80
- `gallery`: 1200×800, WebP q80

**Flujo deploy:**
```
Admin edita proyecto en Filament
  → ProjectObserver / MediaObserver
  → NotifyAstroFrontendJob (queue)
  → GitHub repository_dispatch: backend_update
  → GitHub Actions: npm run sync:prod → npm run build:prod → LFTP mirror
```

---

## Providers registrados

| Módulo | Provider |
|--------|---------|
| Cafca | `Modules\Cafca\Providers\CafcaServiceProvider` |
| Core | `Modules\Core\Providers\CoreServiceProvider` |
| Intelligence | `Modules\Intelligence\Providers\IntelligenceServiceProvider` |
| Performance | `Modules\Performance\Providers\PerformanceServiceProvider` |
| Prospects | `Modules\Prospects\Providers\ProspectsServiceProvider` |
| Safety | `Modules\Safety\Providers\SafetyServiceProvider` |
| Mailing | `Modules\Mailing\Providers\MailingServiceProvider` |
| Website | `Modules\Website\Providers\WebsiteServiceProvider` |
| App | `app/Providers/AppServiceProvider.php` |
| Filament | `app/Providers/Filament/AdminPanelProvider.php` |

---

## Dependencias cruzadas importantes

```
Mailing  ──────────────→  Prospects  (prospect_id, fuente de audiencias)
Performance  ──────────→  Cafca      (sync mirror desde SQL Server)
Intelligence  ─────────→  Cafca      (sync mirror desde SQL Server)
Cafca  ────────────────→  SQL Server (ReadOnly, nunca mutar)
Website  ──────────────→  GitHub     (repository_dispatch via job)
App\Contracts\MarketingCampaignInterface  ←  MicrosoftGraphMailer
                                          ←  SaaSMailer (stub futuro)
```

---

## Filament Clusters y Resources

```
app/Filament/Clusters/Website/
  ├── Resources/ConsultationRequestResource.php
  └── Resources/ProjectResource.php           ← edición de proyectos públicos

Modules/Safety/Filament/Resources/
  ├── ChecklistResource.php
  └── InspectionResource.php

Modules/Mailing/Filament/Resources/           ← gestión de campañas
Modules/Intelligence/Filament/                ← dashboard IA
Modules/Performance/Filament/                 ← dashboard Watchdog + insights
Modules/Prospects/Filament/Resources/         ← gestión de prospectos
```
