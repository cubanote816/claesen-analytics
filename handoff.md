# Handoff — CAFCA Intelligence Hub

> Estado global vivo del proyecto. Se actualiza en cada cierre de ticket.
> **Regla de edición:** las entradas nuevas se añaden en la tabla "Cambios recientes", **al final** de la tabla — nunca se antepone texto libre arriba de este archivo. Esto preserva el prompt cache entre sesiones (ver `docs/ai/handoff-strategy.md`).
> Histórico completo previo a esta reorganización (CLA-536, 2026-09-15) → `docs/ai/handoff-archive.md` (no es lectura obligatoria).

---

## Estado actual

- **Sprint activo:** Programa Laravel 13 (CLA-514) — núcleo técnico cerrado (CLA-515→532 Done); pendiente de infraestructura real: **CLA-530** (aprovisionar staging Laravel separado de producción) y **CLA-531** (endurecer `deploy.sh`/`deploy.yml` + rollback real) bloquean **CLA-525** (In Progress) y **CLA-523** (Backlog). PR #9 (`release/laravel-13-rc1` → `main`) sigue **Draft**, sin merge, sin deploy.
- **CLA-532 = Done** (2026-09-09): eliminados los usos runtime de `env()` incompatibles con `config:cache`. CI certificada verde (`1355 passed / 0 failed / 0 errors / 2 skipped`). Detalle en `docs/ai/handoff-archive.md`.
- **CLA-536 (este ticket, In Progress):** reorganización de la documentación de IA (`CLAUDE.md`/`handoff.md`/`docs/ai/`/`.agent/skills`) para reducir el coste de tokens por sesión y restaurar el aprovechamiento de prompt cache. Ver plan y alcance en el propio ticket Linear.
- **Hallazgo central pendiente:** no existe un entorno de staging Laravel real — `deploy.yml` es un job único a `prod-priv-01`. Nada se despliega a producción hasta que CLA-530/531/525/523 cierren en ese orden.

---

## Módulos activos

| Módulo | Estado | Rama | Documento específico |
|--------|--------|------|---------------------|
| **Mailing** | ✅ Fase 0+1+2 completadas / Fase 3 en Backlog (bloqueada hasta datos reales) | `main` | `docs/Mailing/mailing-platform-master.md` |
| **Website** | ✅ Mergeado en `main` (incl. Work Details + Static Site) | `main` | `docs/website-sprint-handoff.md` |
| **Safety** | ✅ Sprint completado | `main` | `docs/safety-sprint-linear-tickets.md` |
| **Performance** | 🚧 ~85% | `main` | `docs/ai/handoff-archive.md` |
| **Intelligence / BI** | ✅ Sprint 1+2B completados | `main` | `docs/bi-sprint-plan.md` |
| **Prospects** | 🚧 CLA-535 (refactor de fuentes de federación) — 7 slices Done, docs cerrando F | `audit/prospects-module` | `docs/ai/context-map.md` |
| **Cafca** | ✅ ~90% | `main` | `docs/ai/handoff-archive.md` |
| **Core** | ✅ ~99% | `main` | `docs/ai/handoff-archive.md` |
| **FieldOps** | 🚧 batería de seguridad en curso (CLA-496/497 Done, CLA-498 pendiente de GO) | `main` | `docs/ai/handoff-archive.md` |
| **Laravel 13 (CLA-514)** | 🚧 núcleo Done, staging/deploy pendiente (CLA-530/531/525/523) | `release/laravel-13-rc1` | `docs/ai/archive/` |

---

## Bloqueantes actuales

- **CLA-530** — provisión de staging Laravel real, separado de producción. Bloquea CLA-525.
- **CLA-531** — endurecer `deploy.sh`/`deploy.yml` + rollback real probado. Bloquea CLA-525 y CLA-523.
- **MAI-026** — Webhook handler ESP externo: bloqueado por decisión de gerencia. No tocar.
- **Mailing Fase 3** (MAI-031 a MAI-036) — bloqueada hasta 4–6 semanas de datos reales en producción.
- **Backfill Website media** — `php artisan website:regenerate-media` pendiente de ejecutar en producción.

Ver `docs/ai/known-risks.md` para el detalle completo.

---

## Próximos pasos recomendados

1. Cerrar CLA-536 (esta reorganización de documentación) y confirmar con GO técnico.
2. CLA-530 — aprovisionar staging Laravel real (12 criterios, ver ticket).
3. CLA-531 — endurecer deploy + rollback ensayado en staging.
4. Con CLA-530/531 cerrados, reanudar CLA-525 (E2E por rol) y luego CLA-523.
5. Mailing Fase 3: esperar datos reales de campañas en producción.
6. Website backfill media pendiente en producción.

