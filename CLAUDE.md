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
| `fieldops-maintenance-roadmap.md` | Roadmap maestro de mantenimiento: fases CLA-267/266/271/268, Claesen-Client y producción |
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

7. **En prod-priv-01, `opcache.validate_timestamps=0`** (`/etc/php/8.4/fpm/conf.d/10-opcache-prod.ini`) — PHP-FPM nunca relee archivos por su cuenta (CLA-232, 2026-07-06: esto rompió el login de Azure OAuth porque `config:cache` reescribía el archivo pero los workers seguían con el bytecode viejo). `infrastructure/scripts/deploy.sh` ya recarga PHP-FPM en cada deploy completo (paso 9) — no quitar ese paso. Para ediciones manuales de `shared/.env` **sin** un deploy completo, correr `infrastructure/scripts/reload-config.sh` (config:clear + config:cache + `systemctl reload php8.4-fpm`), nunca solo `config:cache` a mano.

8. **`SESSION_DOMAIN` no puede ser un valor estático en `.env`** (CLA-233, 2026-07-06). Filament (`backoffice.claesen.local`) y la PWA Safety/`service.claesen-verlichting` (Sanctum SPA sobre `*.claesen-verlichting.be`, requiere cookie compartida entre `service.claesen-verlichting.be` y `backend.claesen-verlichting.be`) necesitan dominios de cookie *incompatibles* — arreglar uno con un valor fijo en `.env` rompe el otro. Se resuelve en `Modules/Core/Http/Middleware/ResolveSessionCookieDomain.php` (middleware global, `$middleware->prepend(...)` en `bootstrap/app.php`, corre antes que `StartSession`/`VerifyCsrfToken`/Sanctum stateful) usando `EnsureFrontendRequestsAreStateful::fromFrontend()` (Origin/Referer contra `SANCTUM_STATEFUL_DOMAINS`) — **no** `$request->getHost()`: el túnel que trae el tráfico público de la API reescribe el Host interno a `backoffice.claesen.local` antes de llegar a Laravel, así que Host es indistinguible entre Filament y la SPA; solo Origin/Referer sobrevive el proxy. No volver a fijar `SESSION_DOMAIN` a un valor concreto en `.env` de producción — debe quedar `null` (el middleware lo sobreescribe en runtime). Si aparece un tercer dominio consumidor, agregarlo a `SANCTUM_STATEFUL_DOMAINS`, no tocar este middleware ni el `.env`.

9. **Todo PDF nuevo debe incluir `@include('core::pdf.letterhead')`** al inicio del `<body>` — membrete corporativo oficial (logo + 3 columnas KANTOOR/MAATSCHAPPELIJKE ZETEL/contacto, ver `Modules/Core/resources/views/pdf/letterhead.blade.php`), no reinventar el encabezado. **Nombre de marca correcto: "Claesen Outdoor Lighting Platform"** (CLA-234, 2026-07-07) — "Claesen Intelligence Hub" es un nombre viejo ya erradicado del código, no reintroducirlo. Detalle completo (datos de la empresa, variantes de logo, y el bug real de `MicrosoftGraphTransport`) en memoria `project_official_branding`. **Gotcha crítico de emails con logo embebido:** `$message->embed(public_path('img/brand-logo-{light,dark}.png'))` es obligatorio (nunca una URL externa — se rompe), pero **no alcanza por sí solo**: `MicrosoftGraphTransport::getPayload()` (`Modules/Mailing/Mail/Transport/`) debe marcar el adjunto embebido con `isInline: true` + `contentId` (ya corregido) o Microsoft Graph lo manda como adjunto suelto y el `<img src="cid:...">` queda roto — cualquier transport nuevo que se agregue debe replicar este mismo tratamiento para adjuntos con `hasContentId()`.

10. **En `app/Providers/Filament/AdminPanelProvider.php`, todo `->label(__(...))`/`->group('...')` de `NavigationGroup`/`NavigationItem` debe ser un closure** (`fn () => __(...)`), nunca un string plano ya evaluado (CLA-235, 2026-07-07). `panel()` corre una sola vez al bootear la app, en el locale por defecto (`APP_LOCALE=nl`) — un `__()` evaluado ahí queda congelado en holandés para siempre, mientras que `Resource::getNavigationGroup()` evalúa `__()` de nuevo en cada request con el locale real del visitante (fijado después por `BrowserLocaleMiddleware`). Como las dos strings no coinciden en ningún locale que no sea el de boot, el `array_search` interno de Filament para ordenar grupos falla en silencio y el orden del sidebar queda determinado por azar (orden de descubrimiento de recursos), y un `NavigationItem::group('string hardcodeado')` sin traducir crea un grupo duplicado en cualquier otro idioma. `NavigationGroup::label()`/`NavigationItem::group()`/`NavigationItem::make()` aceptan `string|Closure` — usar siempre closure.

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
| **FieldOps** | Gestión de complejos deportivos, terrenos, estructuras, luminarias y mantenimiento — **CLA-268 Done; CLA-275 inició el mockup PWA de Claesen-Client en repositorio independiente** | 🚧 ~89% |
| **Analytics** | Instrumentación de eventos de producto (`app_events`) para medir adopción/fricción en apps internas (Backoffice, Safety PWA, Claesen-Sport/FieldOps) — **CLA-229: base de ingesta lista, sin integración real en ningún frontend todavía** | 🚧 ~30% |

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

## Sprint Analytics — CLA-229 (rama: `codex/instrumentacion-apps-internas`)

