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

Todo cambio debe pasar por Linear. Para una solicitud autorizada que requiera cambios y no tenga ticket activo, el agente crea el ticket y lo mueve a `In Progress` automáticamente; no pide una aprobación separada para esa gestión. Las consultas, revisiones y diagnósticos sin cambios no requieren ticket.
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

1. Crear el issue Linear si hace falta y moverlo a **In Progress** automáticamente.
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

## Sprint FieldOps — EN CURSO (rama de trabajo: `fieldops-backend-fixes`, menú "(Demo)")

> Auditoria comparativa 2026-07-03 contra el satélite anterior `api-claesen-sport-app`. Ver `handoff.md` para el detalle completo.

> **Rama fuente de verdad (CLA-295, 2026-08-04):** todo el trabajo activo de FieldOps se comitea en `fieldops-backend-fixes`, no en `main`. `main` es la rama de release/deploy — `infrastructure/scripts/deploy.sh` clona `origin/main` directamente (`git clone --branch main`), así que solo lo que llega a `origin/main` se despliega. `fieldops-backend-fixes` se reconcilia en `main` periódicamente (merge o fast-forward según si `main` acumuló commits propios) y luego se empuja a `origin/main` explícitamente — nunca asumir que comitear en `fieldops-backend-fixes` por sí solo actualiza producción. Antes de CLA-295, `origin/main` llevaba ~1 mes sin push (desde 2026-07-07) y `fieldops-backend-fixes` nunca se había subido al remoto.

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
| CLA-276 | Fase 5 — Validación integral y producción de Claesen-Client | ✅ Done — todo el código del checklist implementado y verificado: E2E de `luminaire_id` (`3680602`), refactor de Claesen-Client en componentes testeables + 52 tests (`79b11f7`/`c60e06b`), cancelación de solicitudes (`cb33822`/`3e9893e`), WCAG 2.2 AA (`5fde0c0`), artefactos + runbook de infraestructura de producción (`03660ae`, sin ejecutar — requiere SSH/DNS real), alertas operacionales + widget de métricas de ciclo de vida (`26110b5`). Cerrado en Linear por decisión explícita del usuario; aplicar el runbook en servidores reales y decidir el pipeline de CI/CD de Claesen-Client quedan como trabajo futuro fuera de este ticket |
| CLA-277 | Pines de marcador para Structure Types (portado de CLA-269/270) | ✅ Done (`bd747ba`) — `StructurePinCatalog`, selector visual en Catalogs, marcador real en mapa de `StructureResource` |
| CLA-278 | Create Luminaire: buscador/filtro de tipo, UX progresiva y media (fotos/videos/documentos) | ✅ Done — ver detalle abajo |
| FO-006 | Slice C.6b — Cutover: frontend Sport → Core, deprecar Sport | ⬜ Todo (ya no bloqueado por la parte de Mantenimiento cubierta en FO-009; si el cutover necesita mantenimiento *programado* a futuro, abrir ticket nuevo para `ScheduledMaintenanceService` antes de cerrar C.6b) |

**Orden de trabajo acordado:** FO-008 → FO-004 → FO-003 → FO-005 → FO-007 → FO-009 → FO-012 → FO-013 → **FO-006**.

### CLA-278 — Create Luminaire: buscador de tipo, UX progresiva y media (2026-07-25)

- **Búsqueda/filtro en el selector visual de tipo de luminaire** (`luminaire-type-gallery-selector.blade.php`, reutilizado también en el modal "Replace Luminaire" de `ViewLuminaire.php`): input de búsqueda + chips de subgrupo (LED/HID/marca), 100% client-side vía Alpine (`x-data` con `types` embebido como JSON) — el catálogo de `LuminaireType` sigue siendo chico (10 filas hoy, ver `LuminaireCatalogSeederTest`), así que no se justifica un endpoint de búsqueda server-side todavía. Si el catálogo crece a cientos de filas, ese es el punto para reconsiderar.
- **UX progresiva:** el picker ahora tiene dos estados (`browsing` en Alpine, no ligado al valor real del campo): sin selección se ve solo la grilla+buscador; al elegir una tarjeta se oculta la grilla, se muestra una tarjeta "seleccionado" (imagen + nombre + botón "Cambiar producto") y recién ahí se revelan el resto de las secciones del formulario (Frame assignment, Technical placement, System reference, Media) vía `->visible(fn (Get $get) => filled($get('luminaire_type_id')))` en cada `Section`. El botón "Cambiar producto" solo reabre la grilla (Alpine local), no borra la selección — el usuario puede volver atrás sin perder nada hasta que elige otra tarjeta.
- **Gotcha real encontrado en QA manual (no en tests automáticos):** el `ViewField` custom del picker ya tenía `->required()` desde antes, y la validación server-side siempre bloqueó el submit vacío correctamente — pero su Blade nunca renderizaba el error (`$errors->has($statePath)`), así que antes de este fix, al ocultar el resto del formulario hasta seleccionar tipo, un clic en "Aanmaken" sin seleccionar nada no mostraba ningún feedback visual (ni borde rojo ni mensaje), pareciendo que "no hacía nada" o que no validaba. Fix: `$hasError = $errors->has($statePath)` en el Blade + borde rojo (`outline`) + `$errors->first($statePath)` debajo del picker. **Lección:** con `ViewField`/Blade custom en Filament, el error de validación no aparece gratis como en un `TextInput` normal — hay que renderizarlo a mano.
- **Bug preexistente encontrado y corregido de paso** (no introducido por este ticket): `fo_luminaires.serial_number` es `NOT NULL` en la BD, pero el campo Filament era `->nullable()` — dejarlo vacío tiraba un `QueryException` 500 crudo en vez de un error de validación. La API/field-app (`LuminaireController::resolveSerialNumber()`) ya resolvía esto generando `AUTO-{timestamp}-{random}` cuando viene vacío; se extrajo esa misma lógica a `LuminaireResource::resolveSerialNumber()` (método público reutilizado por `CreateLuminaire`/`EditLuminaire`) para que el backoffice tenga el mismo comportamiento que el app de campo, en vez de simplemente marcar el campo `->required()`.
- **Media (fotos/vídeos/documentos) en `Luminaire`:** no existía ningún soporte de media en el modelo (a diferencia de Complex/Terrain/Structure/ElectricalBoard). Se replicó el patrón exacto de `HasFieldOpsMedia` (`insteadof` trick para `InteractsWithMedia`). El trait compartido ganó una tercera colección `videos` (mp4/webm/mov, máx 100MB) — Complex/Terrain/Structure/ElectricalBoard heredan la capacidad pero no la usan (no se tocaron sus forms/infolists, fuera de alcance). `FieldOpsMediaController::MODEL_MAP` + la regex de la ruta `store` ganaron `luminaires`. `Http\Resources\LuminaireResource` (API) ahora expone `photos`/`videos`/`documents` vía el mismo `HasMediaPayload` que ya usa `ComplexResource`.
- **Validación:** 101/101 tests del filtro `Luminaire|FieldOpsMedia` en verde (incluye los 9 de `FieldOpsMediaTest` para los otros modelos, sin regresión). Suite completa de `FieldOps` corrida aparte: 264 passed / 88 failed — los 88 son el harness preexistente (`RoleAlreadyExists` por seeding concurrente de roles en tests paralelos, documentado en `feedback_sail_docker`), ninguno toca Luminaire/Media. Verificado manualmente en navegador real con Playwright (usuario QA temporal, borrado al final de la sesión): búsqueda, filtro por subgrupo, ocultar/mostrar grilla, botón "Cambiar producto", validación con error visible, submit exitoso con creación de registro, upload de foto persistido y visible en la vista de detalle.

### CLA-278 (cont.) — bloqueo del producto en Edit + jerarquía Complejo→Terreno→Estructura→Frame en Create (2026-07-25)

- **El producto/tipo instalado es inmutable desde la página Edit** — cambiarlo debe pasar exclusivamente por la acción "Replace luminaire" en la vista de detalle (`ViewLuminaire`), que sí crea una nueva `Luminaire`, retira la anterior y registra mantenimiento atómicamente (CLA-265). Antes de este fix, el `ViewField` de tipo vivía en el mismo `form()` compartido por Create/Edit sin ninguna restricción — mi botón "Cambiar producto" del picker (agregado en la primera parte de CLA-278) lo hizo mucho más visible/tentador de usar en Edit, cuando ya era un bypass del flujo de reemplazo desde antes. Doble candado: (1) UI — `luminaire-type-gallery-selector.blade.php` recibe `locked` (`$operation !== 'create'`, inyectado vía closure de Filament) y en ese modo renderiza solo una tarjeta de solo lectura + link a "Replace luminaire", sin grilla ni botón; (2) servidor — `EditLuminaire::mutateFormDataBeforeSave()` revierte `luminaire_type_id` al valor actual del registro si detecta que difiere, como defensa contra un `fillForm()`/request manipulado que bypasee la UI (`$operation`/`$record` son inyectables en cualquier closure de componente Filament vía `Component::resolveDefaultClosureDependencyForEvaluationByName`, confirmado leyendo `vendor/filament/schemas/src/Components/Component.php`).
- **Una luminaria nunca puede quedar huérfana: Complejo → Terreno → Estructura → Frame es una cadena real de relaciones M:N** (`Complex::terrains()` 1:N, `Terrain::structures()` M:N vía `fo_structure_terrain`, `Structure::luminaireFrames()` M:N vía `fo_luminaire_frame_structure`), no un `belongsTo` simple. El campo `luminaire_frame_id` en Create ya no es un `Select` plano con todos los frames del sistema (no escalaba, mismo problema que el buscador de tipo) — ahora hay 3 selects auxiliares en cascada (`complex_id`/`terrain_id`/`structure_id`, todos `dehydrated(false)` porque no son columnas reales de `Luminaire`, solo scaffolding de UI) que progresivamente acotan las opciones del siguiente nivel y terminan acotando `luminaire_frame_id`.
- **Bug real encontrado y corregido durante el QA con Playwright:** los primeros `whereHas('terrains', fn ($q) => $q->where('terrains.id', ...))` / `whereHas('structures', fn ($q) => $q->where('structures.id', ...))` tiraban `QueryException: Unknown column 'terrains.id'` — el nombre de la relación (`terrains`) no es el nombre de la tabla real (`fo_terrains`), y dentro del closure de `whereHas` el query builder apunta a la tabla real, no al nombre del método de relación. Corregido a `fo_terrains.id`/`fo_structures.id`.
- **Los 3 selects auxiliares son `->required()` solo en `create`** (`fn (string $operation) => $operation === 'create'`), nunca en `edit` — un frame factory-creado (o cualquier frame real sin estructura asignada, algo que `LuminaireFrameCrudTest` confirma que es válido: no hay test "store fails without structure ids") no siempre resuelve una cadena completa hacia atrás, y forzar la jerarquía también en Edit hubiera bloqueado guardar cualquier cambio no relacionado con el frame en luminarias con frames "huérfanos" preexistentes. En Edit, `EditLuminaire::mutateFormDataBeforeFill()` intenta pre-rellenar los 3 campos a partir de `luminaire_frame_id->structures->first()->terrains->first()`, best-effort (si no resuelve, quedan vacíos y el usuario puede ignorarlos sin bloquear el guardado).
- **El `Select` de `luminaire_frame_id` siempre incluye el frame actualmente asignado en sus opciones**, aunque no aparezca en el resultado de la cadena Complejo→Terreno→Estructura (mismo motivo: frames sin estructura asignada) — sin este merge, Filament rechaza el valor guardado por no estar entre las opciones disponibles y la edición se rompe con "Component has errors" incluso sin tocar el campo.
- **Test actualizado:** `LuminaireFilamentTest::test_editing_type_keeps_subgroup_consistent` (que verificaba que cambiar el tipo en Edit actualizaba el subgrupo) se reemplazó por `test_editing_type_via_edit_page_is_ignored` (verifica que un `fillForm(['luminaire_type_id' => ...])` en Edit se revierte silenciosamente al valor original). El aserto de `data-fieldops-luminaire-type-picker` en el test de renderizado de Edit se invirtió a `assertDontSee`, ya que ese atributo ahora solo existe en modo no-bloqueado (Create). 101/101 en el filtro `Luminaire|FieldOpsMedia` tras ambos fixes, sin tocar ningún otro test.

