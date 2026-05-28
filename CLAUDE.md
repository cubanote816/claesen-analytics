# CAFCA Intelligence Hub — Guía para Claude

> Leer esto al inicio de cada sesión. Es la fuente de verdad del proyecto.

---

## Proyecto

**CAFCA Intelligence Hub** — sistema de inteligencia de negocio para Claesen Verlichting (BV), contratista belga de iluminación exterior. Conecta el ERP legacy (SQL Server, ReadOnly) con una capa analítica moderna sobre MySQL + IA.

**Objetivo:** "Guardián del Flujo de Caja" y "Auditor IA" bajo política de Cero Complacencia sobre riesgos financieros.

---

## Stack

| Capa | Tecnología |
|------|------------|
| Backend | Laravel 12 / PHP 8.2+ |
| Admin UI | Filament V5 (Bleeding Edge) |
| DB local | MySQL 8.4 |
| DB legacy | SQL Server 192.168.254.102 (ReadOnly) |
| Módulos | nwidart/laravel-modules ^12.0 |
| Auth | Laravel Sanctum + Azure OAuth (Microsoft Graph) |
| RBAC | spatie/laravel-permission |
| IA | Google Gemini 1.5 Flash |
| Infra | Docker Sail, Redis, Meilisearch |

---

## Restricciones críticas — NUNCA ignorar

1. **SQL Server es ReadOnly.** Jamás generar `save()`, `update()`, `create()`, `delete()` en conexión `sqlsrv`. Todos los modelos Cafca usan `ReadOnlyTrait`. Lanza `LogicException` si se intenta mutar.

2. **Filament V5 únicamente.** Usar `Filament\Schemas\Schema` para Forms e Infolists. NO clases de V3/V4.

3. **IDs nunca son enteros.** Los IDs del ERP legacy son strings. Siempre `trim()` en modelos Cafca.

4. **Idioma:** código/variables/comentarios en inglés. UI/labels/notificaciones en holandés (NL) para navegadores NL, inglés para el resto.

---

## Módulos

| Módulo | Descripción | Estado |
|--------|-------------|--------|
| **Cafca** | Modelos ReadOnly del SQL Server ERP (Project, Labor, Invoice, Employee…) | ✅ ~90% |
| **Core** | Auth (Azure OAuth + Laravel), RBAC Spatie, Filament V5 admin panel | ✅ ~95% |
| **Intelligence** | Gemini 1.5 Flash, Mirror SQL→MySQL, Similarity (Nearest Neighbors), Budget Assistant | ✅ ~90% |
| **Performance** | Project insights, arquetipos de técnicos, Watchdog (€20k), SWOT | ✅ ~85% |
| **Prospects** | Sync federaciones deportivas (RBFA, LBFA, AFT), CRM, campañas email | 🚧 ~75% |
| **Safety** | Checklists seguridad en obra, inspecciones, incidents — **sprint completado** | ✅ ~100% |
| **Mailing** | Plataforma de campañas: templates, eventos, supresión, tracking, compliance — **Fase 0+1 completadas** | ✅ ~80% |
| **Website** | Sitio público, formulario de consulta, galería proyectos — **sprint en curso** | 🚧 ~85% |

---

## Patrones arquitectónicos

- **Service Layer** — lógica de negocio en servicios (`GeminiService`, `ComplianceService`, etc.)
- **DTO Pattern** — normalización antes de enviar a IA (`ProjectAiPayload`, `GeminiContextDTO`)
- **ReadOnlyTrait** — bloqueo de mutaciones en modelos legacy
- **Mirror/Sync Pattern** — copia local de SQL Server en MySQL para queries analíticas
- **Semantic Cache** — hash MD5 de payload para evitar llamadas redundantes a Gemini

---

## Umbrales de negocio

- **WIP Trap:** (Costo Real − Facturado) > €2,500 → ALERTA
- **Watchdog:** threshold €20,000 (`WATCHDOG_IMMEDIATE_THRESHOLD`)
- **Vacío 30 días:** proyectos activos sin factura en >30 días → alerta
- **Safety compliance:** 30 días (`config('safety.compliance_days')`)
- **Report email:** orelvys.cuellar@claesen-verlichting.be (lunes por la mañana)

---

## Sprint Safety — COMPLETADO (rama: `Safety_Inspections`)

