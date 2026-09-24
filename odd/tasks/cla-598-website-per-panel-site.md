# CLA-598 — Website por panel (Bertels gestiona su propio sitio)

- **Rama:** `electrobertels/website-per-panel-site` (sobre `electrobertels/trunk-merge-main`, PR #26). Worktree: `/home/totti/claesen/electrobertels-merge`.
- **Linear:** CLA-598 (In Progress). Engram mirror: `odd/cla-598-website-per-panel-site/tasks`.
- **Objetivo:** el panel `bertels` gestiona proyectos, leads, anuncios y ajustes de su propio sitio; el panel `admin` nunca muestra filas de Bertels.
- **Alcance autorizado:** T1–T7 de abajo. Sin push, sin producción, sin tocar `main`.
- **TDD:** no habilitado por configuración (no hay fuente de proyecto/sesión que lo active); checks funcionales por tarea. Runner: `./vendor/bin/phpunit` con `DB_HOST=127.0.0.1 DB_PORT=3308 DB_DATABASE=testing_merge_main DB_USERNAME=sail DB_PASSWORD=<de main .env>`.
- **Heurística de tamaño:** ~400 líneas por tarea (solo planificación).

## Tareas
- [x] T1 Mapa panel→sitio (`config/organizations.php`) + `Site::forPanel()`
- [x] T2 Middleware de panel que fija el sitio en `OrganizationContext` (+403 cross-org)
- [x] T3 Trait `ScopedToPanelSite` en 3 recursos + `SiteSettingsPage`
- [x] T4 Registrar el clúster en `BertelsPanelProvider`; adaptar `PublicationStatusWidget`
- [x] T5 Leads: asignación por organización; correo interno por sitio
- [x] T6 Verificar medios del admin de Website (sin acceso cross-site)
- [x] T7 Fixture de Bertels solo tests/QA

## Criterios de aceptación
Aislamiento en ambos sentidos (listado + URL directa); creación fija `site_id` del panel; sin sitio no rompe (oculta); suite completa verde; snapshots regenerados solo por rutas `/bertels/...`; verificación visual real.

## Progreso / evidencia
- T1: `config('organizations.panel_sites')`, `Site::forPanel()`/`forPanelOrFail()` (sin panel explícito usa el actual o el default de Filament; nunca cae a Claesen si el sitio mapeado no existe). `PanelSiteResolutionTest` 5/5.
- T2: `OrganizationContext::site()` decide por panel dentro de Filament; `EnsureUserBelongsToPanelSite` (403 cross-org, tras el flag) en middleware y `persistentMiddleware` (Livewire) de ambos paneles. `PanelSiteContextTest` 9/9.
- T3: trait `App\Filament\Concerns\ScopedToPanelSite` en Project/Announcement/ConsultationRequest resources (query filtrada, `canAccess` false sin sitio); 6 usos de `Site::claesenId()` reemplazados; export CSV de leads y unicidad de slug ahora filtran por sitio (huecos reales encontrados: ambos dependían del scope inerte).
- T4: `discoverClusters` en `BertelsPanelProvider`; `PublicationStatusWidget` usa el sitio y health URL del panel (la URL global de config solo para Claesen). Test `WebsitePanelSiteScopingTest` 5/5 (listado, URL directa, creación, sin sitio).
- T5: `assignableUsersQuery` usa el sitio del panel también al crear (antes solo con registro existente).
- T6: sin ruta de medios de Website en el admin (conversiones públicas por diseño, originales privados sin ruta). Hueco menor cerrado: el reordenado de galería buscaba media por uuid globalmente; ahora solo entre los medios del propio registro.
- T7: fixture Bertels (org+sitio) solo en `core:qa-reset-environment` (guardado local/testing); `QaBertelsFixtureTest` 2/2. Ninguna migración crea la fila de Bertels.
- Snapshots `ClaesenBaseline` regenerados: diff verificado = middleware nuevo en rutas de panel + 12 rutas `/bertels/website/*` (399→411); test del panel Bertels actualizado (antes "vacío").
- Suite completa: 1899 tests; único fallo (test del panel Bertels vacío) corregido y re-ejecutado en verde. **Verificado visualmente en navegador real** (Playwright + Chromium, servidor local sobre BD aislada, login con MFA TOTP real, 2026-09-24): panel admin lista solo el proyecto/lead de Claesen; panel bertels solo los de Bertels, marca naranja, badge de organización, botón «Wissel naar Claesen», ajustes del sitio vacíos para el sitio nuevo. Capturas descartadas (scratchpad).

## Siguiente paso
Revisión del diff; push de las ramas `website-per-panel-site` → `org-in-users-ui` → `org-indicator` (apiladas) y PR (requiere autorización explícita). Pendiente fuera de alcance: selector de organización en usuarios, nombre de organización en cabecera, grupo Azure de Bertels, P7.


## Continuación (2026-09-24, autorizada en secuencia, sin push)
- CLA-599 (`6ffe614`, `fd3d820`): columna/filtro de organización y campo solo lectura en Usuarios; verificado en navegador (columna movida junto al nombre tras verla cortada).
- CLA-600 (`6e9bf1d`): badge de organización en la cabecera de ambos paneles + brandName Bertels.
- Suite completa tras CLA-600: 1904 tests, 0 fallos. Ramas apiladas: website-per-panel-site -> org-in-users-ui -> org-indicator.
- Decisión de producto pendiente: crear usuarios de Bertels desde el formulario (hoy exige Employee del ERP y dominio corporativo).

## CLA-601/602 (2026-09-24, en el repo principal y esta rama respectivamente)
- CLA-601 (login dual-brand): implementado en `/home/totti/claesen_api_web_oficial` (main), commit `2d98b8c`. No es parte de esta rama/worktree.
- CLA-602 (KNX punto de entrada, Fase 1): `electrobertels/knx-entry-point` (sobre `org-indicator`), commit `3a68244`. Ambos verificados visualmente y con suite completa en verde.