---

## Cambios recientes

| Fecha | Ticket | Acción |
|-------|--------|--------|
| 2026-08-30 | CLA-518/519/526/517/516/521/522/524/527/528 | Cadena completa de certificación Laravel 13 (núcleo, Spatie majors, Filament/Livewire, auth/CSRF, cache/queues/mail, PHPUnit 12, casing de migraciones, estabilización de suite). Detalle en `docs/ai/handoff-archive.md`. |
| 2026-08-30 | CLA-523 (parcial) | Fix `down()` roto de migración de luminaire positions (bloqueaba `migrate:rollback`). |
| 2026-08-31 | CLA-525 (cont.) | 1ª corrida real de CI sobre `release/laravel-13-rc1` — 103 fallos de harness eliminados, baseline diferencial certificado sin regresión de código. |
| 2026-09-01 | CLA-529 | Done — fix de zona horaria en A/B winner y follow-ups de Mailing (bug real de producción, sin relación con L13). |
| 2026-09-01 | FASE 0 | Auditoría de solo lectura del plan de despliegue Laravel 13 → producción. CLA-530/531 creados. |
| 2026-09-03 | CLA-532 | Implementación inicial — migración de `env()` a `config()` cache-safe. |
| 2026-09-09 | CLA-532 | **Done** — CI certificada verde (1355/0/0/2). PR #9 sigue Draft. |
| 2026-09-15 | CLA-536 | Auditoría del harness de IA + reorganización de `CLAUDE.md`/`handoff.md`/`docs/ai`/`.agent/skills` para reducir coste de tokens. |
| 2026-09-15 | CLA-539 | Auditoría de la config `gentle-ai` (memoria `project_gentle_ai_audit`): `rdd_mode` apagado global, effort de los 25 subagentes de `pi` diferenciado por fase, Engram desactivado (solo auto-memory nativo). En el repo: archivado el histórico ticket-por-ticket de FieldOps (CLA-266 en adelante, mismo patrón CLA-536) a `docs/ai/fieldops-decisions-log.md` — `CLAUDE.md` pasó de 245KB a 46.7KB (−81%). |
| 2026-09-15 | CLA-535 | Refactor completo de adquisición de datos de Prospects (SDD, rama `audit/prospects-module`, 7 slices A→B→D1→D2→C→E→F): arquitectura `FederationDataSource`/`NormalizedClub`/`ClubPersister` (ver `docs/ai/context-map.md`), adapters reales para RBFA/AFTT-PDF/Bruselas-CSV (nueva cobertura), fix de bugs de datos (idioma Hockey, provincias RBFA faltantes, región AFT), hardening de observabilidad (`guardedSync`, notificación de fallo de cadena maestra), y fix colateral de `region_id NOT NULL` en `LeadService`. Gaps diferidos (AFPadel/Verenigingsregister/Sport Vlaanderen) documentados en `docs/ai/known-risks.md`. Suite Prospects 118/118 (329 assertions) al cierre de CLA-535 — 122/450 tras CLA-543, reverificado de forma independiente el 2026-09-18 con DB aislada; suite completa del repo 1446/1446 sin fallos (stack Docker aislado `claesen_api_web_oficial-*`, puertos alternos, sin tocar el otro checkout del mismo repo). GO técnico dado y comiteado en `62a1261`; pendiente de push a `origin/main`. |
| 2026-09-22 | CLA-580 | Auditoría de diseño del login de Filament + identidad de marca: panel split-screen (`Modules/Core/resources/views/filament/auth/brand-panel.blade.php`, render hook `SIMPLE_LAYOUT_START` escopado a `filament.admin.auth.login`) y fix de i18n real (`microsoft-login-button.blade.php` hardcodeaba "Of"/"Aanmelden met Microsoft" en NL sin `__()`; ahora `core::auth.{divider_or,microsoft_login,brand_tagline}` con `nl`/`en`). Verificado con Selenium/WebDriver headless (screenshots reales desktop+mobile) y `PanelAccessTest`/`MicrosoftAuthRoleGateTest`/`Laravel13AuthCsrfCompatibilityTest` (18/18). Hallazgo colateral documentado en `CLAUDE.md` regla 11: `.bg-mesh-signature` de `app.css` no existe en vistas del panel Filament (`theme.css` no importa `app.css`). GO técnico dado; pendiente de push a `origin/main`. |
| 2026-09-22 | CLA-385 | Fix real (no solo doc): agregado `--color-claesen-orange` y `.bg-mesh-signature` (adaptada a cyan+naranja, sin índigo/rosa) a `resources/css/filament/admin/theme.css`. El bug era ~5x más grande de lo diagnosticado en agosto: 20 usos de `claesen-orange` + 4 de `.bg-mesh-signature` en `employee-project-timeline.blade.php` (dashboard completo "AI Performance"), no 2 aislados. Segunda causa raíz encontrada al verificar: los `@source` de `theme.css` no cubrían `resources/views/livewire/**`, así que ninguna variante de opacidad compilaba aunque el token ya existiera — agregado ese `@source`. Verificado con build real + grep de selectores exactos en el CSS compilado (no solo confiar en el build) + `npx @google/design.md lint DESIGN.md` (0 errores). Implementado en un git worktree aislado (`git worktree add` desde `main` local) porque el working directory compartido tenía otra sesión de Claude Code activa en CLA-581 con ~15 archivos sin comitear — cero contacto con esos cambios. GO técnico dado; pendiente de push a `origin/main` (junto con CLA-580). |
| 2026-09-22 | CLA-446 | Sin código nuevo — el fallo ya estaba resuelto desde el 2026-08-30 por `144ee32` (CLA-528, "estabilizar la suite bajo Laravel 13"), nunca vinculado de vuelta a este ticket. Verificado: `Modules/Core/tests` 125/125 verde. Causa real confirmada: `Application::configurationIsCached()` no relee el filesystem, solo el binding `config_loaded_from_cache` fijado una vez en el bootstrap — nunca fue regresión de L13. Cerrado Done. |
| 2026-09-22 | CLA-450 | Alcance mínimo del ticket (favicon/PWA, sin unificar los 8 `master.blade.php` en un layout único — decisión de producto aparte, no tomada): nuevo partial `Modules/Core/resources/views/partials/head-icons.blade.php` con los mismos 5 `<link>` de CLA-449, incluido (`@include('core::partials.head-icons')`) antes de `</head>` en las 12 vistas standalone listadas en el ticket (no-access, setup-password, mailing preferences/unsubscribe, 8 `master.blade.php` de módulos). Verificado con `php artisan view:cache` sobre toda la app (0 errores — resuelve y compila el include en los 12 archivos a la vez; no se corrió un test HTTP real por ser cambio puramente aditivo de `<link>` estáticos). Implementado en worktree aislado, mismo motivo que CLA-385. |
| 2026-09-22 | Multiempresa Electro Bertels (CLA-451→478, 547→560) | Rama `electrobertels/trunk` reconciliada con `main` en `electrobertels/trunk-merge-main` (sin push). Avance: F0–F4 + P1–P6 hechos; **falta P7** (NOT NULL, flag en prod, alta de Bertels, decisión MFA D8), bloqueado por staging (CLA-530/531/525). Detalle: `docs/ai/multiorg-handoff-log.md`, `docs/ai/multiorg-decisions-log.md`, ADR `docs/ai/adr-multi-organization.md`. |