### CLA-278 (cont. 2) — QA manual del usuario: posición ocupada, media privada y documentos (2026-07-26)

- **Aviso reactivo de posiciones ocupadas en el frame:** `luminaire_frame_id` pasó a `->live()` y `frame_position` muestra un `helperText` con las posiciones ya ocupadas en el frame seleccionado (`LuminaireFrame::luminaires()`, ya filtrado a instalaciones activas). `frame_position` también ganó `->live(onBlur: true)` con `afterStateUpdated` propio (y otro simétrico en `luminaire_frame_id`) que llaman a `$livewire->validateOnly(...)` para que el error de "posición ocupada" aparezca/desaparezca en vivo sin necesidad de enviar el formulario. **Gotcha real:** dejar que la `ValidationException` de `validateOnly()` se propague normalmente solo funciona en un request real de Livewire (el hook `SupportValidation::exception()` la intercepta ahí) — dentro de `fillForm()` en tests, ese límite de request no existe y la excepción revienta el test sin más. Fix: capturarla en el propio `afterStateUpdated` y aplicar `$livewire->setErrorBag($exception->validator->errors())` a mano en vez de dejarla propagar.
- **Bug real encontrado por el usuario probando en el navegador:** enviar `frame_position` en 0 o negativo tiraba un `QueryException` crudo (`Out of range value for column`) porque `fo_luminaire_positions.frame_position` es `unsignedInteger` y el campo Filament no tenía cota inferior. Fix: `->minValue(1)`.
- **Segundo bug real, mismo flujo:** crear una luminaria en una posición de frame ya ocupada por otra luminaria activa rompía con `UniqueConstraintViolationException` sobre `fo_luminaires_one_active_per_position` (constraint en `active_position_id`). Fix: regla de validación custom en `frame_position` (closure de Laravel `fn ($attribute, $value, $fail)`, devuelta por un closure evaluado por Filament) que replica la misma condición (`Luminaire::current()` en el mismo frame/posición, excluyendo el propio registro en edición) y falla con un mensaje claro apuntando a "Replace luminaire" en vez de un 500. Aplica igual en Create y Edit.
- **Tercer bug real, más grave — no específico de Luminaire:** las galerías de fotos/videos (`media-gallery.blade.php`, `video-gallery.blade.php`, compartidas por Complex/Terrain/Structure/ElectricalBoard/Luminaire) llamaban a `$media->getUrl()` de Spatie directamente. El disco `local` de FieldOps es privado a propósito (`storage_path('app/private')`, sin `url` configurado) — `Illuminate\Filesystem\FilesystemAdapter::getLocalUrl()` cae en silencio al patrón del disco `public` (`/storage/{id}/{file}`), produciendo una URL que 404 siempre (nunca existió ahí). Bug preexistente en las 5 resources desde que se implementó media (CLA-210/CLA-278), nunca detectado porque ningún test verificaba la URL renderizada, solo el conteo/existencia del media (mismo patrón que `feedback_visual_verification`). Fix: nueva ruta `fieldops.admin.media.show` (`Modules/FieldOps/routes/web.php`, middleware `auth` + `EnsurePanelAccess` — no alcanza con `auth` solo, o un `project_manager`/`client` bloqueado del panel igual podría pedir cualquier media por id) que reusa `FieldOpsMediaController::show()` tal cual; los dos blades ahora arman la URL con `route('fieldops.admin.media.show', $item)` en vez de `getUrl()`.
- **Cuarto bug real, mismo patrón — tampoco específico de Luminaire:** la sección de documentos en las 5 resources nunca tuvo un link real, solo un `TextEntry` con el conteo (`documents_count`). Fix: partial nuevo `document-list.blade.php` (nombre + tamaño + link de descarga vía la misma ruta `fieldops.admin.media.show`), reemplaza el `TextEntry` por un `ViewEntry::make('documents')` en las 5 resources. Clave de traducción nueva `media.no_documents` (en/nl).
- **Quinto hallazgo, sin relación con CLA-278 — descubierto de rebote al investigar el punto anterior:** `ComplexFilamentTest::test_complex_without_client_or_coordinates_renders` afirmaba ver "Create terrain" tras un `$this->get(...)` plano, algo estructuralmente imposible: `TerrainsRelationManager` es un componente Livewire genuinamente *lazy-loaded* (`x-intersect="$wire.__lazyLoad(...)"`, confirmado con `lazyLoaded:false` en el snapshot) — su tabla y header actions nunca están en el HTML de la respuesta inicial, solo llegan tras un round-trip AJAX que dispara el `IntersectionObserver` del navegador. Este assert nunca pudo haber pasado desde que los relation managers pasaron a ser lazy; no tiene relación con ningún cambio de esta sesión. Fix: reemplazado por `Livewire::test(TerrainsRelationManager::class, ['ownerRecord' => $complex, 'pageClass' => ViewComplex::class])->assertSee(...)` — el patrón correcto de Filament para testear relation managers directamente.
- **Validación:** `LuminaireFilamentTest` 9/9, `ComplexFilamentTest` 4/4, `FieldOpsMediaTest` 20/20 (incluye 3 tests nuevos para la ruta admin de media y sus reglas de acceso), `StructureFilamentTest` 7/7, `TerrainFilamentTest` 5/5, `ElectricalBoardFilamentTest` 4/4 — todos en verde, corridos con `sail` real (Docker del usuario).

### CLA-278 (cont. 3) — Frame Types: imágenes reales del seed + bug real de navegación (2026-07-26/27, commit `a935834`)

- **Catálogo `LuminaireFrameType` con imágenes reales:** los 6 placeholders (`Traverse 1-5`, `Balcony`, `image=null`) se reemplazaron por 6 headframes reales (`Curved stadium headframe`, `Fixed cross-arm headframe`, `Fixed platform stadium headframe`, `Lowering headframe`, `Oval stadium headframe`, `Tubular cage headframe`) — el `name` es literalmente el nombre del archivo de imagen, a pedido explícito del usuario. Imágenes en `public/assets/frame-types/*.png`, mismo patrón que `LuminaireTypeSeeder`/`public/assets/luminaire-types/`. Los placeholders viejos se soft-deletan en el seeder (no `forceDelete`: `fo_luminaire_frames` puede tener FKs reales apuntándolos).
- **Fix colateral #1:** `FieldOpsDemoDataSeeder.php` tenía `luminaire_frame_type_id => 3` hardcodeado (asumía el 3er registro del seeder viejo). Al recrear el catálogo ese ID deja de existir. Resuelto con `LuminaireFrameType::query()->value('id')` — el demo no depende de un tipo específico.
- **Fix colateral #2, mismo bug que ya existía en `LuminaireTypeResource` antes de tener su fix:** `LuminaireFrameTypeResource::table()` usaba `ImageColumn::make('image')` sin `getStateUsing()`. El `ImageColumn` de Filament asume por defecto que el state es una ruta relativa al disco `public` (`storage/app/public/...`) — para un valor `/assets/frame-types/xxx.png` (servido directo desde `public/`, no desde el disco), `Storage::disk('public')->exists(...)` da `false` y Filament no renderiza nada. Fix: mismo `resolveImageUrl()` que ya tenía `LuminaireTypeResource` (URL absoluta si ya es http(s)/data:, si no existe en el disco → `asset(ltrim($image,'/'))`).
- **Bug real reportado por el usuario, no relacionado con las imágenes en sí:** en la página Edit de Frame Types, el "Frame image editor" (upload + dibujo en canvas) no mostraba la imagen actual ni respondía a ninguna herramienta — pero **solo cuando se llegaba por click desde el listado**, no al cargar la URL directo. Causa raíz, encontrada verificando con Chrome real (Selenium, no solo tests que solo miran HTML estático):
  1. Filament navega con `wire:navigate` (transición SPA sin recarga completa) por defecto. El componente Alpine se registraba vía `@push('scripts')` + `@once`, envolviendo un `<script>` con `Alpine.data('fieldopsLuminaireFrameTypeImageEditor', ...)`. Un `<script>` insertado en el DOM vía el morph de `wire:navigate` **nunca se ejecuta** (los navegadores solo corren `<script>` que forman parte del parseo inicial de la página, no los insertados por manipulación de DOM) — por eso solo "funcionaba" en una carga dura.
  2. Migrar a `@script`/`@endscript` (mecanismo real de Livewire para JS que debe correr en cada montaje, sobrevive `wire:navigate`) reveló un segundo bug: el código interno esperaba `document.addEventListener('alpine:init', callback)` — ese evento Alpine lo dispara **una sola vez por sesión de navegador**, al arrancar. Como `@script` corre después de que Alpine ya arrancó, el listener nunca se disparaba. Fix: registrar `Alpine.data(...)` directo, sin esperar `alpine:init` (`Alpine.data` con el mismo nombre es idempotente).
  3. Diagnóstico irrefutable solo fue posible instrumentando un browser real: se creó un usuario QA temporal (borrado al final), se manejó Selenium (contenedor ya presente en `docker-compose`, sin Dusk instalado) vía WebDriver REST crudo desde dentro del contenedor `laravel.test` (`http://selenium:4444`), simulando el flujo exacto (login → listado → click en el link de edición) y leyendo el estado reactivo real de Alpine (`window.Alpine.$data(el)`, `Livewire.all()`, píxeles del canvas) — los tests basados en `assertSee()` sobre HTML estático nunca hubieran detectado esto, porque el HTML servido por el backend siempre fue correcto; el bug era 100% de ejecución JS en el navegador.
  4. **Hallazgo de alcance, no corregido a pedido explícito del usuario:** el mismo patrón roto (`Alpine.data(...)` + `addEventListener('alpine:init', ...)` dentro de `@push('scripts')`/`@once`) existe también en `complex-location-picker.blade.php`, `terrain-location-picker.blade.php`, `structure-location-picker.blade.php` y `electrical-board-location-picker.blade.php` — muy probablemente con el mismo bug al navegar por click. **`luminaire-type-gallery-selector.blade.php` (el picker de Create Luminaire) SÍ se revisó y NO tiene el bug** — usa `x-data="{...}"` inline en el propio elemento, no una fábrica registrada por separado, así que no depende de `alpine:init`. Pendiente: ticket propio para auditar/arreglar los 4 location-pickers.