> Sprint cerrado el 2026-05-26. Todos los tickets en Done. Último commit: `93dfdd3`.

### Mapa SAF ↔ Linear — Estado final

| SAF | Linear | Título | Commit | Estado |
|-----|--------|--------|--------|--------|
| SAF-001 | CLA-5 | Configuración base config/config.php | 7e9958d | ✅ Done |
| SAF-002 | CLA-6 | InspectionPolicy — Autorización por recurso | 868ff60 | ✅ Done |
| SAF-003 | CLA-7 | Cambio de disco: fotos y PDFs a local privado | 3bf5408 | ✅ Done |
| SAF-004 | CLA-8 | Rutas web admin para servir archivos Filament | 1d36496 | ✅ Done |
| SAF-005 | CLA-9 | GET inspections/{id} — Detalle completo | a9638dc | ✅ Done |
| SAF-006 | CLA-10 | GET inspections/{id}/pdf — Descarga API | b0a7f40 | ✅ Done |
| SAF-007 | CLA-11 | GET answers/{id}/photo — Streaming seguro | cf77805 | ✅ Done |
| SAF-008 | CLA-12 | StoreInspectionRequest — Extracción validación | 4556064 | ✅ Done |
| SAF-009 | CLA-13 | index() — Paginación y filtros | e28ef5f | ✅ Done |
| SAF-010a | CLA-14 | ComplianceService + refactor command | 824c4aa | ✅ Done |
| SAF-010b | CLA-15 | GET /api/v1/safety/compliance | 93dfdd3 | ✅ Done |
| SAF-011 | CLA-16 | Factories + HasFactory en modelos Safety | 0ada386 | ✅ Done |
| SAF-012 | CLA-17 | Feature tests — Auth, Store e Index | cffee75 | ✅ Done |
| SAF-013 | CLA-18 | Feature tests — Show, PDF y Photo | a9638dc/b0a7f40/cf77805 | ✅ Done |
| SAF-014 | CLA-19 | Tests rutas web admin /safety/files/... | 3f07065 | ✅ Done |
| SAF-015 | CLA-50 | Incident type support | c1ed9fa | ✅ Done |
| SAF-016 | CLA-51 | ProjectController SQL Server → mirror fallback | dad5d70 | ✅ Done |

### Reglas Safety (no negociables)

- Disco: `config('safety.disk')` → valor `local`
- Autorización: `Gate::authorize()` por recurso, sin cambiar el padre del controller
- `project_manager` → solo recursos propios (`inspection.user_id === user.id`)
- `super_admin` → todos los recursos
- Tests y factories dentro de `Modules/Safety`

---

## Flujo de trabajo con Claude

Flujo obligatorio por ticket — no saltarse pasos:

1. Mover issue Linear a **In Progress**.
2. Presentar plan del ticket: alcance, archivos previstos, tests/checks.
3. **Esperar aprobación** antes de editar cualquier archivo.
4. Implementar solo el ticket activo.
5. Ejecutar tests/checks relevantes.
6. Presentar diff/resumen + criterios de aceptación cubiertos.
7. **Esperar GO técnico** del auditor.
8. Crear commit dedicado para ese ticket.
   - Formato: `SAF-XXX / CLA-YY: resumen corto`
   - No mezclar cambios de otros tickets salvo que estén declarados y aprobados.
9. Mostrar hash del commit.
10. Marcar issue Linear como **Done** con hash del commit en el comentario.
11. **No avanzar al siguiente ticket** sin confirmación explícita.

### Regla para cambios colaterales

Si durante un ticket aparecen cambios que pertenecen a otro ticket:
- No se mezclan silenciosamente.
- Documentar el cambio y su ticket de origen.
- Pedir decisión: mover a otro commit/ticket, incluir como dependencia aprobada, o revertir.

### Actualizar estado en CLAUDE.md

Usar la progresión: ⬜ Todo → 🚧 In Progress → ✅ Done

### Cómo reanudar una sesión nueva

```
"Continuamos con SAF-00X / CLA-X. Lee CLAUDE.md y docs/safety-sprint-linear-tickets.md."
```

---

## Tests

```bash
# Suite completa
php artisan test

# Solo módulos (añadido en MAI-020)
php artisan test --testsuite=Modules

# Un módulo concreto
php artisan test --testsuite=Modules --filter=Mailing
php artisan test --testsuite=Modules --filter=Safety

# Un archivo concreto
php artisan test Modules/Mailing/tests/Feature/CampaignWorkflowTest.php
```

