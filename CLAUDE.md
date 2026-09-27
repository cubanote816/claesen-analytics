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
| `laravel-13-readiness.md` | Baseline de PHP/Composer, entornos y riesgos del programa CLA-514 |
| `laravel-13-compatibility-matrix.md` | Matriz exacta, lockfile objetivo simulado y gate previo a CLA-519 |
| `laravel-13-staging-certification.md` | Checklist de ejecución de CLA-525 (E2E por rol + subsistemas) + ensayo de rollback / health check (CLA-523) |
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
| Backend | Laravel 12 / PHP 8.3+ |
| Admin UI | Filament V5 (Bleeding Edge) |
| DB local | MySQL 8.4 |
| DB legacy | SQL Server 192.168.254.102 (ReadOnly) |
| Módulos | nwidart/laravel-modules ^12.0 |
| Auth | Laravel Sanctum + Azure OAuth (Microsoft Graph) |
| RBAC | spatie/laravel-permission |
| IA | Google Gemini (alias `gemini-flash-latest`, `GEMINI_API_URL` en `.env`) + Anthropic Claude Sonnet 5 (`Modules/Intelligence/Services/ClaudeVisionService`, identificación visual FieldOps) |
| Infra | Docker Sail, Redis, Meilisearch |

---

## Historial de cierres técnicos — Laravel 13 (CLA-514 y subtickets)

El detalle completo de cada cierre técnico (CLA-515 a CLA-532) se archivó en `docs/ai/decisions-log.md` — no es lectura obligatoria. Consultar solo si una tarea toca puntualmente uno de esos tickets.

---

## Restricciones críticas — NUNCA ignorar

1. **SQL Server es ReadOnly.** Jamás generar `save()`, `update()`, `create()`, `delete()` en conexión `sqlsrv`. Todos los modelos Cafca usan `ReadOnlyTrait`. Lanza `LogicException` si se intenta mutar.

2. **Filament V5 únicamente.** Usar `Filament\Schemas\Schema` para Forms e Infolists. NO clases de V3/V4.

3. **IDs nunca son enteros.** Los IDs del ERP legacy son strings. Siempre `trim()` en modelos Cafca.

4. **Idioma:** código/variables/comentarios en inglés. UI/labels/notificaciones en holandés (NL) para navegadores NL, inglés para el resto.

5. **`project_manager` no tiene acceso al panel Filament** (CLA-205, 2026-07-03). `User::hasPanelAccess()` es la fuente única de verdad; `canAccessPanel()` sigue permitiendo el login (solo mira `is_active`) y el gate real lo aplica el middleware `EnsurePanelAccess`, que redirige a `/auth/no-access` (página de bienvenida propia) en vez de usar el 403 nativo de Filament. No volver a agregar `project_manager` a ningún `canAccess()` de recurso/página del panel — usa la PWA de Safety, no este backoffice. **`technician` es la excepción deliberada** (CLA-581, 2026-09-22): tiene `hasPanelAccess()` true, pero acotado por diseño a `Modules/FieldOps/Filament/Pages/MyWorkOrders` — todo recurso/página existente (incluidos los que no tenían `canAccess()` propio y por defecto de Filament quedaban abiertos a cualquier usuario del panel, ver `helpers.php::get_authorization_response`) excluye explícitamente `technician`. `Modules\Core\Filament\Pages\Auth\Login::authenticate()` (CLA-363) también dejó de bloquear su intento de login — solo `client` sigue en ese denylist. Cualquier resource/page nuevo sin `canAccess()` propio hereda ese default-allow de Filament — agregar el guard explícito si no debe ser visible para `technician`.

6. **Sesión expirada (419) usa modal branded, no el `confirm()` nativo de Livewire** (CLA-208, 2026-07-04). Interceptado vía `Livewire.hook('request', ({fail}) => fail(({status, preventDefault}) => {...}))` en `Modules/Core/resources/views/filament/session-expired-modal.blade.php`, enganchado al `PanelsRenderHook::BODY_END` de `AdminPanelProvider.php`. No revertir a dejar pasar el 419 sin `preventDefault()` — el `confirm()` del vendor volvería a dispararse. Si se agregan clases Tailwind arbitrarias nuevas en esta u otras vistas, correr `npm run build` local para verlas (no afecta producción, `deploy.sh` ya lo hace).