| 2026-09-28 | CLA-627 | El rechazo de login dejaba de ser agnóstico de app: `Modules/Knx/lang/{nl,en}/auth.php` decían «…geen toegang tot Kantoor» y `POST /auth/login` lo sirven las dos apps, así que un técnico de Veld leía sobre Kantoor. Texto a «Deze inloggegevens kloppen niet.» y «These credentials are incorrect.». Hallazgo de paso: `test_every_refusal_looks_exactly_the_same` afirmaba que cada negativa «looks exactly the same» pero solo comparaba la **forma**, no el mensaje — ahora compara el payload completo entre los 6 motivos de rechazo. Test nuevo que verifica que el mensaje no nombra ninguna de las dos apps (control negativo: revirtiendo el idioma, falla). Verificado: `Modules/Knx/tests` 183 passed / 0 failed / 1 skipped (baseline 182), Pint limpio, y sobre HTTP real en `:8002` el mensaje es idéntico para contraseña incorrecta y cuenta inexistente. Observación **no** arreglada: `SetLocaleFromHeader` no está aplicado a las rutas Knx (sí a las de FieldOps y Website), así que `Accept-Language: en` no cambia el idioma y el fichero `en` del módulo hoy no es alcanzable por HTTP. GO técnico del usuario pendiente. |

