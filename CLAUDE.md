# CAFCA Intelligence Hub — Guía para Claude

> Leer esto al inicio de cada sesión. Es la fuente de verdad del proyecto.

---

## AI Harnesses

Reglas de arranque persistentes: `AGENTS.md` y `.agents/rules/00-project-startup.md`.

Al iniciar cada sesión, leer en este orden:

1. `CLAUDE.md` — este archivo (reglas permanentes, estado macro)
2. `handoff.md` — estado global vivo (sprint activo, último ticket, bloqueantes)
3. `docs/ai/README.md` — índice de harnesses y qué documento leer según la tarea
4. Documento específico del módulo activo

### Índice de harnesses (`docs/ai/`)

| Archivo | Propósito |
|---------|-----------|
| `README.md` | Índice completo y guía de lectura por tipo de tarea |
| `project-protocol.md` | Flujo obligatorio: ticket → plan → aprobación → implementar → commit → GO |
| `context-map.md` | Mapa real del proyecto: stack, módulos, rutas, jobs, dependencias |
| `module-contracts.md` | Reglas no negociables por módulo (Mailing, Safety, Website, Cafca…) |
| `testing-checklists.md` | Qué testear según el tipo de cambio; comandos de test por módulo |
| `production-readiness.md` | Checklist de staging y producción; migraciones, scheduler, smoke tests |
| `code-review-rubric.md` | Cómo revisar un PR: prioridades, severidades, reglas por módulo |
| `known-risks.md` | Riesgos abiertos, deuda técnica, bloqueantes y decisiones pendientes |
| `prompt-templates.md` | Prompts reutilizables para las tareas más comunes |
| `commands-runbook.md` | Todos los comandos Artisan con descripción y notas operativas |
| `handoff-strategy.md` | Cómo mantener y usar `handoff.md` y los documentos de módulo |

---

## Regla operativa clave

Todo cambio debe pasar por Linear.
No se edita código sin ticket activo, plan presentado y aprobación explícita.
Cada ticket debe terminar con tests relevantes, actualización de `CLAUDE.md` y `handoff.md`, commit Git dedicado y GO técnico del usuario.

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

5. **`project_manager` no tiene acceso al panel Filament** (CLA-205, 2026-07-03). `User::hasPanelAccess()` es la fuente única de verdad; `canAccessPanel()` sigue permitiendo el login (solo mira `is_active`) y el gate real lo aplica el middleware `EnsurePanelAccess`, que redirige a `/auth/no-access` (página de bienvenida propia) en vez de usar el 403 nativo de Filament. No volver a agregar `project_manager` a ningún `canAccess()` de recurso/página del panel — usan las PWA de Safety/FieldOps, no este backoffice.

6. **Sesión expirada (419) usa modal branded, no el `confirm()` nativo de Livewire** (CLA-208, 2026-07-04). Interceptado vía `Livewire.hook('request', ({fail}) => fail(({status, preventDefault}) => {...}))` en `Modules/Core/resources/views/filament/session-expired-modal.blade.php`, enganchado al `PanelsRenderHook::BODY_END` de `AdminPanelProvider.php`. No revertir a dejar pasar el 419 sin `preventDefault()` — el `confirm()` del vendor volvería a dispararse. Si se agregan clases Tailwind arbitrarias nuevas en esta u otras vistas, correr `npm run build` local para verlas (no afecta producción, `deploy.sh` ya lo hace).

---

## Módulos