7. **En prod-priv-01, `opcache.validate_timestamps=0`** (`/etc/php/8.4/fpm/conf.d/10-opcache-prod.ini`) — PHP-FPM nunca relee archivos por su cuenta (CLA-232, 2026-07-06: esto rompió el login de Azure OAuth porque `config:cache` reescribía el archivo pero los workers seguían con el bytecode viejo). `infrastructure/scripts/deploy.sh` ya recarga PHP-FPM en cada deploy completo (paso 9) — no quitar ese paso. Para ediciones manuales de `shared/.env` **sin** un deploy completo, correr `infrastructure/scripts/reload-config.sh` (config:clear + config:cache + `systemctl reload php8.4-fpm`), nunca solo `config:cache` a mano.

8. **`SESSION_DOMAIN` no puede ser un valor estático en `.env`** (CLA-233, 2026-07-06). Filament (`backoffice.claesen.local`) y la PWA Safety/`service.claesen-verlichting` (Sanctum SPA sobre `*.claesen-verlichting.be`, requiere cookie compartida entre `service.claesen-verlichting.be` y `backend.claesen-verlichting.be`) necesitan dominios de cookie *incompatibles* — arreglar uno con un valor fijo en `.env` rompe el otro. Se resuelve en `Modules/Core/Http/Middleware/ResolveSessionCookieDomain.php` (middleware global, `$middleware->prepend(...)` en `bootstrap/app.php`, corre antes que `StartSession`/`VerifyCsrfToken`/Sanctum stateful) usando `EnsureFrontendRequestsAreStateful::fromFrontend()` (Origin/Referer contra `SANCTUM_STATEFUL_DOMAINS`) — **no** `$request->getHost()`: el túnel que trae el tráfico público de la API reescribe el Host interno a `backoffice.claesen.local` antes de llegar a Laravel, así que Host es indistinguible entre Filament y la SPA; solo Origin/Referer sobrevive el proxy. No volver a fijar `SESSION_DOMAIN` a un valor concreto en `.env` de producción — debe quedar `null` (el middleware lo sobreescribe en runtime). Si aparece un tercer dominio consumidor, agregarlo a `SANCTUM_STATEFUL_DOMAINS`, no tocar este middleware ni el `.env`.

9. **Todo PDF nuevo debe incluir `@include('core::pdf.letterhead')`** al inicio del `<body>` — membrete corporativo oficial (logo + 3 columnas KANTOOR/MAATSCHAPPELIJKE ZETEL/contacto, ver `Modules/Core/resources/views/pdf/letterhead.blade.php`), no reinventar el encabezado. **Nombre de marca correcto: "Claesen Outdoor Lighting Platform"** (CLA-234, 2026-07-07) — "Claesen Intelligence Hub" es un nombre viejo ya erradicado del código, no reintroducirlo. Detalle completo (datos de la empresa, variantes de logo, y el bug real de `MicrosoftGraphTransport`) en memoria `project_official_branding`. **Gotcha crítico de emails con logo embebido:** `$message->embed(public_path('img/brand-logo-{light,dark}.png'))` es obligatorio (nunca una URL externa — se rompe), pero **no alcanza por sí solo**: `MicrosoftGraphTransport::getPayload()` (`Modules/Mailing/Mail/Transport/`) debe marcar el adjunto embebido con `isInline: true` + `contentId` (ya corregido) o Microsoft Graph lo manda como adjunto suelto y el `<img src="cid:...">` queda roto — cualquier transport nuevo que se agregue debe replicar este mismo tratamiento para adjuntos con `hasContentId()`.

10. **En `app/Providers/Filament/AdminPanelProvider.php`, todo `->label(__(...))`/`->group('...')` de `NavigationGroup`/`NavigationItem` debe ser un closure** (`fn () => __(...)`), nunca un string plano ya evaluado (CLA-235, 2026-07-07). `panel()` corre una sola vez al bootear la app, en el locale por defecto (`APP_LOCALE=nl`) — un `__()` evaluado ahí queda congelado en holandés para siempre, mientras que `Resource::getNavigationGroup()` evalúa `__()` de nuevo en cada request con el locale real del visitante (fijado después por `BrowserLocaleMiddleware`). Como las dos strings no coinciden en ningún locale que no sea el de boot, el `array_search` interno de Filament para ordenar grupos falla en silencio y el orden del sidebar queda determinado por azar (orden de descubrimiento de recursos), y un `NavigationItem::group('string hardcodeado')` sin traducir crea un grupo duplicado en cualquier otro idioma. `NavigationGroup::label()`/`NavigationItem::group()`/`NavigationItem::make()` aceptan `string|Closure` — usar siempre closure.