| 2026-09-29 | CLA-630 | Los assets publicados de Filament (`public/{css,js,fonts}/filament/**`, 37 ficheros) **dejan de trackearse** y el deploy endurece su publicación. El drift apareció al pasar el gate de RDD sobre CLA-627: `gentle_review` ofrece `review.start` y el START falla en preflight con `lens_context_budget_exceeded` (`next_action: stop`, sin linaje ni autoridad) porque son bundles minificados de una sola línea, y la salida que sugiere el provider (partirlo en candidatos más chicos) no aplica a un bundle; y como la proyección de review es el *working tree*, ese drift **tapa lo commiteado** — el fix de CLA-627 nunca entró al candidato (`applicability: "unrelated"`). Validado **antes** de decidir: borrando `public/js/filament/notifications/notifications.js` y corriendo `filament:upgrade`, el fichero volvió **byte-idéntico** (sha256 `662228686bdfd5cb`), así que el deploy los republica desde `vendor/filament/*/dist`. Cambios: `.gitignore` (3 rutas), `git rm --cached` de los 37 ficheros (siguen en disco), e `infrastructure/scripts/deploy.sh` paso 7 sin `|| true` ni `2>/dev/null` **más** un `test -f` de comprobación — imprescindible porque `UpgradeCommand::handle()` llama a `filament:assets` pero **ignora su código de salida** y devuelve `SUCCESS` igual: puede salir 0 sin haber publicado nada. Regla permanente nueva en `CLAUDE.md` (12). Efecto colateral asumido y consciente: un fallo de publicación ahora aborta el deploy antes del paso 9, así que la app queda en mantenimiento en vez de servir un backoffice roto. GO técnico del usuario pendiente. |

| 2026-10-06 | KNX-3 | **Planos y cuadros para el backoffice de oficina (Kantoor).** Cinco unidades de trabajo, commits `c90e6a6` (`POST /documents`: el servidor deriva `size_bytes`/`mime_type`/`pages`, guarda en el disco `local`, `supersedes` en una transacción, idempotencia por `clientId`, 50 MB y `413`, y `DocumentResource` pasa a exponer `mimeType`/`pages` con `size_bytes` no nullable), `f6b3144` (`code` de `POST /projects` con la restricción de la ruta de lectura → `422`), `5af2b3c` (`GET /employees?role=office\|field` → `{id, name, shortName}`; cierra el alta de proyecto sin responsable), `694969e` (marcadores por **documento/revisión** con `GET`/`PUT`, `carryOverFrom`, `nx`/`ny` `decimal(6,5)`, geometría opaca del cliente) y `b8232dd` (`GET /projects/{code}/boards` + `knx:import-worklist`: módulos con `slot`, enlaces `(canal, habitación, objeto, GA)`). Identidad de cuadro = **slug del worklist**, la misma cadena que el `boardId` del marcador; `revision` se deriva del documento, no se duplica. Verificado: `Modules/Knx/tests` **229 passed / 1 skipped / 0 failed** (1290 aserciones), mis 34 ficheros Pint-limpios, y **HTTP real en `:8002`**: login de oficina, `POST /documents` de un PDF real de 3 páginas y 2 MB → `201` con `pages:3`/`mimeType`/`url` firmada, reintento `200`, `supersedes` deja `85 isCurrent=false` y `86 isCurrent=true`, `PUT`/`GET` de 5 marcadores, `GET /projects/000026/boards` → **5 cuadros, 13 módulos, 52 canales, 160 enlaces** (los números medidos del worklist real), `GET /employees` → Lien Smet + Pieter Aerts, y `code = "C 1699"` → `422`. Doc del módulo actualizada; `package-lock.json` quedó sin tocar. GO técnico del usuario pendiente. |

| 2026-10-06 | KNX-3 (revisión) | **Revisión nativa por unidad de trabajo.** U2 (`f6b3144`) y U3 (`5af2b3c`) revisados, **approved** y quemados. U1 (`c90e6a6`) pasó reviewer y **refuter**, que encontró **3 defectos CRITICAL reales** (todos introducidos en `c90e6a6`): `putFileAs` devolvía `false` y la transacción podía commitear un documento con `path` vacío; un `clientId` vacío esquivaba el replay y chocaba con el índice único (500); y el índice único de `client_id` era global mientras la relectura es por organización. Corregidos y verificados en `da7ed94` con 3 tests de regresión; `Modules/Knx/tests` **232 passed / 1 skipped / 0 failed** (1299 aserciones). **Bloqueante de la revisión:** el proveedor emitió la ruta `correction_plan_required`, pero `gentle_review_capture` rechazó el binding vigente como *stale* en los cuatro estados probados y `gentle_review advance` no soporta `capture-correction-plan`; no se usó RESET/RECOVER (destructivos, requieren decisión). U4/U5 quedaron sin veredicto porque el flujo del proveedor devolvió después un binding de consentimiento expirado y `lineage_created: false`. Detalle en `odd/tasks/knx-planos-cuadros.md`. |

> Histórico completo de tickets anteriores (CLA-105 a CLA-517, sesiones de FieldOps/Mailing/Website/BI/Safety sin ticket formal, etc.) → `docs/ai/handoff-archive.md`.