| Módulo | Descripción | Estado |
|--------|-------------|--------|
| **Cafca** | Modelos ReadOnly del SQL Server ERP (Project, Labor, Invoice, Employee…) | ✅ ~90% |
| **Core** | Auth (Azure OAuth + Laravel), RBAC Spatie, Filament V5 admin panel, user provisioning (USR-001) | ✅ ~98% |
| **Intelligence** | Gemini 1.5 Flash, Mirror SQL→MySQL, Similarity (Nearest Neighbors), Budget Assistant | ✅ ~90% |
| **Performance** | Project insights, arquetipos de técnicos, Watchdog (€20k), SWOT | ✅ ~85% |
| **Prospects** | Sync federaciones deportivas (RBFA, LBFA, AFT), CRM, campañas email | 🚧 ~75% |
| **Safety** | Checklists seguridad en obra, inspecciones, incidents — **sprint completado** | ✅ ~100% |
| **Mailing** | Plataforma de campañas: templates, eventos, supresión, tracking, compliance, automatización — **Fase 0+1+2 completadas** | ✅ ~98% |
| **Website** | Sitio público, formulario de consulta, galería proyectos — **sprint en curso** | 🚧 ~85% |
| **FieldOps** | Gestión de complejos deportivos, terrenos, estructuras, luminarias (reemplazo del satélite `api-claesen-sport-app`) — **menú marcado "(Demo)", gaps abiertos, sin consumidor conectado** | 🚧 ~70% |

---

## Patrones arquitectónicos

- **Service Layer** — lógica de negocio en servicios (`GeminiService`, `ComplianceService`, etc.)
- **DTO Pattern** — normalización antes de enviar a IA (`ProjectAiPayload`, `GeminiContextDTO`)
- **ReadOnlyTrait** — bloqueo de mutaciones en modelos legacy
- **Mirror/Sync Pattern** — copia local de SQL Server en MySQL para queries analíticas
- **Semantic Cache** — hash MD5 de payload para evitar llamadas redundantes a Gemini
- **Azure-first provisioning** — `User.hasCompletedPasswordSetup()` = canónico; activación vía código opaco one-time (no bearer en URL); `EnsurePasswordIsSet` bloquea panel y API hasta completar setup

---

## Sprint User Provisioning — USR-001 / CLA-171 (rama: FieldOps)

> Ticket A (backend) completado: 2026-06-23. Commit: `a2846ea`.
> **Ticket B pendiente:** Safety PWA (`safety_claesen`) debe manejar `?activation_code=xxx&setup_required=true` antes de activar en producción.

### Reglas User Provisioning (no negociables)

- `hasCompletedPasswordSetup()` en `User.php` es el único punto de verdad — no duplicar la lógica
- `employee_id` en `users` es una referencia blanda a MySQL mirror (no FK de DB) — validar existencia en app layer
- Bearer token **nunca** en URL — el código de activación es opaco y solo sirve para el canje por POST
- `EnsurePasswordIsSet` excluye `/auth/setup-password` web, `POST /api/v1/auth/activate`, `POST /api/v1/auth/setup-password` y `POST /api/v1/auth/logout`
- Canje de código: `lockForUpdate()` obligatorio — dos requests concurrentes no pueden emitir dos tokens
- `syncRoles()` debe estar dentro del mismo `DB::transaction()` que `User::create()`

### Backfill en producción

```bash
php artisan core:link-users-to-employees --dry-run   # preview sin escrituras
php artisan core:link-users-to-employees --apply     # solo después de revisar el dry-run
```

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

## Sprint FieldOps — EN CURSO (rama: `main`, menú "(Demo)")

> Auditoria comparativa 2026-07-03 contra el satélite anterior `api-claesen-sport-app`. Ver `handoff.md` para el detalle completo.

### Estado

`fo_admin` (Slices C.1→C.6a) ya está mezclado en `main` y `origin/main`. El menú "Field Operations" en Filament está marcado **"(Demo)"** (`lang/en,nl/navigation.php`, clave `navigation.groups.field_operations`) porque el módulo no tiene ningún consumidor real conectado todavía y le faltan dominios completos frente al sistema anterior.

### Reglas FieldOps (no negociables)