> Base de instrumentación de eventos de producto para medir adopción/fricción en Backoffice, Safety PWA y Claesen-Sport/FieldOps. Endpoint de ingesta y modelo de datos listos; Backoffice ya emite `resource_created`/`resource_updated` automáticamente (CLA-231); ningún frontend externo (Safety PWA/Claesen-Sport, repos separados) emite eventos todavía.

### Reglas Analytics (no negociables)

- **Un solo catálogo de eventos: `Modules\Analytics\Enums\EventName`.** Ninguna app/módulo inventa un `event_name` fuera de este enum — `StoreAppEventRequest` lo rechaza con `Rule::enum`. Agregar un evento nuevo es un PR que agrega un `case` acá, nunca un string suelto en el payload del frontend. Mismo criterio para `AppSource` (catálogo de apps consumidoras).
- **`event_name`/`app` son `string` en la tabla `app_events`, no `ENUM` de MySQL a propósito** — la validación vive en PHP para que agregar un evento nunca sea un `ALTER TABLE`. No "mejorar" esto a un `ENUM` de base de datos.
- **Nombres de evento sin prefijo de app** (`inspection_started`, no `safety.inspection_started`) — la columna `app` ya namespacea el origen. No reintroducir prefijos dot-notation en el catálogo.
- **`POST /api/v1/events` es intencionalmente público (sin `auth:sanctum`)** — soporta eventos anónimos/pre-sesión (ej. login fallido). `$request->user()` se resuelve solo si el middleware global `statefulApi()` ya autenticó la request (cookie de sesión o bearer token); si no, el evento se guarda con `user_id=null`, que es una señal válida, no un error. **Por esto mismo la ruta lleva `throttle:120,1`** — sin Sanctum de por medio, `config/cors.php` no protege nada contra un POST directo no-browser. No quitar el throttle sin poner otra mitigación equivalente.
- **`properties` (JSON libre) tiene un cap de 5KB** en `StoreAppEventRequest` — sin este límite, el endpoint es un sumidero de storage sin fondo. No subir el límite sin una razón concreta de negocio.
- **`app_events` es append-only** (`AppEvent::UPDATED_AT = null`) — no editar eventos ya registrados, mismo principio que `mailing_message_events`.
- **`user_id` usa `nullOnDelete`** (no `cascadeOnDelete`) — borrar un usuario de Core no debe borrar el historial analítico. **`employee_id` es referencia blanda sin FK**, mismo patrón que `Safety::incident_worker_id`/`FieldOps::FoMaintenanceRecord.employee_id`.
- **Limitación conocida y aceptada:** `session_ended` depende de que el frontend lo dispare (logout/`beforeunload`) — cierres bruscos (pestaña cerrada, PWA matada en background en campo) nunca lo emiten. Cualquier KPI de duración de sesión debe tolerar sesiones sin cierre formal; no es un bug a "arreglar" en el backend.
- **`resource_created`/`resource_updated` de Backoffice se enganchan vía `Modules\Analytics\Observers\TrackableModelObserver`, attachado a *todos* los modelos de *todos* los recursos Filament en `AnalyticsServiceProvider::boot()`** (`Filament::getPanels()` → `getResources()` → `getModel()::observe(...)`) — no vía `Filament::serving()`, que solo dispara en requests HTTP reales ruteadas por el panel y por eso es inútil en tests/consola (CLA-231, encontrado empíricamente: el registro vía `serving()` nunca corría en `Livewire::test()`). El filtro real de "esto pasó en el panel, no en un sync command" vive **dentro del Observer**, no en el registro: `Filament::getCurrentPanel() !== null` (poblado por el middleware `SetUpPanel`, exclusivo de requests ruteadas por un panel). **No agregar opt-out por módulo/modelo** — el mecanismo ya es genérico y correcto sin lista de exclusión; si un modelo no debe trackearse, es porque no debería ser un recurso Filament en primer lugar.
- **`report_exported` sigue sin implementar a propósito** — no hay un choke point genérico (cada módulo exporta PDFs distinto: Safety, Performance, Website), forzar una abstracción común sería sobre-ingeniería. Instrumentar caso por caso si se decide priorizarlo.

### Estado

Implementado (CLA-229): migración `app_events`, modelo `AppEvent`, `EventTracker` (servicio de registro centralizado), `RecordAppEventJob` (cola), endpoint de ingesta, catálogo completo de eventos (7 transversales operativos + 12 reservados por app), 9 feature tests.

Implementado (CLA-231): `TrackableModelObserver` engancha `resource_created`/`resource_updated` en los ~30 recursos Filament existentes sin tocar ninguno de esos archivos. 3 feature tests adicionales (`BackofficeResourceEventTest`, vía `Livewire::test` real sobre `Permission`), 12/12 en verde en el módulo.

Pendiente (sin ticket abierto todavía): integración real en Safety PWA (`/home/totti/Claesen-Safety`, confirmado) y Claesen-Sport/FieldOps (`/home/totti/Claesen-Sport`) — repos separados, llamada HTTP al endpoint desde cada frontend; `report_exported` (ver regla arriba); dashboards de adopción/fricción (Fase futura, requiere semanas de datos reales, mismo criterio que se aplicó en Mailing Fase 3).

---

## Sprint FieldOps — EN CURSO (rama: `main`, menú "(Demo)")

> Auditoria comparativa 2026-07-03 contra el satélite anterior `api-claesen-sport-app`. Ver `handoff.md` para el detalle completo.

### Estado

`fo_admin` (Slices C.1→C.6a) ya está mezclado en `main` y `origin/main`. El menú "Field Operations" en Filament está marcado **"(Demo)"** (`lang/en,nl/navigation.php`, clave `navigation.groups.field_operations`) porque el módulo no tiene ningún consumidor real conectado todavía y le faltan dominios completos frente al sistema anterior.