`phpunit.xml` tiene suites `Unit`, `Feature` (raíz) y `Modules` (todos los `*Test.php` bajo `Modules/`).

---

## Estructura de módulo Safety

```
Modules/Safety/
├── config/config.php
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Models/
├── Policies/          ← se crea en SAF-002
├── Services/          ← se crea en SAF-010a
├── Database/
│   ├── Factories/     ← se crea en SAF-011
│   └── Migrations/
├── Jobs/
├── Filament/Resources/
└── Tests/Feature/     ← se crean en SAF-012/013/014
```

---

## Sprint Website — EN CURSO (rama: `website`)

> Sprint iniciado 2026-05-28. Documento de handoff: `docs/website-sprint-handoff.md`.

### Arquitectura Website

- **Backend:** `Modules/Website` — API REST en `/v1/website/*` (ProjectController + PortfolioService)
- **Frontend:** Astro en repo separado `cubanote816/website-claesen-v1`
- **Imágenes:** `spatie/laravel-medialibrary` → disco `public`, conversiones WebP (thumb, optimized, gallery)
- **Webhook:** `NotifyAstroFrontendJob` → GitHub repository_dispatch `backend_update` → `deploy.yml` rebuild
- **Sync:** `npm run sync:prod` en CI descarga imágenes de la API al directorio `public/v1-media/`

### Mapa WEB — Estado

| WEB | CLA | Título | Commit | Estado |
|-----|-----|--------|--------|--------|
| WEB-001 | CLA-90 | Fix event_type mismatch (update_portfolio → backend_update) | 132f98c | ✅ Done |
| WEB-002 | CLA-91 | Fix repositorio is_published → published, eliminar filtro published_at | 141c3ab | ✅ Done |
| WEB-003 | CLA-92 | Fix path duplication v1-media/v1-media en GitHub Actions | 7b2b28f (frontend) | ✅ Done |
| WEB-004 | CLA-93 | Fix errores LFTP (sftp:chmod-ignore, \|\| true) | 7b2b28f (frontend) | ✅ Done |
| WEB-005 | CLA-94 | Add .format('webp') a conversiones gallery y thumb | 2868699 | ✅ Done |
| WEB-006 | CLA-95 | Servir URLs WebP en atributos API (optimized key en api_gallery) | 2868699 | ✅ Done |
| WEB-007 | CLA-96 | Backfill: website:regenerate-media command | 90cc01b | ✅ Done |

### Backfill a ejecutar en producción

```bash
php artisan website:regenerate-media
# Opciones:
php artisan website:regenerate-media --collection=gallery
php artisan website:regenerate-media --collection=featured_image
php artisan website:regenerate-media --project=<id>
```

### Cómo reanudar

```
"Continuamos con WEB-XXX / CLA-Y. Lee CLAUDE.md y docs/website-sprint-handoff.md."
```

---

## Sprint Mailing — COMPLETADO Fase 0+1 (rama: `feature/mailing-platform`)

> Fase 0+1 cerradas el 2026-05-29. PR: #1. Documento maestro: `docs/Mailing/mailing-platform-master.md`.

### Decisiones arquitectónicas fijadas

- **Transporte:** Microsoft Graph (Fase 1) → ESP externo configurable (Fase futura)
- **DB:** MySQL 8.4 (no PostgreSQL — cross-join con `prospects_prospects`)
- **KPI principal:** clics y CTR, no aperturas (Apple MPP invalida open rate)
- **Audiencias:** `Modules/Prospects` es fuente de verdad. Mailing solo referencia `prospect_id`.

### Mapa MAI — Estado

| Fase | Tickets | Estado |
|------|---------|--------|
| **Fase 0** — Consolidación | MAI-001 a MAI-005 | ✅ Done |
| **Fase 1** — MVP Robusto | MAI-006 a MAI-020 | ✅ Done |
| **Fase 2** — Automatización | MAI-021 a MAI-027 | ⬜ Backlog |
| **Fase 3** — Inteligencia | MAI-031 a MAI-036 | ⬜ Backlog |

### Mapa MAI Fase 0+1 — Estado final