- Tablas con prefijo `fo_`. `created_by_user_id` siempre `nullOnDelete` (no `cascadeOnDelete`) — borrar un usuario de Core no debe borrar datos operacionales.
- Traducciones: `spatie/laravel-translatable` (columnas json) + `HasAiTranslations` propio (`Modules/Intelligence`) para autotraducir con Gemini. Locales canónicos: **`nl, en, fr, de`** — no usar `es` (bug corregido en FO-008).
- `LuminaireGroup` está intencionalmente denormalizado como `group_name` string en `fo_luminaire_subgroups` — no crear una tabla catálogo separada sin discutirlo primero (decisión de Slice C).
- `ComplexZoomLevel` (zoom por usuario) está intencionalmente colapsado a un único campo `zoom` en `Complex` — no revertir a zoom por usuario sin justificación de negocio.
- `Access`/`Safety` de estructura están denormalizados como columnas planas en `fo_structures` (`access_type_id`, `access_active`, `safety_type_id`, `safety_certified`) en vez de tablas de instancia separadas — mismo precedente que `LuminaireGroup` (relación 1:1 por estructura, nunca reutilizada). Catálogos `AccessType`/`SafetyType` sí son tablas propias (`super_admin` only).
- `ElectricalBoard` (`fo_electrical_boards`) SÍ usa 3 tablas pivot reales (`fo_complex_electrical_board`, `fo_electrical_board_terrain`, `fo_electrical_board_structure`, todas con FK `cascadeOnDelete`) porque un cuadro eléctrico puede compartirse entre múltiples complejos/terrenos/estructuras — no es 1:1 como Access/Safety, así que aquí sí aplica tabla de instancia (pivot) en vez de denormalizar.
- Adjuntos (fotos/PDFs) de `Complex`/`Terrain`/`Structure`/`ElectricalBoard` usan `spatie/laravel-medialibrary` con **disco privado `local`** (mismo `storage_path('app/private')` que `Modules/Safety`, no el disco `public` por defecto de la librería). Colecciones `photos`/`documents` vía trait compartido `HasFieldOpsMedia` — al añadir el trait a un modelo nuevo, resolver el conflicto de métodos con `InteractsWithMedia` usando `insteadof` (ver cualquiera de los 4 modelos existentes como ejemplo). Servir/subir siempre vía `FieldOpsMediaController` (genérico, no crear controllers de media por entidad).
- **El dominio de Mantenimiento de luminarias (`TypeMaintenance`/`MaintenanceServicesHistory`) SÍ está en uso real en producción** (confirmado directamente por el usuario, 2026-07-04) — no era código muerto del sistema anterior. Implementado en FO-009 como `FoMaintenanceType`/`FoMaintenanceRecord` (ver detalle en la sección FieldOps más abajo). `ScheduledMaintenanceService`/`Task` quedaron fuera de alcance a propósito (sin evidencia de uso real).

### Gaps abiertos (tickets Linear, equipo Claesen)

| Ticket | Título | Estado |
|--------|--------|--------|
| FO-008 / CLA-206 | Fix locale es→de en validación FieldOps | ✅ Done (`6a831e9`) |
| FO-004 / CLA-207 | Slice E — Access/Safety de fijación de estructura | ✅ Done (`4f6d1c5`) |
| FO-003 / CLA-209 | Slice D — Electrical Board (dominio completo) | ✅ Done (`603baf7`) |
| FO-005 / CLA-210 | Slice F — Adjuntos de archivos/planos (Media Library) | ✅ Done (`f80e0cb`) |
| FO-007 / CLA-212 | Spike — evaluar alcance del dominio de Mantenimiento | ✅ Done — **está vivo en producción**, no se cierra como N/A |
| FO-009 / CLA-213 | Slice G — Dominio de Mantenimiento de luminarias (implementación real) | ✅ Done — `FoMaintenanceType` (catálogo) + `FoMaintenanceRecord` polimórfico (Luminaire\|ElectricalBoard) + subdominio cliente-reportado. Excluido a propósito: `ScheduledMaintenanceService`/`Task` (sin evidencia de uso real, ver detalle abajo) |
| FO-006 | Slice C.6b — Cutover: frontend Sport → Core, deprecar Sport | ⬜ Todo (ya no bloqueado por la parte de Mantenimiento cubierta en FO-009; si el cutover necesita mantenimiento *programado* a futuro, abrir ticket nuevo para `ScheduledMaintenanceService` antes de cerrar C.6b) |