### Reglas FieldOps (no negociables)

**Roadmap maestro:** `docs/ai/fieldops-maintenance-roadmap.md`. Claesen-Client es la Fase 4 de este programa y nunca debe planificarse como iniciativa independiente.

- Tablas con prefijo `fo_`. `created_by_user_id` siempre `nullOnDelete` (no `cascadeOnDelete`) — borrar un usuario de Core no debe borrar datos operacionales.
- Traducciones: `spatie/laravel-translatable` (columnas json) + `HasAiTranslations` propio (`Modules/Intelligence`) para autotraducir con Gemini. Locales canónicos: **`nl, en, fr, de`** — no usar `es` (bug corregido en FO-008).
- `LuminaireGroup` está intencionalmente denormalizado como `group_name` string en `fo_luminaire_subgroups` — no crear una tabla catálogo separada sin discutirlo primero (decisión de Slice C).
- `ComplexZoomLevel` (zoom por usuario) está intencionalmente colapsado a un único campo `zoom` en `Complex` — no revertir a zoom por usuario sin justificación de negocio.
- `Access`/`Safety` de estructura están denormalizados como columnas planas en `fo_structures` (`access_type_id`, `access_active`, `safety_type_id`, `safety_certified`) en vez de tablas de instancia separadas — mismo precedente que `LuminaireGroup` (relación 1:1 por estructura, nunca reutilizada). Catálogos `AccessType`/`SafetyType` sí son tablas propias (`super_admin` only).
- `ElectricalBoard` (`fo_electrical_boards`) SÍ usa 3 tablas pivot reales (`fo_complex_electrical_board`, `fo_electrical_board_terrain`, `fo_electrical_board_structure`, todas con FK `cascadeOnDelete`) porque un cuadro eléctrico puede compartirse entre múltiples complejos/terrenos/estructuras — no es 1:1 como Access/Safety, así que aquí sí aplica tabla de instancia (pivot) en vez de denormalizar.
- Adjuntos (fotos/PDFs) de `Complex`/`Terrain`/`Structure`/`ElectricalBoard` usan `spatie/laravel-medialibrary` con **disco privado `local`** (mismo `storage_path('app/private')` que `Modules/Safety`, no el disco `public` por defecto de la librería). Colecciones `photos`/`documents` vía trait compartido `HasFieldOpsMedia` — al añadir el trait a un modelo nuevo, resolver el conflicto de métodos con `InteractsWithMedia` usando `insteadof` (ver cualquiera de los 4 modelos existentes como ejemplo). Servir/subir siempre vía `FieldOpsMediaController` (genérico, no crear controllers de media por entidad).
- **El dominio de Mantenimiento de luminarias (`TypeMaintenance`/`MaintenanceServicesHistory`) SÍ está en uso real en producción** (confirmado directamente por el usuario, 2026-07-04) — no era código muerto del sistema anterior. FO-009 creó el historial polimórfico; CLA-267 agregó después la planificación y las órdenes de trabajo sin reutilizar el CRUD genérico del satélite.
- **La posición física de una luminaria es estable y no pertenece al equipo reemplazable** (CLA-265, 2026-07-21): `fo_luminaire_positions` es la fuente canónica de frame/slot/X/Y/escala/versión; cada fila de `fo_luminaires` es una instalación. Un reemplazo siempre crea una nueva luminaria, retira la anterior y registra mantenimiento dentro de una sola transacción, manteniendo el mismo `luminaire_position_id`. Nunca implementar un reemplazo sobrescribiendo tipo/serial sobre la fila anterior ni recalculando las coordenadas.
- **Plan, orden y registro son entidades distintas** (CLA-267, 2026-07-22): `FoMaintenancePlan` define recurrencia, `FoMaintenanceWorkOrder` coordina planificación/asignación/ejecución/validación y `FoMaintenanceRecord` conserva el trabajo ya validado. La app de terreno inicia y envía la ejecución; el backoffice valida y cierra. Un cierre excepcional desde backoffice exige `override_reason` y lo replica en el registro histórico. Equipo, cliente y `luminaire_position_id` se derivan del contexto FieldOps y no son editables desde la orden. El histórico es **solo lectura** tanto en API como en Filament; no volver a registrar rutas o acciones CRUD directas. La sustitución atómica mediante `LuminaireReplacementService` es la única excepción interna.
- **El aislamiento de clientes es fail-closed** (CLA-266, 2026-07-22): una cuenta externa siempre lleva rol `client` y obtiene visibilidad únicamente mediante `fo_client_user` activo con `can_view=true`. Toda consulta y acceso directo a cliente, complejo, terreno, estructura, frame, luminaria, cuadro, media e histórico debe resolverse con `FieldOpsTenantService`; un activo sin cliente o conectado a varios clientes no es visible. Las cuentas cliente son read-only y no acceden a órdenes internas. No confiar en un `client_id` enviado por el frontend.
- **`FoClient` y la creación de `Complex` pertenecen al bridge CAFCA**: sus escrituras manuales están retiradas de API y Filament. El vínculo `Complex.client_id` sigue siendo inmutable. Una orden de mantenimiento solo puede crearse cuando el equipo resuelve exactamente un cliente.
- **Asignación y lifecycle son auditables** (CLA-271, 2026-07-22): solo se asigna a empleados CAFCA con `User` activo; `assigned_by_user_id`/`assigned_at` identifican la asignación vigente. Toda transición se ejecuta en `MaintenanceWorkOrderService` y añade un `FoMaintenanceWorkOrderEvent` append-only. Una devolución requiere motivo y vuelve `awaiting_validation → in_progress`. Las notificaciones FieldOps database/mail van en cola after-commit, respetan preferencias por canal y nunca mezclan módulos en sus endpoints.
- **Las solicitudes de cliente son un dominio propio** (CLA-268, commit `d0436df`, 2026-07-22): `FoMaintenanceRequest` conserva snapshot de instalación/posición, conversación pública append-only, notas internas separadas, adjuntos privados, cuadros eléctricos, intake IA no autoritativo, confirmación y reapertura. La conversión puede generar sucesivas órdenes conservando la historia; el cliente nunca recibe notas internas ni accede al workflow de órdenes. Invitaciones de contactos usan código opaco one-time almacenado como hash y capacidades `can_view`/`can_report`/`can_manage_contacts`.