| MAI | CLA | Título | Commit | Estado |
|-----|-----|--------|--------|--------|
| MAI-001 | CLA-99 | Campaign model + migración prospect_mail_campaigns → mailing_campaigns | 04950fc | ✅ Done |
| MAI-002 | CLA-100 | CampaignMessage model + migración prospect_mail_logs → mailing_messages | dd53571 | ✅ Done |
| MAI-003 | CLA-101 | ExecuteCampaignJob en Modules/Mailing + fix sleep(1) | 2f25fe8 | ✅ Done |
| MAI-004 | CLA-102 | Filament CampaignResource → Modules/Mailing | 38095b2 | ✅ Done |
| MAI-005 | CLA-103 | Limpiar Prospects — eliminar modelos/job/resources movidos a Mailing | ffa8bf4 | ✅ Done |
| MAI-006 | CLA-104 | config/config.php + documento maestro Mailing | bdd80b1 | ✅ Done |
| MAI-007 | CLA-105 | Migración mailing_suppression_list | f1e3bec | ✅ Done |
| MAI-008 | CLA-106 | SuppressionEntry model + SuppressionService + check en job | 9094d40 | ✅ Done |
| MAI-009 | CLA-107 | Migración mailing_message_events | eaa69bd | ✅ Done |
| MAI-010 | CLA-108 | MessageEvent model + MessageEventType enum | fd28502 | ✅ Done |
| MAI-011 | CLA-110 | EmailTemplate — category, variables, version | 8360a6c | ✅ Done |
| MAI-012 | CLA-111 | Campaign workflow — estados, aprobación, transiciones | 3699f04 | ✅ Done |
| MAI-013 | CLA-112 | Tracking pixel apertura (GET /mailing/track/open/{token}.gif) | 7ac8c27 | ✅ Done |
| MAI-014 | CLA-113 | Tracking click redirect + mailing_tracked_links | 7ac8c27 | ✅ Done |
| MAI-015 | CLA-109 | Headers List-Unsubscribe + endpoint one-click RFC 8058 | 742dba0 | ✅ Done |
| MAI-017 | CLA-114 | CampaignPolicy — RBAC por recurso | 7c79260 | ✅ Done |
| MAI-018 | CLA-115 | Filament Campaign management (create, review, approve, view) | b6d4914 | ✅ Done |
| MAI-019 | CLA-116 | Dashboard métricas básicas (StatsOverviewWidget) | 183e2e3 | ✅ Done |
| MAI-020 | CLA-117 | Feature tests Fase 1 (36 tests) | 3189ccd | ✅ Done |

### Arquitectura Mailing

- **Transporte:** `MarketingCampaignInterface` → `MicrosoftGraphMailer` (intercambiable)
- **Workflow:** `draft → review → approved → sending → completed|failed|cancelled`
- **Supresión:** `mailing_suppression_list` — permanente para `hard_bounce` y `spam_complaint`
- **Tracking:** pixel apertura + click redirect vía `mailing_tracked_links`
- **Eventos:** `mailing_message_events` append-only (KPI: clics únicos, CTR, CTOR)
- **Compliance:** `List-Unsubscribe` + `List-Unsubscribe-Post` en todo correo comercial

### Reglas Mailing (no negociables)

- Transporte siempre via `MarketingCampaignInterface` — nunca `MicrosoftGraphMailer` directo
- `mailing_message_events` es append-only — no se editan eventos registrados
- `spam_complaint` y `hard_bounce` son permanentes — solo `super_admin` puede levantar
- Sin aprobación (`status !== approved`) el job lanza `DomainException`
- Aperturas no son KPI — siempre usar CTR/CTOR como criterio de éxito
- `List-Unsubscribe` obligatorio en todo correo comercial (exento: transaccional)

### Migraciones a ejecutar en producción

```bash
php artisan migrate
# Tablas afectadas:
# mailing_campaigns (rename + approved_by, approved_at, template_id, status ENUM)
# mailing_messages (rename + tracking_token)
# mailing_suppression_list (nueva)
# mailing_message_events (nueva)
# mailing_tracked_links (nueva)
# email_templates (category, variables, version, parent_id, created_by)
```

### Cómo reanudar (Fase 2)

```
"Continuamos con MAI-02X / CLA-Y. Lee CLAUDE.md y docs/Mailing/mailing-platform-master.md."
```