11. **Antes de crear o modificar cualquier vista Blade/Filament o token de estilo (Tailwind/CSS), leer `DESIGN.md`** (CLA-380, 2026-08-14). Si el cambio toca colores o tipografía, correr `npx @google/design.md lint DESIGN.md` antes de comitear. Regla replicada en `.agents/rules/00-project-startup.md` (leído "Always On" antes de cualquier edición) — no basta con la mención pasiva que tenía antes en la sección "Identidad visual" de este mismo archivo. Si `DESIGN.md` y el código divergen, reportar el conflicto y actualizar `DESIGN.md` para reflejar el cambio real aprobado — no dejarlo desactualizado en silencio. **Gotcha real (CLA-580, 2026-09-22):** las utilidades de `resources/css/app.css` (`.bg-mesh-signature`, etc.) NO están disponibles en ninguna vista Blade compilada bajo el panel Filament — `resources/css/filament/admin/theme.css` (el Vite theme real del panel, vía `->viteTheme(...)`) no importa `app.css`; solo redefine `.glass-signature` localmente. Antes de reutilizar una clase de `DESIGN.md`/`app.css` en una vista de `Modules/**/Filament` o `app/Filament`, verificar en qué archivo CSS está definida esa clase, no asumir por la documentación. **Resuelto en `theme.css` (CLA-385, 2026-09-22):** se agregó `--color-claesen-orange` y `.bg-mesh-signature` (adaptada a los tokens reales de `DESIGN.md` — cyan+naranja, sin el índigo/rosa del `app.css` original) directamente a `theme.css`. **Segundo gotcha encontrado en el mismo ticket:** los `@source` de `theme.css` no cubrían `resources/views/livewire/**` — Tailwind nunca escaneaba ese directorio para este build, así que ninguna variante de opacidad (`claesen-orange/10`, `/20`, etc.) ni `from-claesen-orange` compilaba, aunque las clases base sí (por casualidad, también usadas en un archivo sí cubierto). Cualquier clase Tailwind nueva en una vista Livewire (`resources/views/livewire/**`) fuera del panel Filament debe verificarse igual: confirmar que su `@source` esté cubierto, no asumirlo por estar "dentro de `resources/`".

---

## Módulos

| Módulo | Descripción | Estado |
|--------|-------------|--------|
| **Cafca** | Modelos ReadOnly del SQL Server ERP (Project, Labor, Invoice, Employee…) | ✅ ~90% |
| **Core** | Auth (Azure OAuth + Laravel), RBAC Spatie, Filament V5 admin panel, user provisioning (USR-001) | ✅ ~98% |
| **Intelligence** | Gemini (`gemini-flash-latest`), Claude Sonnet 5 (visión FieldOps), Mirror SQL→MySQL, Similarity (Nearest Neighbors), Budget Assistant | ✅ ~90% |
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

## Identidad visual — `DESIGN.md` (CLA-379, 2026-08-14)