### Gaps abiertos (tickets Linear, equipo Claesen)

| Ticket | Título | Estado |
|--------|--------|--------|
| FO-008 / CLA-206 | Fix locale es→de en validación FieldOps | ✅ Done (`6a831e9`) |
| FO-004 / CLA-207 | Slice E — Access/Safety de fijación de estructura | ✅ Done (`4f6d1c5`) |
| FO-003 / CLA-209 | Slice D — Electrical Board (dominio completo) | ✅ Done (`603baf7`) |
| FO-005 / CLA-210 | Slice F — Adjuntos de archivos/planos (Media Library) | ✅ Done (`f80e0cb`) |
| FO-007 / CLA-212 | Spike — evaluar alcance del dominio de Mantenimiento | ✅ Done — **está vivo en producción**, no se cierra como N/A |
| FO-009 / CLA-213 | Slice G — Dominio de Mantenimiento de luminarias (implementación real) | ✅ Done — `FoMaintenanceType` (catálogo) + `FoMaintenanceRecord` polimórfico (Luminaire\|ElectricalBoard) + subdominio cliente-reportado. Excluido a propósito: `ScheduledMaintenanceService`/`Task` (sin evidencia de uso real, ver detalle abajo) |
| FO-012 / CLA-226 | Bridge `MirrorRelation` → `FoClient`, deshabilitar creación manual | ✅ Done |
| FO-013 / CLA-227 | Bridge `MirrorRelationDelivery` → `Complex` + geocoding, deshabilitar creación manual | ✅ Done |
| CLA-265 | Posición física estable + reemplazo atómico de luminarias | ✅ Done |
| CLA-266 | Ownership de cliente y autorización tenant-aware | ✅ Done — aislamiento tenant y hardening OAuth aprobados |
| CLA-267 | Planes de mantenimiento y órdenes de trabajo | ✅ Done — hardening del histórico y cutover de Claesen-Sport aprobados tras auditoría (`d7606bc` en la app de terreno) |
| CLA-268 | Solicitudes de incidencia del cliente y respuesta backoffice | ✅ Done — `d0436df` (aplicación), `545b42e` (memoria), cierre Linear registrado |
| CLA-275 | Fase 4 — Claesen-Client: portal PWA de mantenimiento | 🚧 En progreso — mockup interactivo en `/home/totti/Claesen-Client`, commit `9f2414b`; API real bloqueada hasta aprobación visual |
| CLA-276 | Fase 5 — Validación integral y producción de Claesen-Client | 🚧 En progreso — todo el código del checklist está implementado y verificado: E2E de `luminaire_id` (`3680602`), refactor de Claesen-Client en componentes testeables + 52 tests (`79b11f7`/`c60e06b`), cancelación de solicitudes (`cb33822`/`3e9893e`), WCAG 2.2 AA (`5fde0c0`), artefactos + runbook de infraestructura de producción (`03660ae`, sin ejecutar — requiere SSH/DNS real), alertas operacionales + widget de métricas de ciclo de vida (`26110b5`). Pendiente para cerrar el ticket: aplicar el runbook de infraestructura en servidores reales y decidir pipeline de CI/CD de Claesen-Client |
| CLA-277 | Pines de marcador para Structure Types (portado de CLA-269/270) | ✅ Done (`bd747ba`) — `StructurePinCatalog`, selector visual en Catalogs, marcador real en mapa de `StructureResource` |
| FO-006 | Slice C.6b — Cutover: frontend Sport → Core, deprecar Sport | ⬜ Todo (ya no bloqueado por la parte de Mantenimiento cubierta en FO-009; si el cutover necesita mantenimiento *programado* a futuro, abrir ticket nuevo para `ScheduledMaintenanceService` antes de cerrar C.6b) |

**Orden de trabajo acordado:** FO-008 → FO-004 → FO-003 → FO-005 → FO-007 → FO-009 → FO-012 → FO-013 → **FO-006**.

### FO-012 / CLA-226 y FO-013 / CLA-227 — detalle de diseño (2026-07-05)

Auditoría del satélite viejo (`api-claesen-sport-app`) confirmó que tanto `Client` como `Complex` nunca tuvieron una vía de creación manual *intencional*: el `ComplexController::store()` viejo existía pero jamás se registró en rutas, con un `//TODO importar desde cafca` explícito al lado. Se decidió con el usuario deshabilitar la creación manual en ambos (Filament `canCreate(): false` + páginas `create` quitadas de `getPages()`; mismo tratamiento en `Claesen-Sport` quitando el botón "+" de `ClientsListPage`/`ComplexesListPage`) y construir el bridge real desde el ERP.