- **Validación:** 18/18 tests (`CatalogFilamentTest`, `LuminaireFrameTypeImageEditorTest`, `CustomLuminaireFrameTypeTest`). Verificación end-to-end en Chrome real (Selenium) confirmando imagen visible en preview, dibujada en canvas, y herramientas (Freehand/Line/Rectangle/Circle) respondiendo (`is-active`, `aria-pressed`, cambio de estado reactivo).

### CLA-278 (cont. 4) — mismo bug de `wire:navigate` en el canvas espacial de Luminaire Frame + fix de UX (2026-07-27, commit `9f2ef37`)

- **Segunda ocurrencia real del mismo bug de CLA-278 (cont. 3), reportada por el usuario probando el flujo de agregar una luminaria:** `luminaire-frame-spatial-layout.blade.php` (el canvas donde se colocan/arrastran luminarias sobre un frame, y el modal "Add luminaire") registraba su componente Alpine como función global plana (`window.fieldopsLuminaireFrameLayout = function(payload) {...}`) dentro de `@push('scripts')`/`@once` — exactamente el mismo anti-patrón, sin el wrapper `alpine:init` esta vez (era una asignación síncrona directa, no un listener de evento). Roto igual bajo `wire:navigate`: toggle Overview/Technical, zoom, drag de marcadores y el botón "Add luminaire" quedaban completamente inertes al llegar por click desde el listado. Fix: mismo patrón que CLA-278 (cont. 3) — `@script`/`@endscript` + `Alpine.data('fieldopsLuminaireFrameLayout', function (payload) {...})`, `@push('styles')`→`@assets` por consistencia (los estilos en sí no estaban rotos — un `<style>` insertado por morph sí se aplica, a diferencia de un `<script>`).
- **Bug de UX real, no relacionado con lo anterior, encontrado por el usuario en la misma sesión:** el canvas (imagen de fondo del frame + grid técnico) solo se renderizaba server-side cuando `count($markers) > 0` (`@if (count($markers) > 0)` en el Blade) — un frame recién creado sin luminarias todavía mostraba únicamente un estado vacío genérico de puro texto, sin la imagen del frame, dejando al usuario sin forma de ver visualmente dónde va a colocar la primera luminaria. Fix de una línea: `@if ($payload['frameImage'] || count($markers) > 0)` — el `@foreach` de marcadores simplemente queda vacío cuando no hay ninguno, así que el canvas se muestra igual con el frame en blanco listo para el primer marcador.
- **Ajuste de tests:** `LuminaireFrameFilamentTest` tenía 3 `assertSee(..., false)` que buscaban texto crudo del `<script>` (`this.viewMode === 'overview'`, `window.Livewire.navigate(marker.url)`, `destination.searchParams.set('layout', 'technical')`) — con `@script`, ese contenido viaja como efecto JSON embebido en un atributo `wire:snapshot`, HTML-escapado (`'` → `&#039;`, etc.), no como texto plano de `<script>` inline. Cambiados a `assertSee($needle)` (escape por defecto) para que coincidan con la forma escapada. Los `assertSee` de markup estático (`wire:navigate`, `setViewMode('technical')`, `x-show="viewMode === 'technical'"`) quedaron igual — están fuera del `@script`, sin cambios.
- **Segunda confirmación real de este anti-patrón en el módulo (2/2 archivos auditados en vivo tenían el bug)** — sube la prioridad de auditar los 4 `*-location-picker.blade.php` documentados como riesgo abierto en `docs/ai/known-risks.md` (siguen sin arreglar, sin ticket todavía).
- **Validación:** 20/20 tests (`LuminaireFrameFilamentTest`, `LuminaireFrameCrudTest`). Verificación end-to-end en Chrome real (Selenium), navegación por click real desde el listado: toggle Overview↔Technical funcionando, botón "Add luminaire" habilitado abre el modal con los 10 tipos de luminaire del catálogo, imagen del frame visible con 0 luminarias.

### CLA-278 (cont. 5) — cierre de huecos de creación huérfana + máximo 2 frames por estructura (2026-07-27, commit `f572bb7`)