**Orden de trabajo acordado:** FO-008 → FO-004 → FO-003 → FO-005 → FO-007 → FO-009 → **FO-006**.

### FO-009 / CLA-213 — detalle de diseño

- Un solo modelo/controller polimórfico (`FoMaintenanceRecord`, `maintainable_type`/`maintainable_id` vía `morphs()`) en vez de los dos controllers duplicados del sistema viejo (uno para luminarias, otro para cuadros eléctricos) — mismo principio que `FieldOpsMediaController` (FO-005).
- `FoMaintenanceType.code` (string nullable unique: `preventive`/`corrective`/`emergency`) reemplaza los IDs hardcodeados (`PREVENTIVE_ID=1`, etc.) del sistema viejo — los scopes de filtrado usan `code`, no el nombre traducido ni el ID.
- `employee_id` es `string`, referencia blanda a `employees.id` (tabla MySQL local de `Cafca\Employee`, PK no incremental) — mismo patrón exacto que `Safety\Inspection::incident_worker_id`, sin FK de BD (cruce de módulos), validado con `exists:employees,id` en los FormRequests.
- Subdominio "reportado por cliente" con columnas reales (`client_id` FK a `fo_clients`, `priority`, `contact_person`, `contact_phone`, `location_details`, `reported_by_client`) en vez de enterrados en el JSON `details` como hacía el sistema viejo (que por eso agrupaba estadísticas en PHP, no en SQL).
- `ScheduledMaintenanceService`/`Task` del sistema viejo quedaron fuera: CRUD genérico sin evolución real en 12+ meses de historial (a diferencia de `MaintenanceServicesHistory`, que sí tuvo 6+ commits de desarrollo sustancial) — si se confirma uso real más adelante, es un ticket nuevo.

**Backfill pendiente en producción:** `fo_maintenance_types` queda vacía tras la migración — sin los 3 tipos base, `storeClientReported()` devuelve 422 ("no hay tipo de emergencia configurado"). Correr una vez:

```bash
php artisan db:seed --class="Modules\FieldOps\Database\Seeders\FoMaintenanceTypeSeeder"
```

Idempotente (`firstOrCreate` por `code`) — seguro de re-correr.

### Cómo reanudar