- **`FoClient` ← `MirrorRelation`** (`Modules/FieldOps/Services/ClientRelationSyncService`, comando `fieldops:sync-clients-from-relations`): solo importa relaciones con `tp_customer=1` (la tabla `relation` del ERP también tiene proveedores/transportistas/subcontratistas mezclados). Idempotente vía `fo_clients.relation_id` (nullable, único, sin FK — mismo patrón que `Safety::incident_worker_id`). `withTrashed()` + `restore()` para no chocar con el índice único si un cliente fue soft-deleted. Fixes de paso en `SyncMirrorDataService::syncRelations()`: `phone` ahora lee `tel1` (antes 100% vacío, leía columnas que no existen), se agrega `street` (nunca se sincronizaba), y se decodifica el código numérico de `language` del ERP (1/2/3/4, sin tabla de referencia en el ERP) a locale `nl/fr/en/de` — mapeo **inferido, no confirmado con el negocio** (por distribución real: 1=1118, 2=39, 3=5, 4=5), documentado con comentario en el código. Esto también corrige un bug preexistente en `MirrorRelation::getIsNlAttribute()` (usado por el Offer Simulator), que comparaba el código numérico crudo contra `'nl'` y siempre daba `false`. Resultado en dev: **1167 clientes reales** desde 3265 relaciones.

- **`Complex` ← `MirrorRelationDelivery`** (nuevo mirror, tabla CAFCA `relation_delivery` — direcciones de entrega/sitio del cliente, que para un contratista de iluminación son instalaciones físicas reales: canchas, polideportivos, estadios). `Modules/FieldOps/Services/ComplexRelationDeliverySyncService`, comando `fieldops:sync-complexes-from-relation-deliveries`. Solo importa deliveries cuyo `relation_id` ya resolvió a un `FoClient` sincronizado (vía FO-012) y `relation_id != 0` (placeholder catch-all del ERP, mismo patrón que los IDs basura 100/101/102 de `relation`). Idempotente vía `(fo_complexes.relation_id, delivery_seq_nr)`. Resultado en dev: **887 complejos reales** desde 935 direcciones (26 sin cliente sincronizado, descartadas).

- **Geocoding** (`Modules/FieldOps/Services/GeocodingService`): `relation_delivery` no trae coordenadas — se resuelven vía Google Geocoding API (`config('services.google_geocoding.key')` / env `GOOGLE_GEOCODING_API_KEY`) **solo la primera vez** que un `Complex` no tiene `lat`/`lng` — nunca pisa coordenadas ya pineadas a mano en el `MapPicker` del frontend, ni re-geocodifica en corridas siguientes del sync. Necesario porque `Claesen-Sport` va a listar complejos ordenados por proximidad al usuario. Resultado en dev: 883/887 geocodificados (5 sin street/city/zipcode en el ERP, quedan `null`).

- **Cache de geocoding independiente de `Complex`** (2026-07-07, incidente real): el guard "solo la primera vez" de arriba protege corridas normales del sync, pero **no** protege contra un reset/reseed de `fo_complexes` (`migrate:fresh`, DB de dev limpiada por otra sesión de trabajo en paralelo, etc.) — sin ningún `Complex` existente, el guard `lat===null && lng===null` es siempre verdadero y el próximo sync re-geocodificaría los ~887 complejos de una sola vez contra la API real de Google. Se agregó `fo_geocoding_cache` (tabla nueva, migración `2026_07_07_029`, modelo `GeocodingCache`) — cache por `sha1(dirección normalizada)`, **independiente de la fila de `Complex`**, consultado por `GeocodingService::geocode()` antes de llamar a Google. También cachea resultados negativos (`ZERO_RESULTS`, etc.) para no reintentar para siempre direcciones que Google nunca va a poder resolver. Tests en `GeocodingServiceTest.php` (5 casos, incluye uno que simula explícitamente el escenario "la fila de Complex ya no existe, el cache sí"). Confirmado con `SyncComplexesFromRelationDeliveriesTest` (5/5) que el comportamiento existente no cambió.

**Pendiente:** confirmar con el negocio el mapeo real de `relation.language` (1/2/3/4 → nl/fr/en/de) — hoy es una inferencia. La key de Google Geocoding está restringida por IP en Google Cloud Console a la IP de este entorno de dev (`169.155.241.57`, probablemente dinámica) — si se corre este sync desde producción (`prod-priv-01`) hay que agregar esa IP también.

### Sesión 2026-07-05 (cont.) — catálogos portados, campo único + AI translation, fixes de UX/CSRF