- **Auditoría de jerarquía pedida por el usuario:** Complex→Terrain es obligatorio en esquema (`fo_terrains.complex_id` NOT NULL), Luminaire→LuminaireFrame también (`fo_luminaires.luminaire_frame_id` NOT NULL) — pero `Structure↔Terrain` y `LuminaireFrame↔Structure` son M:N puros **sin mínimo de cardinalidad** a nivel de esquema. Confirmado en código (no solo sospecha): `LuminaireFrameResource::form()` no tenía NINGÚN campo de Structure — un frame se creaba huérfano por defecto entrando desde el ítem plano "Luminaire frames" del menú, la asociación solo ocurría si se llegaba con `?structure_ids[]=...` desde la pestaña de una Structure. Además existía una **segunda vía de creación de `Luminaire`** (`LuminaireFrameResource`'s `LuminairesRelationManager`, pestaña "Luminaires" dentro de un Frame) con un formulario propio e independiente del oficial, sin sus mismas protecciones (`luminaire_type_id`/`serial_number` `nullable()` pese a ser `NOT NULL` en BD — riesgo real de 500 crudo — y sin el chequeo de posición ocupada).
- **Regla de negocio confirmada con el usuario:** una Structure nunca tiene más de 2 luminaire frames (límite físico real, no inventado). `Structure::MAX_LUMINAIRE_FRAMES = 2` + `Structure::hasLuminaireFrameCapacity(?int $excludingFrameId = null)` son la única fuente de verdad, consumida por la API, el form de Filament y el `LuminaireFramesRelationManager`.
- **`LuminaireFrameResource::form()`** gana una sección "Location": Complex/Terrain como scaffolding UI (`dehydrated(false)`, igual patrón que `LuminaireResource`) que acotan un campo real `structures` — `Select::make('structures')->relationship('structures', ...)->multiple()`, **nativo de Filament** (sincroniza el pivot solo con guardar, sin código manual), requerido solo en `create`. La regla de capacidad se aplica como closure (no la `Rule` de la API — un `->rule()` sobre un `multiple()` valida el array completo como un solo valor, no ítem por ítem).
- **Gotcha real encontrado implementando el prefill "crear desde esta Structure":** un campo `disabled()` en Filament **no se dehydrata** — si solo se prellenaba `structures` desde el query param sin también derivar `complex_id`/`terrain_id`, el campo quedaba deshabilitado (por `terrain_id` vacío) y el valor prellenado se perdía en silencio al guardar. Fix: `LuminaireFrameResource::contextualTerrain()` deriva el terreno/complejo reales de la primera Structure del query param, así el campo nunca arranca deshabilitado en ese flujo.
- **`CreateLuminaireFrame.php` simplificado:** el mecanismo manual (`public ?array $structureIds`, `mount()` leyendo el query param, `afterCreate()` con `syncWithoutDetaching`) se eliminó completo — innecesario ahora que el campo relationship de Filament sincroniza solo.
- **`LuminaireFramesRelationManager`** (Structure → pestaña Frames): botones "Create"/"Attach" ocultos vía `->visible()` cuando la Structure ya está en el límite, más un `->rule()` en el `AttachAction` como defensa server-side (no confiar solo en ocultar el botón).
- **`LuminairesRelationManager`** (Frame → pestaña Luminaires, el segundo formulario inseguro): `luminaire_type_id` ahora requerido, `serial_number` se autogenera reutilizando `LuminaireResource::resolveSerialNumber()` (ya público), `frame_position` gana `minValue(1)` + el mismo chequeo de posición ocupada del formulario oficial — extraído a `LuminaireResource::hasFramePositionConflict()` (método estático compartido) en vez de duplicar la lógica una segunda vez.
- **Hallazgo real durante el testing, no relacionado con el fix en sí:** Filament oculta `CreateAction`/`AttachAction` **por defecto en páginas `ViewRecord`** (`RelationManager::isReadOnly()`, activo cuando el panel tiene `hasReadOnlyRelationManagersOnResourceViewPagesByDefault()`, que es el default). Ambos huecos que se cerraron acá solo eran alcanzables desde la página **Edit**, no View — un `Livewire::test(RelationManager::class, ['pageClass' => ViewXxx::class])` los muestra ocultos aunque el código esté bien; hay que testear contra `EditXxx::class` para el flujo real de creación. Acción custom (`Action::make(...)->url(...)`, no `CreateAction`/`AttachAction`) no está sujeta a esta regla.
- **Validación:** 99/99 tests (`LuminaireFrameCrudTest`, `LuminaireFrameFilamentTest`, `StructureFilamentTest`, `LuminaireFilamentTest`, `LuminaireCrudTest`, `LuminaireCatalogSeederTest`, `FieldOpsMediaTest`) — cero regresiones.
- **Fuera de alcance de este cierre, a pedido del usuario:** reorganizar el menú lateral (los 5 recursos de la jerarquía Complex→Luminaire son hoy ítems planos sueltos, mezclados con 9 catálogos sin agrupar) y construir breadcrumbs jerárquicos reales (`Complexes > Stadion Bleukens > Terrains > Terrain Main > Structures > Hinged > Luminaire frames`, con URLs anidadas del mismo estilo) — no existe hoy en ningún módulo del repo, hay que construirlo desde cero. El usuario también pidió explícitamente que Structures/Luminaire frames/Luminaires dejen de ser accesibles desde el menú lateral plano, solo alcanzables navegando la jerarquía. Sin ticket propio todavía — próximo trabajo de esta rama.

### CLA-278 (cont. 6) — breadcrumbs jerárquicos + ocultar Terrains/Structures/Frames/Luminaires del menú (2026-07-27, commit `ae4e211`)

- **URLs se quedan planas (`/structures/{id}`), no anidadas.** Decisión explícita, no por costo: `Structure↔Terrain` y `LuminaireFrame↔Structure` son M:N **real en los datos de dev, no solo posible por esquema** (confirmado: 1/6 structures ya está en 2 terrenos, 1/7 frames ya está en 2 structures). Una URL anidada (`/complexes/1/terrains/2/structures/5`) presupone que cada hijo tiene un solo padre — con M:N real no hay "la" URL canónica de una Structure, cambiaría según por dónde navegaste, que es justo lo que una URL no debería hacer. Mismo criterio que usan productos reales con recursos multi-padre (Google Drive: ID plano + breadcrumb contextual según la carpeta desde la que abriste el archivo).
- **El mecanismo nativo de Filament para breadcrumbs anidados (`getParentResourceRegistration()`) asume un solo padre** (reasigna `$parentRecord = $parentRecord->{inverseRelationship}` en un `while`) — incompatible con el M:N real de este módulo. Se construyó `Modules\FieldOps\Filament\Support\FieldOpsBreadcrumbs` en su lugar: un método `xTrail()` (cadena completa incluyendo la entrada propia del registro, usado cuando ese nivel es ancestro de algo más profundo) y un `xAncestors()` (excluye la entrada propia, usado como override de `getResourceBreadcrumbs()` en la página View/Edit de ese mismo nivel — Filament ya agrega automáticamente la entrada del registro actual después).
- **Resolución del padre cuando hay ambigüedad real:** `Structure::resolveTerrain(?int $viaTerrainId)` / `LuminaireFrame::resolveStructure(?int $viaStructureId)` — preferencia por el padre que el usuario realmente navegó (query params `?via_terrain=`/`?via_structure=`, propagados en cada link de relation manager y cross-reference de la cadena: `StructuresRelationManager`, `LuminaireFramesRelationManager` de Structure, `buildCanvasMarkers`/`buildSpatialLayoutState` de `LuminaireFrameResource`, `frameUrl` de `LuminaireResource`, el botón "Open in frame" de `ViewLuminaire`), con fallback determinístico (menor ID) si se llega sin ese contexto (URL directa/bookmark).
- **`Structures\RelationManagers\LuminaireFramesRelationManager` no tenía ningún `recordUrl()`** — clickear una fila de la pestaña "Luminaire frames" de una Structure no llevaba a ningún lado. Se agregó (con `via_structure`), cerrando el flujo de navegación descendente de punta a punta.
- **`shouldRegisterNavigation = false`** en `TerrainResource`/`StructureResource`/`LuminaireFrameResource`/`LuminaireResource` (mismo mecanismo ya usado por `FoMaintenanceRecordResource`) — las rutas siguen vivas, solo desaparecen del sidebar.
- **Hallazgo de paso, sin relación con este cambio, no corregido:** en el canvas del Frame (`luminaire-frame-spatial-layout.blade.php`), cuando el `LuminaireFrameType` no tiene imagen de referencia, se muestra un mensaje "No luminaires placed yet" **aunque el frame sí tenga luminarias** — ese fallback está atado a "sin imagen de frame", no a "sin luminarias", pero reutiliza el mismo texto (`layout_empty_title`/`layout_empty_text`) que el estado realmente vacío. Con los frame types reales (todos con imagen desde CLA-278 cont. 3) no debería manifestarse en producción; solo se vio con un `LuminaireFrameType::factory()` de test/QA sin imagen. Sin ticket, no se tocó.
- **Validación:** 136/136 tests (`LuminaireFrameFilamentTest`, `StructureFilamentTest`, `LuminaireFilamentTest`, `LuminaireCrudTest`, `LuminaireFrameCrudTest`, `CatalogFilamentTest`, `FieldOpsHierarchyNavigationTest` nuevo, `TerrainFilamentTest`, `ComplexFilamentTest`, `ElectricalBoardFilamentTest`, `FieldOpsMediaTest`) — cero regresiones. Verificado además en Chrome real (Selenium): breadcrumb exacto `Complexes > Stadion Bleukens > Terrains > Main field > Structures > #1 — Conical > Luminaire Frames > #1 > View`, y confirmado que el grupo "Field Operations" del sidebar ya no lista Terrains/Structures/Luminaire frames/Luminaires.

### CLA-278 (cont. 7) — rediseño del breadcrumb largo: línea colapsada + fila propia a todo el ancho (2026-07-27/30, commits `719bea9` → `7c6a414` revert → diseño final sin commit de este mensaje)

Tres iteraciones reales con el usuario en la misma sesión, cada una corregida tras verla:

1. **Intento 1** (`f4bbfd4`, revertido en `7c6a414`): una sola línea horizontal con scroll (`flex-wrap: nowrap` + `overflow-x: auto`). Funcionaba pero el usuario no aceptó el diseño.
2. **Intento 2** (`719bea9`): línea colapsada `COMPLEXES › … › STRUCTURES` + los últimos 6 segmentos apilados verticalmente. El usuario aclaró en el siguiente mensaje que en realidad quería todo en una sola línea horizontal — lo de "apilado" en su mensaje anterior era solo cómo lo tipeó en el chat, no un pedido real de diseño vertical.
3. **Diseño final:** una sola línea horizontal normal de breadcrumb, colapsando solo los niveles intermedios lejanos del registro actual (`Stadion Bleukens`, `Terrains`, `test2`) en un `…`, manteniendo visibles el primer nivel (`Complexes`) y todo desde `Structures` en adelante — `Complexes › … › Structures › #6 — Hinged › Luminaire Frames › #7 — Oval stadium headframe › Luminaires › AUTO-... › View`. Se activa solo con más de 7 entradas (umbral en `resources/views/vendor/filament/components/breadcrumbs.blade.php`, override local de `<x-filament::breadcrumbs>` de `vendor/filament/support` vía la convención estándar de Laravel de `resources/views/vendor/{namespace}/...`) — cualquier breadcrumb corto de otro módulo (Safety, Mailing) sigue usando el markup original sin cambios.
4. **Ajuste final de layout, pedido aparte por el usuario:** el breadcrumb compartía fila con los botones de acción del header (`Open in frame`/`Schedule maintenance`/etc.), lo que lo apretaba a una fracción del ancho y forzaba más wrap del necesario. Se lo separó a su propia fila a todo el ancho, arriba del heading+botones — override adicional de `<x-filament-panels::header>` (⚠️ paquete y namespace **distinto** al de `<x-filament::breadcrumbs>`: `filament/filament` se registra como `filament-panels`, no `filament` — el path de override correcto es `resources/views/vendor/filament-panels/components/header/index.blade.php`, un error real cometido primero y corregido al verificar en navegador que el override simplemente no se aplicaba).
- **Validación:** 40/40 tests de jerarquía FieldOps + 157/157 Safety (módulo con breadcrumbs cortos, para confirmar que no se ve afectado). Verificado en Chrome real (Selenium) en cada iteración contra la página exacta del reporte del usuario — enlaces confirmados intactos (incluye `via_terrain`/`via_structure` preservados). 11 fallos preexistentes en `Modules/Mailing` (AbTestingTest/DispatchScheduledTest/FollowUpTest — scheduling/dispatch de jobs en cola) confirmados sin relación causal posible (los archivos tocados en este cambio son solo vistas Blade de header/breadcrumb y CSS del panel, ningún código de Mailing).

### CLA-278 (cont. 8) — deshabilitar el link de los segmentos "tipo" del breadcrumb (Terrains/Structures/Luminaire Frames/Luminaires) (2026-07-31, commit `f051149`)

- Esos 4 índices planos ya estaban ocultos del sidebar (`shouldRegisterNavigation = false`, CLA-278 cont. 6) pero el breadcrumb seguía siendo la última puerta abierta hacia ellos — el segmento "tipo" (ej. "Luminaire Frames") todavía enlazaba al índice sin scope (`/luminaire-frames`). El segmento específico inmediatamente después (ej. "#7 — Oval stadium headframe") ya tenía su propia URL con contexto y sigue clickeable, igual que "Complexes" (único índice plano que sí sigue siendo un browse real, sigue en el sidebar).
- `FieldOpsBreadcrumbs::{terrain,structure,luminaireFrame,luminaire}Ancestors()` ahora clavan esos 4 entries con un sentinel string (`UNLINKED = 'fieldops-breadcrumb-unlinked:'`) en vez de `XxxResource::getUrl()` como key. `null` no era opción como key — PHP lo coacciona a `''`, indistinguible de un href vacío real. El override `breadcrumbs.blade.php` extiende su condición de link/no-link para tratar cualquier key con ese prefijo como texto plano, al mismo nivel que la convención propia de Filament (`is_int($url)` para su entrada de "página actual").
- **Verificación de SPA (segundo pedido explícito del usuario, "verifica que durante la navegacion la web no se recarge"):** no hizo falta cambiar nada — todo link real del breadcrumb ya se arma vía `generate_href_html()` (helper propio de Filament), que decide `wire:navigate`/`wire:navigate.hover` solo según `FilamentView::hasSpaMode()`, automático desde que el panel tiene `->spa()`. Se confirmó empíricamente por Selenium (cada `<a>` del breadcrumb trae `wire:navigate` en el DOM real) y se dejó fijado con un test nuevo (`test_breadcrumb_record_links_use_spa_navigation_not_full_page_reload`).
- 3 tests existentes ajustados (afirmaban la forma vieja del array keyed por URL, ahora afirman que el label está presente sin ser key clickeable) + 3 tests nuevos: los labels tipo nunca son URL en toda la cadena de 5 niveles, renderizan como texto plano (sin `href`) en HTML real, y los links de registro sí traen `wire:navigate`.
- **Validación:** `FieldOpsHierarchyNavigationTest` 16/16 (54 assertions). Regresión: `LuminaireFrameFilamentTest` 11/11 + `StructureFilamentTest` 7/7 — sin regresiones. Verificado visualmente en Chrome real (Selenium) en `/luminaires/23?via_structure=6`: Structures/Luminaire Frames/Luminaires como texto plano, Complexes/#6/#7/número de serie siguen clickeables con `wire:navigate`.

### CLA-278 (cont. 9) — dimming de segmentos sin link + colapso dinámico por overflow real, no por conteo (2026-07-31, commit `a9ecdd8`)

El usuario reportó dos problemas sobre el mismo breadcrumb largo, con captura: (1) los segmentos "tipo" sin link (Structures/Luminaire Frames/Luminaires) eran visualmente indistinguibles de los enlaces reales — el CSS compilado de Filament solo cambia de color un `<a>` en `:hover`, así que a simple vista no había ninguna diferencia; (2) el colapso de CLA-278 (cont. 7) era un umbral fijo por cantidad de entries (`> 7`), sin ninguna noción de si la fila realmente entraba en el ancho disponible — en un viewport más angosto seguía rompiendo a varias líneas, exactamente lo que mostraba la captura del usuario.

- **Dimming:** una sola regla en `theme.css` — cualquier `<span class="fi-breadcrumbs-item-label">` (todo segmento sin link: los labels "tipo", la entrada final "página actual", y ahora también el "…") renderiza a `opacity: 0.55`. Sin tocar los `<a>`, que quedan a opacidad plena — la diferencia es evidente en reposo, sin necesidad de hacer hover.
- **Colapso dinámico real:** `breadcrumbs.blade.php` deja de precalcular en PHP qué colapsar. Ahora renderiza SIEMPRE todos los entries en el DOM, y un componente Alpine (`@script`/`@endscript` — no `@push('scripts')`/`@once`, que muere bajo `wire:navigate`, ver `feedback_livewire_wire_navigate_alpine`) mide si la fila realmente envolvió a una segunda línea (comparando `offsetTop` de cada `<li>` visible) y solo entonces colapsa, en dos pasadas: primero los segmentos "tipo" sin link (son los más baratos de sacrificar, no aportan navegación), y si con eso no alcanza, entries reales uno por uno empezando justo después de "Complexes", hasta que entra en una línea o solo quedan Complexes + la última entrada. Cada tramo contiguo de entries ocultos colapsa en un solo "…" (no uno por entry oculto) — un `<li>` de "…" candidato por índice, mostrado solo cuando ese índice es el inicio de un tramo oculto.
- **Dos race conditions reales encontradas verificando en vivo con Selenium (dev data real, no fixtures):**
  1. El `ResizeObserver` observaba el contenedor padre del breadcrumb, pero colapsar entries cambia la ALTURA de ese mismo contenedor (2 líneas → 1) — el observer se disparaba por su propio efecto colateral, corriendo un segundo `recalc()` en paralelo con uno que todavía no terminaba, dejando un estado inconsistente (varios "…" sueltos en vez de uno solo fusionado). Fix: el callback del observer solo reacciona si `contentRect.width` cambió realmente (compara contra el último valor visto, ignora cambios de alto), más un mutex (`recalculating`/`recalcQueued`) que impide que dos pasadas se entrelacen.
  2. `$nextTick` de Alpine solo garantiza que Alpine terminó de parchear el DOM, no que el navegador ya hizo layout/paint del resultado — medir `offsetTop` inmediatamente después produjo una lectura vieja una vez (`isWrapping()` devolvía `true` sobre un DOM que un instante después ya se veía en una sola línea). Fix: doble `requestAnimationFrame` después de `$nextTick`, técnica estándar para esperar tanto el patch del framework como el layout/paint real del navegador.
- **Validación:** verificado en Chrome real (Selenium) contra datos reales de dev (cadena de 11 entries vía `via_structure=4`): a 1400px solo colapsan los 4 labels tipo (una línea, "…" atenuados, links a opacidad plena); resize en vivo a 720px colapsa correctamente hasta `Complexes > … > AUTO-... > View` (conserva el registro específico + página actual); vuelta a 1400px se re-expande correctamente. Regresión: `FieldOpsHierarchyNavigationTest` 16/16 + `LuminaireFrameFilamentTest`/`StructureFilamentTest`/`TerrainFilamentTest`/`ComplexFilamentTest`/`ElectricalBoardFilamentTest`/`LuminaireFilamentTest` 56/56 (324 assertions) — los tests que afirman `href="..."` exacto siguen pasando sin cambios porque `generate_href_html()` para links reales no se tocó, solo se envolvió en un `x-show` adicional. `Modules/Safety` (breadcrumbs cortos, nunca envuelven) 126/126, confirmando que en el resto del panel esto es un no-op.

### CLA-278 (cont. 10) — recarga completa al navegar a Terrain/Structure/Luminaire (bug real, no relacionado al breadcrumb) + breadcrumb faltante en Maintenance work orders (2026-07-31)

El usuario reportó dos problemas tras validar el trabajo de cont. 8/9: (1) navegar a Terrain/Structure/Luminaire recargaba la página completa (no era SPA); (2) el breadcrumb no contemplaba la página de "Schedule maintenance".

- **Diagnóstico del bug de recarga (agente de investigación en background + verificación propia con Selenium):** NO tenía relación con el trabajo de breadcrumbs de esta sesión. Causa real: varios partials Blade custom de FieldOps (`map-panel.blade.php`, `profile-header.blade.php`, `associated-complexes.blade.php`, `used-by.blade.php`, `luminaire-operational-overview.blade.php`, 3 links sueltos en `luminaire-frame-spatial-layout.blade.php`, 1 en `luminaire-type-gallery-selector.blade.php`) construían `<a href="{{ $url }}">` en crudo para navegación interna del panel, sin pasar por `\Filament\Support\generate_href_html()` — nunca llevaban `wire:navigate`, así que cualquier click en ellos hacía un GET normal del navegador (recarga completa), no una transición SPA. Confirmado empíricamente con Selenium: `window.__marker` seteado antes del click desaparecía después (contexto JS destruido = recarga real), a diferencia de los links de breadcrumb (que sí sobreviven, ya usaban `generate_href_html()` desde antes). `map-panel.blade.php` tenía además un SEGUNDO bug independiente: los pines de Leaflet (no solo la lista lateral) navegaban con `window.location.href = marker.url` en JS puro — mismo síntoma, causa distinta, mismo fix (`window.Alpine.navigate(marker.url)`). `associated-complexes.blade.php` (usado en `FoClient`) tenía un tercer patrón — un `onclick="window.location='...'"` en el `<tr>` completo — corregido al patrón `x-on:click="...Alpine.navigate(...)"` que ya usa el propio `generate_href_html()` de Filament para links anidados en filas de tabla (`hasNestedClickEventHandler`).
- **Por qué el usuario lo notó justo ahora, aunque el bug es preexistente:** no relacionado a esta sesión — simple coincidencia de que recién se estaba probando la navegación a fondo por el trabajo de breadcrumbs. `luminaire-frame-spatial-layout.blade.php` ya tenía UN link con `wire:navigate` estático correcto (línea ~2386, el marcador del canvas técnico) — los otros 3 links de esa misma vista (posición seleccionada, agendar mantenimiento, ver historial) no lo replicaban; se igualaron al mismo patrón local (atributo `wire:navigate` estático) por consistencia con el resto del archivo, mientras que en los demás archivos se usó `generate_href_html()` (más correcto — respeta `hasSpaMode()`/excluye URLs externas automáticamente, `is_app_url()` internamente).
- **Gotcha real de test, no de producto:** `generate_href_html()` construye `href="{$url}"` con interpolación cruda, SIN escapar `&` a `&amp;` (a diferencia de un `{{ $url }}` plano de Blade). Un test existente (`LuminaireFilamentTest`) esperaba el `&amp;` viejo — se corrigió a `assertSee(..., false)` (comparación cruda), mismo patrón ya usado en el resto de tests de esta sesión para asserts de `href`.
- **Breadcrumb de Maintenance work orders (segundo pedido):** `FoMaintenanceWorkOrderResource` (Create/View/Edit) nunca tuvo `getResourceBreadcrumbs()` — cualquier página de "Schedule maintenance" mostraba el breadcrumb genérico de Filament ("Work orders > Create"), sin contexto de a qué luminaria/cuadro pertenece. Se agregó `FieldOpsBreadcrumbs::maintenanceWorkOrderAncestors(string $maintainableType, int|string $maintainableId, ?viaStructureId, ?viaTerrainId)` — para `Luminaire`, reutiliza la cadena completa (`luminaireTrail()`, método nuevo, mismo patrón `xTrail()` que los otros 4 niveles); para `ElectricalBoard`, NO intenta encajarlo en la cadena M:N de Complex→Terrain→Structure (un cuadro puede pertenecer a varios de cada uno, no tiene "el" padre) — usa su propio índice como ancla, igual que Complexes, ya que `ElectricalBoardResource` sigue visible en el sidebar (no es un leaf oculto como los otros 4). "Work Orders" siempre es un link real (el índice sigue siendo un browse legítimo). En `CreateMaintenanceWorkOrder`, el contexto sale de los query params `maintainable_type`/`maintainable_id` (mismos que ya lee `FoMaintenanceWorkOrderResource::form()` para precargar los campos ocultos); en View/Edit, de `$this->record->maintainable_type`/`maintainable_id` directo. De paso, `ViewLuminaire::scheduleMaintenance` ahora reenvía `via_structure`/`via_terrain` en la URL de creación (antes no lo hacía), consistente con el resto de acciones de la página.
- **Validación:** `MaintenanceWorkOrderFilamentTest` +3 tests nuevos (Luminaire en Create/View, ElectricalBoard en View) — 6/6. `ComplexFilamentTest` +1 test (`map-panel`/`profile-header` con `wire:navigate`) — 5/5. Regresión completa: 75/75 (423 assertions) en el filtro `Terrain|Structure|Luminaire|Complex|ElectricalBoard|LuminaireFrame|FieldOpsHierarchyNavigation|MaintenanceWorkOrder|FoClient`. Verificado end-to-end en Chrome real (Selenium): click real en "Schedule maintenance" desde `/luminaires/1` — navegación SPA confirmada (marcador JS sobrevive) y breadcrumb completo `Complexes > Stadion Bleukens > … > #1 — Conical > … > #1 > … > DEMO-LN-041 > Work Orders > Create`, una sola línea, colapsado correctamente pese a tener 13 entries reales (el más largo probado hasta ahora).

### CLA-278 (cont. 11) — el fix de recarga completa se quedó corto en el canvas de Luminaire Frame + falta breadcrumb en Maintenance Records (2026-07-31, commit `3afd81a`)

El usuario reprodujo el mismo síntoma de "cont. 10" en la URL exacta `http://localhost:8000/luminaires/21?via_structure=4` — el fix anterior no lo cubrió del todo — y de paso reportó que la página de Maintenance Records (accesible con "View history" desde un Luminaire) perdía el breadcrumb anidado, mostrando el genérico `Maintenance Records > List`.

- **Por qué el fix de cont. 10 no alcanzó:** `luminaire-frame-spatial-layout.blade.php` tiene el panel de "marcador seleccionado" duplicado **tres veces** — un bloque server-rendered (`href="{{ $selected['url'] }}"`, visible solo brevemente antes de que Alpine hidrate, `x-show="!selectedMarker()"`) que sí se corrigió en cont. 10, y **dos bloques Alpine-driven** (`:href="selectedMarker()?.url"`, uno para modo Overview y otro idéntico para modo Technical, ambos dentro de `template x-if="selectedMarker()"`) que **no** se tocaron. En la práctica, Alpine hidrata casi de inmediato, así que los bloques reactivos son los que el usuario realmente ve y clickea — el bloque server-rendered corregido casi nunca es el que se renderiza. Fix: agregar `wire:navigate` como atributo estático junto al binding reactivo `:href="..."` — no hace falta que forme parte de la expresión de Alpine, Livewire solo revisa que el atributo esté presente en el elemento al momento del click.
- **De paso, mismo archivo:** `LuminaireResource::buildLuminaireOperationalOverview()` y `LuminaireFrameResource::buildSpatialLayoutState()` no reenviaban `via_structure`/`via_terrain` en `maintenanceCreateUrl`/`maintenanceIndexUrl` (sí lo hacían en `frameUrl`, justo al lado) — corregido por consistencia, ahora ambos preservan el contexto de navegación igual que el resto de links de la página.
- **Breadcrumb de Maintenance Records:** mismo gap que `FoMaintenanceWorkOrderResource` en cont. 10, pero en `ListFoMaintenanceRecords` (la página de "View history"). A diferencia de los work orders (`maintainable_type`/`maintainable_id`, soporta Luminaire o ElectricalBoard), esta página siempre llega vía query params `luminaire`/`position` — `LuminaireResource`/`LuminaireFrameResource` nunca construyen este link para un ElectricalBoard. Se agregó `getResourceBreadcrumbs()` reutilizando `FieldOpsBreadcrumbs::luminaireTrail()`.
- **Validación:** `LuminaireFrameFilamentTest` +1 (`wire:navigate` presente exactamente 2 veces por link — uno por bloque Alpine, Overview + Technical — no solo en el fallback), `FoMaintenanceFilamentTest` +1 (breadcrumb con jerarquía completa contiene el link a Complexes). Regresión completa: 72/72 (405 assertions) en el mismo filtro amplio que cont. 10, más `FoMaintenance`. Verificado en Chrome real (Selenium) contra la URL exacta reportada: `/luminaires/21?via_structure=4` ahora sobrevive SPA (marcador de `window` intacto tras el click desde el canvas del frame), y `/fo-maintenance-records?luminaire=21&position=19` muestra `Complexes > test2 > … > #4 — Conical > … > #5 — Fixed cross-arm headframe > … > AUTO-... > List` en vez del breadcrumb plano.

**CLA-278 quedó marcado Done en Linear el 2026-08-01** (confirmación explícita del usuario en navegador real, "funciono como se esperaba"), tras validar el fix de cont. 11.

### FieldOps: breadcrumbs jerárquicos en páginas Create, en TODAS las páginas de Electrical Board, ocultarlo del sidebar, fix de título vacío, y fix del redirect post-create (2026-08-01/02, sin ticket formal, commits `e868110` + `2dc5936` + `efcb586` + `b36807f` + `e2f71a5`)

Pedido explícito del usuario como continuación directa de CLA-278, ya cerrado: aplicar el mismo sistema de breadcrumbs a las páginas **Create** de los 4 recursos, que hasta ahora solo lo tenían en View/Edit. **Sin ticket Linear formal** — al intentar crear uno nuevo, la API devolvió `usage limit exceeded` (tope de issues activos del plan gratuito del workspace); el usuario aprobó explícitamente proceder sin ticket ("proceed sin ticket formal por ahora") en vez de esperar a liberar cupo.

- **Por qué las páginas Create también lo necesitaban, no es solo estética:** sin override, el breadcrumb por defecto de Filament en un recurso con `shouldRegisterNavigation = false` (Terrain/Structure/Luminaire) sigue enlazando el segmento intermedio (ej. "Terrains") al índice plano oculto — exactamente el link que CLA-278 quitó a propósito del sidebar y del breadcrumb de View/Edit. Sin este fix, crear un registro reintroducía esa fuga.
- **Contexto ya disponible, sin query params nuevos:** cada acción "Create X" en los RelationManagers correspondientes ya manda el contexto necesario — `complex_id` (Terrain desde Complex), `terrain_ids[]` (Structure desde Terrain), `complex_id`/`terrain_ids[]`/`structure_ids[]+terrain_ids[]` según la pestaña de origen (Electrical Board, 3 puntos de entrada), y `via_structure`/`via_terrain` (Luminaire, mismo vocabulario que el resto del módulo, aunque hoy ningún caller real los manda — el único punto de entrada de este create es un modal que reutiliza el mismo form, no esta página).
- **4 métodos nuevos en `FieldOpsBreadcrumbs`**, variantes "solo con el padre" de los `xAncestors()` existentes (una página Create no tiene un registro hijo del que derivar el padre): `terrainAncestorsForComplex()`, `structureAncestorsForTerrain()`, `luminaireAncestorsForStructure()`, y `electricalBoardCreateAncestors()` (nuevo — el índice propio de Electrical Board sigue siendo el ancla en su View/Edit por CLA-278 cont. 10, pero al crearlo SÍ se llega desde exactamente un padre concreto, así que esa cadena es real y vale mostrarla; prioriza el contexto más profundo disponible: Structure > Terrain > Complex). `terrainAncestors()`/`structureAncestors()` (las versiones existentes basadas en registro) se refactorizaron para delegar en sus nuevas contrapartes en vez de duplicar la lógica.
- **Validación (commit `e868110`, 4 recursos):** `FieldOpsHierarchyNavigationTest` +7 (2 tests de equivalencia confirmando que los métodos nuevos producen la misma cadena que los basados en registro, 4 tests HTTP renderizando cada Create con contexto real, 1 cubriendo las 3 preferencias de `electricalBoardCreateAncestors()`) — 23/23. Regresión: `TerrainFilamentTest`/`StructureFilamentTest`/`LuminaireFilamentTest`/`ComplexFilamentTest`/`ElectricalBoardFilamentTest`/`LuminaireFrameFilamentTest` 42/42 (279 assertions) — sin regresiones. Verificado visualmente en Chrome real (Selenium): las 4 páginas Create renderizan la cadena esperada con contexto real de query string (el caso más profundo, Electrical Board desde la pestaña de una Structure: `Complexes > Stadion Bleukens > Terrains > Main field > Structures > #1 — Conical > Electrical Boards > Create`), y Create Luminaire sin contexto cae limpiamente a `Luminaires > Create` sin error.
- **Quinto recurso olvidado en la primera pasada, señalado por el usuario (commit `2dc5936`):** `CreateLuminaireFrame` (reached desde la pestaña "Luminaire frames" de una Structure, query param `structure_ids`) se quedó fuera del barrido inicial — mismo síntoma (fuga del link al índice plano oculto). Se agregó `FieldOpsBreadcrumbs::luminaireFrameAncestorsForStructure()` con el mismo patrón "solo con el padre" que los otros 5 métodos, y `luminaireFrameAncestors()` (la versión basada en registro) se refactorizó para delegar en ella. **Lección: al aplicar un patrón a "todos los recursos de tipo X", verificar contra la lista completa de recursos con `shouldRegisterNavigation = false` (grep, no memoria) antes de dar el barrido por completo** — mismo principio ya documentado para el bug `[object Object]` de CLA-274, repetido acá porque no se aplicó la primera vez. `FieldOpsHierarchyNavigationTest` +2 — 25/25. Regresión ampliada (+`LuminaireFrameCrudTest`): 62/62 (328 assertions). Verificado en Chrome real: `/luminaire-frames/create?structure_ids[0]=1` renderiza `Complexes > Stadion Bleukens > Terrains > Main field > Structures > #1 — Conical > Luminaire Frames > Create`; re-confirmado de paso que Electrical Board (ya arreglado en `e868110`) seguía funcionando.
- **El usuario aclaró el alcance real después de ver el resultado (commit `efcb586`): "Electrical Boards debe tener también el sys de breadcrumb, ten en cuenta que el punto de entrada puede ser diferente, todas las páginas relacionadas con Electrical Boards deberían tenerlo"** — no solo Create. A diferencia de Terrain/Structure/Luminaire/LuminaireFrame (recursos ocultos con "un" padre M:N ambiguo pero siempre navegado vía `via_terrain`/`via_structure`), Electrical Board sigue visible en el sidebar y JAMÁS tuvo `getResourceBreadcrumbs()` en View/Edit — dependía 100% del breadcrumb default de Filament (`Electrical boards > {record}`, plano, sin importar desde dónde se llegó). Se renombró `electricalBoardCreateAncestors()` → `electricalBoardAncestors()` (nunca fue realmente específico de Create, solo se llamaba así porque Create era su único caller) y se agregó `electricalBoardTrail()` (incluye la entrada propia del board, mismo patrón `xTrail()`/`xAncestors()` que el resto de la clase) para el branch de ElectricalBoard en `maintenanceWorkOrderAncestors()`, que antes SIEMPRE mostraba la cadena plana aunque hubiera contexto real disponible.
- **Propagación de contexto de punta a punta:** los 3 `ElectricalBoardsRelationManager` (pestaña "Electrical boards" en Complex/Terrain/Structure) ahora mandan `via_complex`/`via_terrain`/`via_structure` (respectivamente, el id del owner record) en su `recordUrl()` — mismo vocabulario que el resto del módulo, `via_complex` es nuevo (no existía antes en ningún lado). `ViewElectricalBoard`/`EditElectricalBoard` ganaron `getResourceBreadcrumbs()` leyendo esos 3 params, y `ViewElectricalBoard::scheduleMaintenance` los reenvía a su vez hacia el work order (mismo tratamiento que `ViewLuminaire` ya tenía). Llegando desde el índice plano del sidebar o desde un marcador del map-panel (código distinto, sin contexto disponible ahí) sigue cayendo correctamente al ancla plana "Electrical boards" — no hay padre real que mostrar en ese caso, comportamiento esperado.
- **Bug real preexistente encontrado y corregido de paso, no relacionado con breadcrumbs:** al escribir el test del RelationManager de Structure, `Structures/RelationManagers/ElectricalBoardsRelationManager.php`'s acción "Create electrical board" construía `terrain_ids` con `->terrains()->pluck('id')` — columna `id` ambigua entre `fo_terrains.id` y el join del pivot `belongsToMany`, tiraba `SQLSTATE[23000]` crudo apenas algo renderizaba de verdad los `headerActions` del componente (nada lo había hecho hasta ahora — los tests previos de Electrical Board solo hacían requests HTTP planos, nunca `Livewire::test()` sobre este RelationManager específico). Corregido a `->pluck('fo_terrains.id')`.
- **Validación (commit `efcb586`):** `FieldOpsHierarchyNavigationTest` +1 (`electricalBoardTrail()` con contexto completo y degradando sin él), `ElectricalBoardFilamentTest` +3 (breadcrumb de View/Edit con contexto, `scheduleMaintenance` reenvía el contexto, el link de fila del RelationManager lleva `via_structure`), `MaintenanceWorkOrderFilamentTest` +1 (`via_complex` para un Electrical Board). Regresión completa: 98/98 (452 assertions) en el filtro amplio de siempre. Verificado en Chrome real navegando por click de verdad (no URL directa) desde la pestaña "Electrical boards" de una Structure: SPA intacto, breadcrumb `Complexes > Stadion Bleukens > Terrains > Main field > Structures > #1 — Conical > Electrical Boards > Cabinet A - Main field > View`.
- **El usuario probó en el navegador y encontró dos problemas más sobre lo recién arreglado (commit `b36807f`):** (1) en un board real, el nivel siguiente a "Electrical Boards" aparecía **vacío** en el breadcrumb; (2) pidió explícitamente que Electrical Board se oculte del sidebar y su segmento de breadcrumb quede deshabilitado, igual que los otros 4 — revirtiendo la decisión original de CLA-278 cont. 10 de mantenerlo visible (esa decisión fue sobre "no forzarlo a la cadena M:N", no sobre visibilidad de sidebar; el usuario las separó explícitamente).
  - **Causa raíz del título vacío:** `ElectricalBoardResource` nunca tuvo `getRecordTitle()` propio — dependía 100% del default `$recordTitleAttribute = 'location_description'`, un campo de texto libre OPCIONAL sin ningún fallback. `Structure`/`LuminaireFrame` sí tienen su propio `getRecordTitle()` con patrón `"#id — TypeName"` que nunca puede quedar vacío; Electrical Board era el único de esta familia sin ese fallback, y el board reportado simplemente no tenía `location_description` cargado — un caso común, no un edge case. Se agregó el mismo patrón `"#id — TypeName"` (usando `ElectricalBoardType`).
  - **Sidebar + breadcrumb:** `ElectricalBoardResource::$shouldRegisterNavigation = false` + `FieldOpsBreadcrumbs::electricalBoardAncestors()`/`electricalBoardTrail()` ahora usan el sentinel `UNLINKED` para el segmento "Electrical boards" en vez de un link real — mismo tratamiento que los otros 4 leaves. El resto de la propagación de contexto (`via_complex`/`via_terrain`/`via_structure`, `efcb586`) no cambió, solo el segmento tipo pasó de link a texto plano.
  - **Validación:** `FieldOpsHierarchyNavigationTest` (3 asserts existentes invertidos a `assertArrayNotHasKey`/texto plano + `ElectricalBoardResource` sumado a los tests de `shouldRegisterNavigation()`/rutas-ocultas), `ElectricalBoardFilamentTest` +1 (`getRecordTitle()` nunca vacío, ni con `location_description` explícitamente `null`). Regresión completa: 66/66 (351 assertions) + 41/41 en los 3 archivos directamente afectados. Verificado en Chrome real reproduciendo el escenario exacto reportado (board con `location_description` vacío): breadcrumb ahora muestra `#5 — Cabinet` en vez de un segmento en blanco, "Electrical Boards" renderiza como texto plano atenuado, y "Electrical boards" ya no aparece en ningún lugar del sidebar izquierdo.
- **El usuario reportó una tercera vez que "el sistema no se está aplicando correctamente" (commit `e2f71a5`, sin captura) — la causa real: el redirect post-create de Filament.** Por defecto, `CreateRecord::getRedirectUrl()` manda al usuario a la página View del registro recién creado **sin ningún query param extra** — para Terrain/Luminaire es inofensivo (resuelven su cadena desde una FK real: `complex_id`, `luminaire_frame_id`), para Structure/LuminaireFrame es un gap silencioso (cae al fallback determinístico "menor id" de la M:N, que a veces coincide por suerte con el padre real y a veces no), pero para Electrical Board es **grave y muy visible**: no tiene NINGUNA FK propia a Complex/Terrain/Structure, así que aterrizar en el View del board recién creado (ej. desde la pestaña de una Structure) mostraba un breadcrumb plano `Electrical Boards > #new > View` — exactamente la jerarquía que se acababa de usar para crearlo, perdida de inmediato.
  - **Fix:** se agregó `getRedirectUrlParameters()` (o se actualizó el `getRedirectUrl()` propio de `CreateStructure`) en las 4 páginas Create, reenviando el mismo contexto `via_structure`/`via_terrain`/`via_complex` que ya usa `getResourceBreadcrumbs()`, vía el hook dedicado que Filament ya expone para esto.
  - **Bug real encontrado implementando el fix:** leer el contexto directo de `request()->input()`/`request()->integer()` DENTRO de `getRedirectUrlParameters()` **no funciona** — confirmado empíricamente, un test de redirect falló con `via_structure` ausente pese a que `Livewire::withQueryParams()` sí lo tenía cargado. Las invocaciones `->call()` de Livewire (el botón "create") no ven de forma confiable el query string de la carga de página original, a diferencia de una lectura fresca de `request()` durante `mount()`/el render inicial — probado guardando el mismo valor en una propiedad durante `mount()` en vez de releer `request()`, que sí funcionó. `CreateLuminaireFrame` y `CreateLuminaire` ahora capturan su contexto `via_*` en propiedades dentro de `mount()`, el mismo patrón que `CreateStructure`/`CreateElectricalBoard` ya usaban correctamente (y por eso "sí funcionaban" desde el principio — esto revela la regla subyacente, no la inventa). Acotado estrictamente al redirect de las páginas Create — el `getResourceBreadcrumbs()` de View/Edit sigue leyendo `request()` sin problema, porque el render de página siempre ocurre en una navegación real (GET), nunca dentro de un POST de acción de Livewire.
- **Validación:** 4 tests nuevos (uno por página Create) con `Livewire::withQueryParams()->test(...)->call('create')->assertRedirect(...)` — afirman la URL exacta del redirect incluyendo los params `via_*`, ejercitando el código real de producción. Regresión completa: 112/112 (517 assertions) en el filtro amplio de siempre + `ElectricalBoardCreateComplexAttachmentTest`.
- **Último punto de entrada que quedaba fuera (mapa):** los pins Leaflet y la lista “Map objects” comparten el URL que reciben de `buildMapPanelState()`. Los tres recursos padre estaban creando el enlace de Electrical Board sin contexto (`/electrical-boards/{id}`), por lo que `ViewElectricalBoard` no podía elegir ningún ancestro aunque el usuario acabara de llegar desde un Complex/Terrain/Structure. Complex ahora envía `via_complex`, Terrain `via_terrain`, y Structure `via_structure` (más el `via_terrain` ya presente en su URL cuando existe). Esto cubre por igual elementos con coordenadas y sin coordenadas, porque ambos consumen el mismo array `items`/`markers`. Se añadieron asserts en los tres tests Filament para las URLs; el harness de MySQL `testing` falló antes de ejecutar aserciones al recrear `migrations`, así que el usuario aprobó un waiver explícito tras validar en navegador el caso real: `Complexes > Stadion Bleukens > Electrical Boards > #1 — Cabinet > View`.
- **Attach de Electrical Board desde Structure:** la pestaña de Structure tenía una `AttachAction` incompleta y el usuario reportó que no aparecía. Al igualarla con Complex/Terrain, Playwright probó que Filament seguía ocultando internamente `AttachAction` (también tras `->visible(true)`), así que en **Structure solamente** se reemplazó por `Action::make('attach')`: mismo botón/modal, `Select` buscable NL/EN/FR/DE, fallback `#id` y `syncWithoutDetaching()`. Playwright ejecutó el flujo completo contra `/structures/7`: botón visible, modal, selección de `#9`, submit y pivot persistido (`[7,9]`), luego desadjuntado. `ElectricalBoardFilamentTest` ganó el test de visibilidad y persistencia; el runner PHPUnit quedó bloqueado por el mismo harness `testing`, con waiver explícito del usuario tras la evidencia E2E.
- **Picker de mapa Electrical Board light/dark:** se quitó `data-theme="light"` hardcodeado de `electrical-board-location-picker.blade.php` y se portó el set dark de Terrain para contenedor, header, título, descripción, pill de coordenadas, hint, footer y controles Leaflet. El `init()` existente vuelve a establecer `data-theme` dinámicamente; el registro Alpine `@push/@once` quedó intacto, porque su migración a `@script` es el riesgo conocido con ticket propio. Playwright verificó estilos computados reales en Create: dark produce `data-theme="dark"`, gradiente `#171725 → #11111a`, título claro/footer `#94a3b8`; al alternar a claro obtiene el gradiente blanco, título `#0f172a` y footer `#64748b`. Usuario confirmó el resultado visualmente.

### CLA-342 — Electrical Board hereda coordenadas del padre + fallback via env + bloquear creación sin padre (2026-08-04)

Reportado por el usuario: al crear un cuadro eléctrico, el pin del mapa no heredaba las coordenadas de la instalación a la que pertenece — solo funcionaba creando desde la pestaña de un Complex. Causa: `ElectricalBoardResource::resolveLocationDefaults()` solo leía `complex_id`, nunca `terrain_ids`/`structure_ids`, aunque los 3 `ElectricalBoardsRelationManager` (Complex/Terrain/Structure) ya enviaban el query param correcto. `StructureResource::resolveLocationDefaults()` (Structure hereda de Terrain) sirvió de plantilla directa.

- **Cascada de herencia extendida a los 3 contextos:** `resolveLocationDefaults()` ahora acepta `terrain_ids`/`structure_ids` además de `complex_id`. Prioridad structure > terrain > complex (defensivo — por diseño de los relation managers solo uno llega poblado a la vez). Cadena de fallback: Structure propia (zoom 17) → uno de sus Terrains vía `Structure::resolveTerrain()` ya existente (zoom 16) → Complex de ese Terrain (zoom 15) → default global. El label de contexto (`complex_label`, viewData del picker) ahora usa `StructureResource::getRecordTitle()` cuando el contexto es una Structure, en vez de reinventar un label propio.
- **Fallback hardcodeado `51.1635`/`5.1640`/zoom `16` → config/env**, estaba duplicado en 8 lugares (4 `resolveLocationDefaults()` de Complex/Terrain/Structure/ElectricalBoard + 4 blade pickers + `map-panel.blade.php`). Nuevo bloque `fieldops.default_map` en `Modules/FieldOps/Config/config.php` (`FIELDOPS_DEFAULT_MAP_LAT/LNG/ZOOM`, default = mismos valores de siempre). Los 4 resources y las 5 vistas Blade leen de ahí — ninguna vista lee `env()` directo, todas reciben el valor ya resuelto por PHP.
- **No debe ser posible crear un cuadro eléctrico sin padre — corrección real de diseño durante la implementación, no del plan original:** la primera idea (`ElectricalBoardResource::canCreate(): false`, mismo patrón que `Complex`/`FoClient`) se descartó al verificar `vendor/filament/filament/src/Resources/Pages/CreateRecord.php:66` — `authorizeAccess()` hace `abort_unless(static::getResource()::canCreate(), 403)` **antes** de que corra cualquier guard propio de `mount()`, así que `canCreate(): false` habría bloqueado también las 3 vías legítimas (desde Complex/Terrain/Structure), no solo la huérfana. Fix real: `canCreate()` se deja en su default (`true`), se quita el `CreateAction` del índice plano oculto (`ListElectricalBoards::getHeaderActions()` → array vacío) y se agrega el guard real en `CreateElectricalBoard::mount()` — `abort(403)` si ninguno de los 3 query params resuelve a un registro existente en BD. Esto cierra el único hueco real (`/electrical-boards/create` visitado directo, sin contexto o con un id inventado) sin tocar las 3 vías contextuales.
- **Regresión real encontrada corriendo tests, no en la primera pasada:** 3 tests existentes (`ElectricalBoardCreateComplexAttachmentTest`, `StructureFilamentTest`, `TerrainFilamentTest`) creaban un `CreateElectricalBoard` vía `Livewire::test(...)->set('complexId'|'structureIds'|'terrainIds', ...)` — un atajo de test que fija la propiedad Livewire manualmente sin pasar por la query string real. El guard nuevo corre en `mount()` contra `request()`, no contra esas propiedades, así que ese atajo ahora siempre aborta 403 antes de que el `->set(...)` posterior llegue a ejecutarse (`Invalid Livewire snapshot` en vez de un 403 limpio, porque el abort interrumpe el mount a medio camino). Corregidos los 3 a `Livewire::withQueryParams([...])->test(CreateElectricalBoard::class)` — más fiel al flujo real de todos modos, ya que ningún llamador real fija esas propiedades a mano.
- **Validación:** 9 tests nuevos (cascada completa de fallback structure→terrain→complex, fallback lee de config override, guard 403 en sus 2 variantes — sin contexto y con id inexistente —, botón "New" ausente del índice plano) + los 3 tests corregidos arriba. 69/70 en verde en la corrida combinada `FieldOpsHierarchyNavigation|ElectricalBoard|Structure|Terrain|Complex`; el único fallo (`TerrainFilamentTest::test_edit_terrain_uses_the_recorded_coordinates_as_its_initial_pin_location`) es el mismo preexistente ya documentado en CLA-340 (falla igual en `HEAD` sin estos cambios, confirmado con `git stash`).

### CLA-340 — fix persistencia de posición del pin en Complex/Structure/ElectricalBoard tras guardar (2026-08-04)

Reportado por el usuario: al cambiar la localización de un Complex (mover el pin en el mapa) y guardar, la posición no se mantenía una vez modificada. Mismo bug que **CLA-305** (commit `bc29b00`, 2026-08-03) ya diagnosticó y corrigió para `Terrain`, nunca portado a `Complex`, `Structure` ni `ElectricalBoard` — confirmado por lectura directa de código (no solo ausencia en git log) que los tres compartían el mismo patrón pre-fix:

- `Edit{Recurso}.php` no sincronizaba `map_center_lat`/`map_center_lng` (campos `Hidden`, `dehydrated(false)`, no son columnas reales) en `mutateFormDataBeforeFill()`, ni tenía `afterSave()` (`$this->record->refresh()` + `$this->fillForm()`). Sin esto, al re-llenar el form tras guardar, esos campos "centro" caían de nuevo al `default()` del schema, calculado con las coordenadas viejas de esa misma request.
- `{recurso}-location-picker.blade.php` (Alpine + Leaflet): `marker.on('dragend', ...)`/`map.on('click', ...)` llamaban a `syncFromLatLng(...)` sin el tercer argumento (`syncCenterFields`), y la escritura de `centerLatInput.value`/`centerLngInput.value` no disparaba `dispatchEvent('input'/'change', {bubbles:true})` — a diferencia de `latInput`/`lngInput`, que sí lo hacían.

Fix: mismo diff mecánico de CLA-305 portado a los 3 recursos (`EditComplex.php`, `EditStructure.php`, `EditElectricalBoard.php` + sus 3 `*-location-picker.blade.php`). En Structure/ElectricalBoard el `mutateFormDataBeforeFill()` ya existía (fix de traducciones de CLA-269) — se extendió, no se creó uno nuevo.

**Hallazgo real durante el testing, no específico de este ticket:** el `assertSee(..., false)` (comparación cruda) que el test original de Terrain (CLA-305) usaba para verificar el JS del picker no puede pasar nunca — el contenido de un bloque `@script` de Livewire viaja embebido como efecto JSON dentro del atributo `wire:effects`, HTML-escapado (`'` → `&#039;`, `=>` → `=&gt;`), no como texto plano de `<script>` inline. Mismo mecanismo ya documentado para breadcrumbs en CLA-278 cont.8. Los 3 tests nuevos de este ticket usan `assertSee($needle)` (escape por defecto) en vez de `assertSee($needle, false)`. El test de Terrain (`TerrainFilamentTest::test_edit_terrain_uses_the_recorded_coordinates_as_its_initial_pin_location`) no se tocó — falla por una causa previa no relacionada (`defaultPinVariant` no coincide), confirmada como preexistente corriendo el mismo test contra HEAD sin ningún cambio de este ticket; queda fuera de alcance.

**Validación:** `ComplexEditLocationMapTest`/`StructureFilamentTest`/`ElectricalBoardFilamentTest` (filtro combinado) 21/21 en verde. Suite completa de FieldOps: 151 fallos con el fix vs. 149 fallos en la misma corrida contra HEAD sin el fix (confirmado con `git stash`) — el delta de 2 es ruido del harness ya documentado (`RoleAlreadyExists`, DB `testing` compartida entre clases de test en paralelo, ver `feedback_sail_docker`), confirmado por grep que los bloques de fallo de las clases afectadas en esa corrida completa son literalmente `RoleAlreadyExists`, no fallos de aserción del fix.

### CLA-362 — expone luminaire_frame_id/lat/lng en MaintenanceWorkOrderResource (2026-08-07, commit `ddf16fc`)

Extiende CLA-351: esa memoria de cierre solo documenta `structure_id`/`terrain_id`/`complex_id`, pero su propia descripción original ya pedía los 6 segmentos de URL (`complex/terrain/structure/lat/lng/frame+posición`) para que el frontend reconstruya el enlace de vuelta desde "My Tasks" al detalle de la luminaria. La sesión que agregó los 3 campos restantes (`luminaire_frame_id`, `lat`, `lng` del `Terrain` resuelto) quedó sin comitear por un cierre inesperado de la máquina — retomada y cerrada en esta sesión.

- `MaintenanceEquipmentContextService::resolveLocationIds()` suma `lat`/`lng` (del mismo `Terrain` ya resuelto para `terrain_id`) al array devuelto. `MaintenanceWorkOrderResource` suma `luminaire_frame_id` (columna directa de `Luminaire`, sin resolver nada) junto a los 5 campos.
- Mismo alcance que CLA-351: solo `Luminaire`, `null` para `ElectricalBoard` (sin cadena de padre única).
- Cambio colateral no relacionado: `config/cors.php` suma `http://localhost:5180` (puerto de dev local).
- **Test nuevo:** `MaintenanceWorkOrderTest::test_assigned_queue_only_returns_the_workers_orders_with_equipment_context` ganó 6 aserciones (antes solo cubría `kind`/`serial_number`/`client.id`) verificando los 6 campos de location contra el registro real creado por el helper `luminaireWithClientContext()`. 26/26 tests en verde (126 assertions, antes 120) en el filtro `MaintenanceWorkOrder`.

### CLA-339 — fix TypeError en asignación de Maintenance Work Orders (2026-08-04, commit `80db935`)

Reportado por el usuario vía la página de error de Laravel al editar una work order: `assertAssignableEmployee(): Argument #1 ($employeeId) must be of type ?string, int given`. Causa real: `employees.id` es `string` (regla general de IDs del ERP legacy), pero valores numéricos como `"100"` — al pasar por el array de `options()` del `Select::make('assigned_employee_id')` de Filament (`pluck('name', 'id')`), PHP castea automáticamente esas keys a `int` (comportamiento del lenguaje, no de Filament ni de Eloquent). `MaintenanceWorkOrderService` tiene `declare(strict_types=1)`, así que el `int` filtrado hasta `assertAssignableEmployee(?string $employeeId)` revienta.

**Intento fallido documentado para no repetirlo:** castear a `(string)` las keys al construir el array de opciones (`mapWithKeys(fn ($e) => [(string) $e->id => $e->name])`) **no arregla nada** — PHP normaliza cualquier key de array que sea una string numérica canónica a `int`, sin importar cómo se haya escrito el cast en el código userland; es una regla del motor sobre la key en sí, no del tipo declarado en PHP. **Fix real:** normalizar `assigned_employee_id` a `?string` en el punto donde se lee de `$data`, dentro de `MaintenanceWorkOrderService::create()`/`updatePlanning()` — y sobreescribir el valor en el array persistido (`$data`/`$changes`) para que lo guardado y lo comparado (`$previousEmployeeId !== $newEmployeeId`) usen el mismo tipo (el mismatch de tipos también causaba un bug silencioso: reasignación fantasma registrada en cada edición aunque el empleado no cambiara).

Test de regresión: `MaintenanceWorkOrderFilamentTest::test_editing_work_order_assignee_with_numeric_employee_id_does_not_throw`, con un `Employee::create(['id' => '100', ...])` real ejercitando `EditMaintenanceWorkOrder` vía Livewire. 48/48 tests en verde (264 assertions) en el filtro `MaintenanceWorkOrder|MaintenanceRequest|FoMaintenance|MaintenancePlan`, sin regresiones.

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