`DESIGN.md` en la raíz del repo documenta la paleta real (Claesen Cyan `#00aeef`/Lime `#a5d610`/Magenta `#e6007e`/Amber `#fcd34d` + acento Orange `#f97316` "signature"), tipografía (Outfit para UI digital, sans-serif genérica para PDFs) y componentes clave, siguiendo la especificación abierta [`google-labs-code/design.md`](https://github.com/google-labs-code/design.md) (formato YAML front-matter + Markdown, **no es un MCP** — es un CLI local sin auth: `npx @google/design.md lint DESIGN.md`). Es documentación derivada de `AdminPanelProvider.php`/`tailwind.config.js`/`app.css`, no una fuente nueva de verdad — si esos archivos cambian, `DESIGN.md` debe actualizarse junto con ellos. Lint en verde (0 errores); quedan 3 warnings de contraste WCAG AA documentados en el propio archivo como hallazgo conocido y deliberadamente no corregido (ajustar los colores reales de producción es una decisión aparte, no tomada en este ticket).

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

## Programa multiempresa Electro Bertels — F0–F4 + P1–P6 hechos, P7 pendiente (rama `electrobertels/trunk`)

> Diseño y reglas de secuencia: `docs/ai/adr-multi-organization.md` (leer antes de tocar organización, sitio, contexto o enforcement). Detalle por ticket: `docs/ai/multiorg-decisions-log.md`.

- Una app Laravel, una BD, **un panel Filament por organización** (`admin` = Claesen, `bertels`). `users.organization_id` define la pertenencia; `site_id` es la fuente de verdad del dominio compartido de Website.
- Secuencia no negociable: estructura → contexto → autorización → enforcement. El enforcement se gobierna con `ORGANIZATIONS_ENFORCE` (`config('organizations.enforce')`); rollback = `false` + `infrastructure/scripts/reload-config.sh`.
- `OrganizationContext` es un binding `scoped`, nunca `singleton`. `Gate::before` aplica primero la frontera de organización y luego el privilegio de `super_admin`.
- Regla de hierro (D10): no debe existir ningún usuario real de Bertels hasta que P5 esté verificada; el alta de Electro Bertels ocurre al final de P7. MFA (D8) es gate previo al primer login real de Bertels.
- De Mailing solo se comparte el transporte transaccional (D11), nunca la plataforma de campañas. El tenant de Microsoft 365 es compartido: el From se resuelve desde configuración del sitio, nunca de entrada de usuario.
- **Pendiente (P7):** `NOT NULL`, flag activo en producción, alta de Bertels y decisión de MFA; bloqueado por staging (CLA-530/531/525).

---

## Sprint FieldOps — EN CURSO (rama de trabajo: `fieldops-backend-fixes`)

> Auditoria comparativa 2026-07-03 contra el satélite anterior `api-claesen-sport-app`. Ver `handoff.md` para el detalle completo.

> **Rama fuente de verdad (CLA-295, 2026-08-04):** todo el trabajo activo de FieldOps se comitea en `fieldops-backend-fixes`, no en `main`. `main` es la rama de release/deploy — `infrastructure/scripts/deploy.sh` clona `origin/main` directamente (`git clone --branch main`), así que solo lo que llega a `origin/main` se despliega. `fieldops-backend-fixes` se reconcilia en `main` periódicamente (merge o fast-forward según si `main` acumuló commits propios) y luego se empuja a `origin/main` explícitamente — nunca asumir que comitear en `fieldops-backend-fixes` por sí solo actualiza producción. Antes de CLA-295, `origin/main` llevaba ~1 mes sin push (desde 2026-07-07) y `fieldops-backend-fixes` nunca se había subido al remoto.

### Estado

`fo_admin` (Slices C.1→C.6a) ya está mezclado en `main` y `origin/main`. El menú "Field Operations" en Filament ya **no** lleva la etiqueta "(Demo)" (`lang/en,nl/navigation.php`, clave `navigation.groups.field_operations`, CLA-541, 2026-09-15) — el módulo tiene consumidores reales en producción (Claesen-Client, portal PWA de mantenimiento; CLA-275/276) y la batería de seguridad CLA-496/497/498 está Done.

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
| CLA-386 | Identificación visual de luminarias en frame — MVP de backend con Claude Sonnet 5 | ✅ Done — ver detalle abajo |
| CLA-388 | Conectar identificación visual (foto + confirmación) + tab Media en el frontend real | ✅ Done — ver detalle abajo. Construido primero por error en `/home/totti/Claesen-Sport` (repo local viejo, sin remoto) — rehecho en `/home/totti/Claesen-Sport-updateing` (repo real, remoto `service.claesen-verlichting.git`) tras detectar el error a mitad de sesión |
| CLA-389 | Identificación visual contra el catálogo completo de marcas (Philips/Signify, Schréder, Thorn, Musco), no solo los 10 tipos internos | ✅ Done — ver detalle abajo. Guía de usuario: `docs/luminaire-vision-identification-user-guide.md` |
| CLA-390 | Identificación visual de tipo de frame al crear uno nuevo (Fase 1 de 3: match contra catálogo) | ✅ Done — ver detalle abajo. Fases 2 (multi-luminaria) y 3 (generación de imagen) fuera de alcance, sin ticket todavía |
| CLA-391 | Detección multi-luminaria + posicionamiento aproximado por foto (CLA-390 Fase 2 de 3) | ✅ Done — ver detalle abajo. Fase 3 (generación de imagen) sigue sin ticket, necesita prueba de concepto propia |
| CLA-405 | Ruta faltante `GET /fieldops/maintenance-work-orders/history` (404 en Reports) | ✅ Done — ver detalle abajo |
| CLA-406 | Auditar/normalizar el pipeline de deploy de `service.claesen-verlichting.be` + redeploy del commit actual | ✅ Done — ver detalle abajo |
| CLA-407 | Migrar autenticación de Gemini de API key a Service Account (Vertex AI) | ✅ Done — ver detalle abajo |
| CLA-408 | Fix UX de recurrencia en Schedule Maintenance: interval con default engañoso | ✅ Done — ver detalle abajo |
| CLA-409 | Generación de imagen de tipo de frame en estilo catálogo cuando no hay match (CLA-390 Fase 3 de 3) | ✅ Done — ver detalle abajo |
| CLA-439 | Programar sync de FoClient/Complex (FO-012/FO-013) encadenado tras el mirror nocturno | ✅ Done — ver detalle abajo |
| CLA-440 | Migrar generación de imagen de frame type (CLA-409) de Gemini a OpenAI `gpt-image-2` | ✅ Done — ver detalle abajo |
| CLA-444 | Fix imagen rota tras guardar (URL absoluta) + prompt agregaba herrajes inexistentes (QA real post-CLA-440) | ✅ Done — ver detalle abajo |
| CLA-445 | Fix residuo `gpt-5.4-mini` en el fallback PHP de `OpenAiImageGenerationService` (config/.env.example ya decían nano) | ✅ Done — ver detalle abajo |
| CLA-448 | Repo hygiene: destrackear 5 archivos ya trackeados bajo `tmp/` desde antes de la regla `/tmp` de `.gitignore` | 🚧 In Progress — ver detalle abajo |
| CLA-496 | FieldOps: matriz de autorización create/update/delete por rol (auditoría de seguridad FieldOps, primer ticket de una batería de 16) | ✅ Done — comiteado en dos tandas (`b81e8a3` matriz original + `f437492` backfill de baseline tras un gap de producción encontrado el mismo día), ambas en `origin/main` y verificadas en producción real. Ver detalle abajo |
| CLA-497 | FieldOps: tenant-scope en 3 endpoints globales de mantenimiento (segundo ticket de la batería de 16) | ✅ Done (`ba149cc`) — comiteado, en `origin/main` y en producción, cerrado en Linear con waiver del smoke productivo. Ver detalle abajo |
| CLA-498 | FieldOps: fix BOLA en upload de media (`FieldOpsMediaController::store()`, tercer ticket de la batería de 16) | ✅ Done (`a6eec05`) — testeado (gate serial 200/654 en verde), verificado con QA real en dev, comiteado y cerrado en Linear (2026-09-15). Pendiente de push a `origin/main` y deploy junto con el resto de la rama `audit/prospects-module`. Ver detalle abajo |
| CLA-581 | FO-006: cutover ejecución de work orders Sport → Core (offline explícitamente fuera de alcance) | ✅ Done — `technician` gana acceso al panel Filament, acotado a `Modules/FieldOps/Filament/Pages/MyWorkOrders` (lista/iniciar/completar work orders asignadas vía `MaintenanceWorkOrderService::start()`/`::submit()` ya existentes). 7 resources de otros módulos (Performance, Employee, Safety×2, Mailing×2, Prospects) que no tenían `canAccess()`/Policy propios recibieron guard explícito contra `technician` — ver regla #5. 1230 tests en verde (FieldOps 582, Core 125, Performance+Employee+Safety+Mailing+Prospects 523), 0 regresiones. Offline/cámara nativa quedan fuera — ver fila FO-006 arriba |
| FO-006 | Slice C.6b — Cutover: frontend Sport → Core | ✅ Done (CLA-328, cerrado 2026-09-22) — **decisión final: Sport NO se deprecia, se reposiciona.** Exploración técnica encontró que el offline es una pared arquitectónica real (Livewire pausa toda interacción sin señal, Filament no trae infraestructura PWA) — un técnico pierde señal en campo de forma rutinaria, no como excepción, así que deprecar Sport del todo sería un retroceso real, no un detalle. Decisión: Core absorbe la superficie de escritorio (navegación de complejos/terrenos/estructuras/clientes, ya cubierta; flujo de ejecución con señal, CLA-581) y Sport se queda como la app dedicada de ejecución offline en campo (cámara nativa, identificación IA en el momento, ejecución sin señal) — su razón de ser explícita de ahora en más, no un accidente del sistema satélite viejo del que viene el nombre. Sin rebuild, sin migración urgente de lo que ya vive bien en cada lado. Si el cutover necesita mantenimiento *programado* a futuro, abrir ticket nuevo para `ScheduledMaintenanceService` |

**Orden de trabajo acordado:** FO-008 → FO-004 → FO-003 → FO-005 → FO-007 → FO-009 → FO-012 → FO-013 → **FO-006**.


Detalle completo de cada cierre técnico (CLA-266 en adelante, incluidos FO-009/FO-012/FO-013, CLA-278 y su serie de continuaciones, y la batería de auditoría de seguridad CLA-496→498) archivado en `docs/ai/fieldops-decisions-log.md` — no es lectura obligatoria. Consultar solo si una tarea toca puntualmente uno de esos tickets.

---

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