- **`client_id` de `Complex` es inmutable vía API** (commit `3bfd95f`): el vínculo cliente↔complejo viene del sync CAFCA (FO-013) y nunca debe reasignarse desde la app. Se quitó `client_id` de `UpdateComplexRequest::rules()` — `FormRequest::validated()` solo devuelve claves con regla definida, así que un `client_id` en el body se ignora en silencio en vez de aplicarse. Frontend (`ComplexFormModal.tsx`): el selector de cliente pasó de un `<select>` editable a texto de solo lectura (y de paso se detectó que el `<select>` viejo solo cargaba los primeros 50 clientes de 1167 — por eso a veces se veía vacío aunque el complejo sí tuviera cliente).
- **8 catálogos de FieldOps portados del satélite viejo** (commit `3bfd95f`, `Modules/FieldOps/Database/Seeders/FieldOpsCatalogSeeder.php`): `AccessType`, `ElectricalBoardType`, `SafetyType`, `StructureType`, `TerrainType`, `LuminaireFrameType`, `LuminaireSubgroup`, `LuminaireType` estaban vacíos en dev (los selects de los formularios de Terrain/Structure/Luminaire/ElectricalBoard no tenían nada para elegir). Ajustado a las diferencias de schema del rediseño: `LuminaireGroup`+`LuminaireSubgroup` viejos se fusionan en `fo_luminaire_subgroups.group_name`+`brand` (decisión Slice C ya documentada), `LuminaireType`/`LuminaireFrameType` son string plano acá (no traducible), `TerrainType` traduce el atributo `type` no `name`. Locale `es` del satélite viejo descartado (regla FO-008), `de` agregado a mano. Idempotente (`firstOrCreate`), correr con `php artisan db:seed --class="Modules\FieldOps\Database\Seeders\FieldOpsCatalogSeeder"`.
- **Un solo campo de texto + auto-traducción IA, en vez de 4 inputs por idioma** (commit pendiente): tanto el backoffice Filament (12 archivos: 6 `Catalogs/*Resource.php` + `TerrainResource`/`TerrainsRelationManager` + `StructureResource`/`ElectricalBoardResource`/`LuminaireResource`/`LuminairesRelationManager`) como `Claesen-Sport` (`TranslatableInput.tsx`) mostraban 4 `TextInput`/`Textarea` separados (nl/en/fr/de) para cada campo traducible — mala UX en mobile y redundante, porque `HasAiTranslations` **ya auto-traduce** en `created`/`updated` (dispara `TranslateModelAttributesJob` para cualquier atributo traducible que cambió, usando `app()->getLocale()` como locale de origen). Fix en Filament: `TextInput::make('name')`/`Textarea::make('info')` **sin sufijo de locale** — Spatie `HasTranslations` resuelve/escribe automáticamente sobre `app()->getLocale()` cuando se accede al atributo sin sufijo, no hace falta ningún plugin de Filament para esto. Fix en `Claesen-Sport`: `TranslatableInput.tsx` reescrito para mostrar un solo campo atado a `useConfig().language`, con los demás locales ya traducidos mostrados de solo lectura debajo una vez completados.
- **Bug real encontrado en el mismo cambio**: `ComplexesListPage.tsx`/`ClientsListPage.tsx` (Claesen-Sport) tenían `const { data = [] } = useQuery(...)` — el `= []` inline crea un array nuevo en cada render, lo que rompe el `useEffect([data])` de abajo (siempre "cambia") y causa un loop infinito de render ("Maximum update depth exceeded"). Fix: constante módulo-level `EMPTY_COMPLEXES`/`EMPTY_CLIENTS` como default estable. Confirmado que ningún otro list/detail page de `Claesen-Sport` tiene este patrón combinado con `useEffect` (los demás usan `data` directo en el render, sin funnel a `useState`, así que no están afectados).
- **Vista satelital en `MapPicker.tsx`** (Claesen-Sport): capa base cambiada de OpenStreetMap estándar a Esri World Imagery (satelital, sin API key) + overlay de referencia de calles/nombres — pedido explícito para poder corregir el marcador de coordenadas contra el terreno real.
- **Bug real de CSRF/sesión (419) en `Claesen-Sport`, dos causas combinadas:**
  1. `config/session.php` tiene `'secure' => env('SESSION_SECURE_COOKIE', true)` — default `true` si la env var no está seteada. El `.env` local nunca la definía, así que las cookies de sesión/CSRF salían `Secure` corriendo sobre `http://localhost` en dev — el navegador las descarta/no las reenvía de forma confiable. Fix: `SESSION_SECURE_COOKIE=false` en `.env` local **(nunca cambiar esto en producción, que sí corre HTTPS real)**.
  2. axios (desde v1.6) solo adjunta el header `X-XSRF-TOKEN` automáticamente en requests **al mismo origen** — `localhost:5173` (Claesen-Sport) y `localhost:8000` (API) son puertos distintos = orígenes distintos, así que el header nunca se mandaba en ningún POST/PUT/PATCH/DELETE. Confirmado mirando el preflight OPTIONS real: `Access-Control-Request-Headers` solo listaba `content-type`, nunca `x-xsrf-token`. Fix: `withXSRFToken: true` en la instancia de axios (`src/api/client.ts`).
  - Diagnosticado reproduciendo el flujo completo (csrf-cookie → login → POST) con `curl` y comparando contra el request real del navegador — el `curl` "funcionaba" porque no aplica ni la regla de cookies `Secure` sobre HTTP ni la restricción same-origin de axios, lo que enmascaró ambos bugs en la primera pasada de diagnóstico.

### FO-009 / CLA-213 — detalle de diseño

- Un solo modelo/controller polimórfico (`FoMaintenanceRecord`, `maintainable_type`/`maintainable_id` vía `morphs()`) en vez de los dos controllers duplicados del sistema viejo (uno para luminarias, otro para cuadros eléctricos) — mismo principio que `FieldOpsMediaController` (FO-005).
- `FoMaintenanceType.code` (string nullable unique: `preventive`/`corrective`/`emergency`) reemplaza los IDs hardcodeados (`PREVENTIVE_ID=1`, etc.) del sistema viejo — los scopes de filtrado usan `code`, no el nombre traducido ni el ID.
- `employee_id` es `string`, referencia blanda a `employees.id` (tabla MySQL local de `Cafca\Employee`, PK no incremental) — mismo patrón exacto que `Safety\Inspection::incident_worker_id`, sin FK de BD (cruce de módulos), validado con `exists:employees,id` en los FormRequests.
- Subdominio "reportado por cliente" con columnas reales (`client_id` FK a `fo_clients`, `priority`, `contact_person`, `contact_phone`, `location_details`, `reported_by_client`) en vez de enterrados en el JSON `details` como hacía el sistema viejo (que por eso agrupaba estadísticas en PHP, no en SQL).
- `ScheduledMaintenanceService`/`Task` del sistema viejo quedaron fuera: CRUD genérico sin evolución real en 12+ meses de historial (a diferencia de `MaintenanceServicesHistory`, que sí tuvo 6+ commits de desarrollo sustancial) — si se confirma uso real más adelante, es un ticket nuevo.