```
"Continuamos con FO-XXX. Lee CLAUDE.md y handoff.md."
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

## Sprint Mailing — COMPLETADO Fase 0+1+2 (rama: `feature/mailing`)

> Fase 0+1 cerradas: 2026-05-29 | Fase 2 cerrada: 2026-05-30 | PR: #1 (Fase 0+1) | PR: #2 (Fase 2)
> Documento maestro: `docs/Mailing/mailing-platform-master.md`

### Decisiones arquitectónicas fijadas

- **Transporte:** Microsoft Graph (Fase 1) → ESP externo configurable (Fase futura, MAI-026 bloqueado)
- **DB:** MySQL 8.4 (no PostgreSQL — cross-join con `prospects_prospects`)
- **KPI principal:** clics y CTR, no aperturas (Apple MPP invalida open rate)
- **Audiencias:** `Modules/Prospects` es fuente de verdad. Mailing solo referencia `prospect_id`.

### Mapa MAI — Estado

| Fase | Tickets | Estado |
|------|---------|--------|
| **Fase 0** — Consolidación | MAI-001 a MAI-005 | ✅ Done |
| **Fase 1** — MVP Robusto | MAI-006 a MAI-020 | ✅ Done |
| **Fase 2** — Automatización | MAI-016, MAI-021–025, MAI-027–029 | ✅ Done |
| **Fase 2** — MAI-026 | Webhook ESP externo | ⏸ Bloqueado (decisión gerencia) |
| **Fase 3** — Inteligencia | MAI-031 a MAI-036 | ⬜ Backlog |

### Mapa MAI Fase 2 — Estado final

| MAI | Título | Commit | Estado |
|-----|--------|--------|--------|
| MAI-028 | Schema foundation for Phase 2 | c689e38 | ✅ Done |
| MAI-029 | X-Mailing-Token header — correlación NDR exacta | 4326a82 | ✅ Done |
| MAI-016 | NDR bounce parser — inbox dedicado + command periódico | 48a3e45 | ✅ Done |
| MAI-021 | Segmentos dinámicos basados en eventos | ab724bf | ✅ Done |
| MAI-024 | Programación por franja horaria (Europe/Brussels) | 7a30112 | ✅ Done |
| MAI-025 | Página de preferencias de categoría | 7b00685 | ✅ Done |
| MAI-022 | A/B testing de asunto — split + winner automático por CTR | 79270f7 | ✅ Done |
| MAI-023 | Follow-up automático por comportamiento | 5699c75 | ✅ Done |
| MAI-027 | Alertas de entregabilidad — hard bounce > 5%, spam > 0.08% | 3b20265 | ✅ Done |
| MAI-026 | Webhook handler ESP externo | — | ⏸ Bloqueado |

### Arquitectura Mailing (Fase 2 añadida)

- **Transporte:** `MarketingCampaignInterface` → `MicrosoftGraphMailer` (intercambiable)
- **Workflow:** `draft → review → approved → sending → completed|failed|cancelled`
- **Supresión:** `mailing_suppression_list` — permanente para `hard_bounce` y `spam_complaint`
- **Tracking:** pixel apertura + click redirect vía `mailing_tracked_links` + `X-Mailing-Token` para NDR
- **Eventos:** `mailing_message_events` append-only (KPI: clics únicos, CTR, CTOR)
- **Compliance:** `List-Unsubscribe` + `List-Unsubscribe-Post` en todo correo comercial
- **Segmentos:** `SegmentResolverService` — reglas has_event/has_no_event/prospect_field con invariantes de seguridad
- **Scheduling:** `mailing:dispatch-scheduled` — campaña con `scheduled_at`, claim atómico, antiduplicado
- **A/B testing:** split por % configurable, winner por CTR, claim doble (status + ab_test_started_at)
- **Follow-up:** parent completado → child con audiencia filtrada por evento; claim atomic + empty-audience safe
- **Alertas:** `mailing:check-deliverability-alerts` → `mailing_deliverability_alerts` + notificaciones DB

### Migraciones a ejecutar en producción (Fase 2)

```bash
php artisan migrate
# Nuevas tablas/columnas Fase 2:
# mailing_campaigns: audience_type, audience_filters, scheduled_at, timezone
# mailing_campaigns: ab_subject_b, ab_split_percent, ab_winner_*, ab_test_started_at
# mailing_campaigns: followup_campaign_id, followup_trigger, followup_delay_hours, followup_dispatched_at
# mailing_messages: ab_variant
# mailing_contact_preferences (nueva tabla)
# mailing_deliverability_alerts (nueva tabla)
```

### Configuración requerida (.env)

```env
MAILING_NDR_INBOX=bounces@claesen-verlichting.be   # inbox para NDR bounces
MAILING_SEND_DELAY_MS=500                           # throttle entre envíos
MAILING_UNSUBSCRIBE_DOMAIN=claesen-verlichting.be
```

### Cómo reanudar (Fase 3)

```
"Continuamos con MAI-031. Lee CLAUDE.md y docs/Mailing/mailing-platform-master.md."
```

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