### CLA-267 — planes y órdenes de trabajo (2026-07-22)

- `fo_maintenance_plans` guarda recurrencia, siguiente vencimiento, asignación e instrucciones. `fieldops:generate-maintenance-work-orders` genera los ciclos vencidos cada hora y ofrece `--dry-run`.
- `fo_maintenance_work_orders` usa estados `planned → assigned → in_progress → awaiting_validation → completed` y `cancelled`. El mantenimiento histórico solo se crea al validar/cerrar la orden.
- El alta es contextual desde Luminaire o Electrical Board. El cliente se deriva de los complejos del equipo y la luminaria conserva el `luminaire_position_id` estable; ambos valores quedan fuera del formulario editable.
- La app FieldOps consume los endpoints Sanctum de órdenes asignadas, inicio y envío. Solo el empleado asignado (por `users.employee_id`) o un `admin`/`super_admin` puede ejecutar la orden. El backoffice valida/cierra; el override exige motivo auditado.
- El menú lateral ya no expone el CRUD ambiguo de registros históricos. Expone `Work orders` como cola operacional y `Maintenance plans` como planificación recurrente; el historial sigue navegable desde el equipo.
- **Hardening posterior a auditoría:** retiradas todas las rutas POST/PATCH/PUT/DELETE de `FoMaintenanceRecord`, incluidas las escrituras legacy `client-reported`. El recurso Filament solo registra `index` y `view`, sin edición, eliminación ni restauración. Claesen-Sport eliminó su modal CRUD del histórico y ahora ofrece `My work`/`Mijn werk`, detalle de orden, inicio y envío a validación.
- **Test Gate del hardening:** focal **22 passed / 102 assertions**; regresión integrada **42 passed / 301 assertions**. Suite FieldOps amplia **209 passed / 649 assertions** y conserva 93 fallos del harness preexistente (`RoleAlreadyExists` y permisos de `storage/framework/testing/disks`); los tests del hardening y de órdenes pasan dentro de esa corrida. PWA: build y **2 tests Vitest** pasan; lint de archivos modificados pasa, mientras el lint global conserva 20 errores preexistentes fuera del alcance.
- **Cierre:** GO técnico aprobado el 2026-07-22. Cutover de Claesen-Sport en `d7606bc`; el hardening backend y esta memoria forman el commit dedicado de cierre de CLA-267.

### CLA-271 — auditoría de asignación y notificaciones operacionales (2026-07-22, Done)

- Las órdenes guardan asignador/fecha vigentes y las devoluciones guardan actor, fecha y motivo. Solo se puede asignar trabajo a un empleado CAFCA vinculado a un usuario activo.
- `fo_maintenance_work_order_events` registra creación, asignación/reasignación, inicio, envío, devolución, validación, override y cancelación. El modelo rechaza update/delete: el timeline es append-only.
- `MaintenanceWorkOrderService` concentra todas las transiciones con bloqueo transaccional. Backoffice puede devolver `awaiting_validation → in_progress` con motivo obligatorio; terreno ve ese motivo y reenvía la ejecución.
- Notificaciones EN/NL por database+mail se encolan after-commit, tienen backoff y respetan preferencias por canal. Endpoints `/api/v1/fieldops/notifications*` aíslan usuario y módulo; Claesen-Sport ofrece badge, centro, marcado de lectura y deep links SPA.
- Filament muestra auditoría de asignación y timeline, limita el selector a empleados ejecutables y añade `Return for correction`.
- Validación: backend focal **19 tests / 100 assertions**; Claesen-Sport **4 tests**, build y lint focal limpios. Migración `000006` validada con **up → rollback → up**. Lint global de Claesen-Sport conserva errores preexistentes fuera del alcance.
- Commits dedicados: backend `91380dd`; Claesen-Sport `2ce9dcf`. Linear queda listo para marcar `Done` tras registrar esta validación.

### CLA-266 — ownership CAFCA y autorización tenant-aware (2026-07-22, Done)

- `fo_client_user` relaciona contactos externos con uno o varios clientes e incluye estado y capacidades (`can_view`, `can_report`, `can_manage_contacts`). El alta desde User Management fuerza el rol `client`, valida los clientes server-side y nunca permite elegir roles internos en ese flujo.
- `FieldOpsTenantService`, la policy compartida y `EnforceFieldOpsTenantAccess` filtran listados y bloquean accesos directos BOLA, media privada e históricos de otro cliente. Topologías ausentes o ambiguas se ocultan; el rol cliente es read-only y no ve órdenes de trabajo internas.
- El backoffice quedó limitado a una allowlist explícita (`super_admin`, `admin`, `financial_manager`, `hr_manager`, `viewer`). `project_manager`, `client` y usuarios sin rol continúan fuera de Filament.
- Los redirects OAuth se aceptan solo para orígenes configurados. `CLIENT_PORTAL_URL` se integra en redirect, CORS y dominios stateful de Sanctum; el hostname productivo sigue sin fijarse hasta confirmar el dominio definitivo.
- Se retiraron las rutas de escritura manual de `FoClient` y el POST de `Complex`; Filament tampoco puede borrar/restaurar clientes CAFCA. El contexto de mantenimiento exige exactamente un cliente.
- Validación focal limpia: **71 tests / 212 assertions**. Migración `2026_07_22_000005_create_fo_client_user_table` verificada con migrate, rollback y reaplicación. GO técnico recibido; cierre en commit dedicado y Linear.

**Backfill pendiente en producción:** `fo_maintenance_types` queda vacía tras la migración y las órdenes necesitan los 3 tipos base. Correr una vez:

```bash
php artisan db:seed --class="Modules\FieldOps\Database\Seeders\FoMaintenanceTypeSeeder"
```

Idempotente (`firstOrCreate` por `code`) — seguro de re-correr.

### CLA-269 — Terrain Types: fix locale + selector visual de marcador + catálogo ampliado (2026-07-22)

- El marcador Leaflet por tipo de terreno es 100% data-driven desde CLA-256 (`code` + `pin_color` en `fo_terrain_types`), pero el formulario Filament de `TerrainTypeResource` (Catalogs) nunca exponía esos dos campos. Se agregó un `ColorPicker` para `pin_color` y un selector visual searchable (grid con preview + búsqueda) para `code`, con "Generic" como opción explícita — **no un upload de SVG por fila**, que se descartó por contradecir el catálogo fijo ya aprobado.
- **`Modules/FieldOps/Support/TerrainPinCatalog.php`** es la única fuente de verdad del catálogo de íconos (19 códigos + fallback genérico) — consumida por `terrain-location-picker.blade.php`, `map-panel.blade.php` (ambos vía `@foreach`, antes tenían el switch de JS duplicado a mano) y el selector del admin. Agregar un código nuevo es un solo `case` acá + una fila en `TerrainTypeSeeder`, nunca duplicar el switch en los blades.
- Catálogo ampliado de 9 a 19 códigos con deportes reales de Bélgica/Países Bajos/Alemania (korfball, rugby, american_football, baseball, beach_volleyball, golf, cycling_track, skatepark, equestrian, minigolf), aprobados explícitamente por el usuario antes de implementar — no es una lista exhaustiva de "todos los deportes posibles", es acotada a lo realista para el negocio de Claesen.
- Fix del bug `[object Object]` en el Edit de Terrain Types: mismo patrón que `EditTerrain.php` (`mutateFormDataBeforeFill()` resolviendo el locale actual), porque `EditRecord::fillForm()` usa `attributesToArray()`, que bypassa el accessor de traducción de Spatie. **Corregido en todos los modelos FieldOps con `HasTranslations`** (10 en total): AccessType/StructureType/SafetyType/ElectricalBoardType (CLA-273) + FoMaintenanceType/Structure/Luminaire/ElectricalBoard (CLA-274, ver abajo). `LuminaireFrameType`/`LuminaireSubgroup`/`LuminaireType` quedaron fuera a propósito porque no usan `HasTranslations` (columnas string planas), nunca tuvieron este bug.

### CLA-270 — fix visual de los pines de Terrain Types (2026-07-22, commit `e37a1b9`)

El primer diseño de CLA-269 mantuvo la forma interna de los 19 íconos tal como estaba en el badge cuadrado de CLA-256 y los encajó en el contorno de gota escalándolos (`scale(0.72)` + reposición) — se ejecutaba bien y pasaba los tests, pero se veía mal en la práctica (líneas demasiado finas, ilegibles a tamaño de marcador; el usuario reportó que hasta soccer/tenis se veían como una cruz genérica). **Lección: pasar los tests no es lo mismo que verse bien — para SVGs/UI visual, la verificación real es mirar el resultado renderizado, no solo `php -l` o aserciones HTML.** Fix: en vez de escalar los íconos del badge, se portaron 1:1 los fragmentos ya diseñados nativamente para la gota en el artifact de revisión `ccf2310c` (coordenadas centradas en origen, pensadas para el bulbo angosto de la gota) — mismo archivo `TerrainPinCatalog.php`, mismos 19 códigos, misma API pública, solo cambia el contenido de cada SVG. No se pudo verificar en navegador real en esta sesión (sin herramienta de browser); la verificación fue inspección manual del SVG resuelto vía `tinker` comparado contra el artifact.

### CLA-272 — fix pines de terreno en mapas de Complex/Structure/ElectricalBoard (2026-07-22, commit `4934f9f`)

`ComplexResource`, `StructureResource` y `ElectricalBoardResource` arman sus marcadores `type: 'terrain'` para `map-panel.blade.php` sin `terrainTypeCode`/`terrainTypeColor` — el JS (`buildTerrainMarkerSvg`) solo dibuja el pin de deporte cuando `terrainTypeCode` está presente, si no cae al círculo genérico con letra. `TerrainResource.php` (vista de un terreno individual) ya lo hacía bien, por eso `/terrains/{id}` mostraba el pin correcto pero `/complexes/{id}` no. Si se agrega un cuarto lugar que construya marcadores `type: 'terrain'` para `map-panel.blade.php`, replicar `'terrainTypeCode' => $terrain->terrainType?->code` + `'terrainTypeColor' => $terrain->terrainType?->pin_color`.

### CLA-274 — fix bug [object Object] en Maintenance Types/Structure/Luminaire/ElectricalBoard (2026-07-22, commit `479810c`)

El barrido de CLA-273 se hizo por lista de catálogos recordados de memoria y dejó 4 modelos afectados sin auditar: `FoMaintenanceType` (campo `name`, catálogo Maintenance Types), `Structure` (`info`), `Luminaire` (`info`), `ElectricalBoard` (`location_description`). **Para auditar este bug de forma completa, correr `grep -rl "HasTranslations" Modules/<módulo>/Models/`** — esa es la lista definitiva de modelos susceptibles, no una lista de nombres recordados. Mismo fix `mutateFormDataBeforeFill()` aplicado a los 4. De paso se corrigió el helper text del campo `code` en `FoMaintenanceTypeResource` (`preventive | corrective | emergency | replacement — leave empty for a custom type`, ahora vía clave de traducción `fieldops::resource.catalogs.maintenance_type_code_helper`) — faltaba mencionar `replacement`, el código real usado por `LuminaireReplacementService` para los reemplazos atómicos de luminarias (CLA-265).

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
