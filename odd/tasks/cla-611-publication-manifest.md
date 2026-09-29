# CLA-611 (parte) — Manifiesto de publicación por página e idioma

- **Rama:** `electrobertels/i18n-publication` (apilada sobre `electrobertels/site-content-api`). Worktree: `/home/totti/claesen/electrobertels-i18n-publication`
- **Linear:** sirve a CLA-611 (gap G2 del informe de backend) y al gate de la web (`odd/tasks/translation-publication-gate.md` del repo del sitio)
- **Objetivo:** que el build de la web pueda preguntar **qué `(página, idioma)` está publicado** y obtener una respuesta; hoy esa decisión vive en una constante TS ✗.
- **Alcance autorizado:** este slice. **No** toca lo ya construido de CLA-611 (tabla `ai_translation_states`, motor, glosa, pantalla de revisión) — lo da por bueno ✗.
- **TDD:** no habilitado por configuración; tests de feature + verificación HTTP real.
- **Doc de diseño:** `translation-publication-gate.md` en el repo del sitio.
- **Estado:** ✅ **cerrado y verificado** (2026-09-28).

## Por qué hace falta, y por qué no se reutiliza lo que ya hay

CLA-611 ya modela el estado por `(modelo, atributo, locale)` con `missing → machine → reviewed → published` ✔. **Pero eso es para contenido dinámico** (registros con fila propia): proyectos, media, ajustes, legales. El copy de las **8 páginas vive en Git** (decisión D-F) ✗ → **no tiene fila ni modelo detrás** ✗ → el estado de esas páginas no tiene dónde vivir hoy.

Y `PublicationState` (tabla `website_publication_states`) **no sirve para esto**: ese es el estado del **despliegue/rebuild** del sitio (`idle/dispatched/pending/accepted` + webhook) ✗. Son dos cosas distintas con nombres parecidos — de ahí que este modelo se llame `PagePublication` y no `PublicationState` ✗.

## Tareas

- [x] **P1** Migración `website_page_publications`: `(site_id, page, locale, status, reviewed_by_user_id, reviewed_at)` con `unique(site_id, page, locale)` — `2cd5329`
- [x] **P2** Modelo `PagePublication` con los estados que se implementan: `machine` (la IA/borrador renderiza, no se indexa) · `reviewed` (leído, aún no publicable) · `published` (indexable). Ausencia de fila = `missing` ✔
- [x] **P3** Endpoint público **`GET /v1/website/publication-manifest`** → `{version, published: {página: [locales]}}` — exactamente la forma del snapshot que la web ya consume ✔
- [x] **P4** Seeder del estado real de hoy: las 7 páginas de contenido con `nl: published` (las 3 legales **fuera** ✗: su gate es su propio borrador)
- [x] **P5** Tests: forma del manifiesto · no aparece lo no publicado · el sitio no ve páginas de otro sitio · unicidad `(site, page, locale)`
- [x] **P6** Verificación real + commit + push — `c72daed` (slice) + el commit de test/contrato/snapshots

## Decisiones tomadas en este slice

1. **Granularidad por página, no por clave.** El plan del sitio ya lo decidió (D-C: «revisión por página, permite avanzar por secciones»). Guardar 274 claves sería pedir al cliente que apruebe 274 cosas ✗.
2. **Solo lo `published` viaja en el manifiesto.** `machine` y `reviewed` existen como estado pero no publican nada ✔ — la web solo pregunta «¿puedo indexar?».
3. **No se inventa el estado inicial en una migración.** Los datos de hoy (NL publicado) van en un seeder, porque una migración no debe decidir contenido editorial ✗.
4. **Trampa declarada para el sync:** un manifiesto **vacío** no debe escribirse jamás encima del snapshot ✗ (convertiría toda la web en borrador). El sync debe fallar en vez de escribir `{}`.

## Verificado (evidencia, no afirmación)

| Chequeo | Resultado |
| --- | --- |
| Tests del slice (`--filter=PagePublicationManifestTest`) | **6/6**, 10 aserciones |
| Módulo `Modules/Website` completo | **242 passed / 751 aserciones** |
| Baseline (`tests/Feature/ClaesenBaseline`) | **78 passed / 380 aserciones** |
| Snapshot de rutas | `421 → 422` ✔ **+1 ruta**, puramente aditivo, `UPDATE_BASELINE_SNAPSHOTS=1` y diff revisado |
| Compuerta OpenAPI (`docs/api/website-v1-openapi.yaml`) | el endpoint documentado; el test exige las dos direcciones ✔ |
| Migración en BD limpia | 214 migraciones, la propia incluida, sin duplicados |

**No ejecutado:** la suite completa del repo (31 min) ✗. Proporcional: este slice añade una ruta, un modelo y un controlador en `Modules/Website`, y el baseline vigila las rutas de todo el repo ✗.

## Trampas de este repo (para el próximo que entre)

1. ⚠️ **La ruta NO lleva `/api`** ✗✗: las del módulo Website viven en **`/v1/website/...`** ✔. Escribir `/api/v1/website/...` da un 404 de «ruta no encontrada» ✗, y la página 404 propia de la app se titula *"Short Circuit!"* ✗ → parece un rechazo del middleware ✗ y no lo es ✗ (perdí una hora larga ahí ✗). Para el módulo `Knx` sí es `/api/v1/knx/...` ✗ — **depende del módulo** ✗.
2. ⚠️ **El worktree necesita su `.env`** ✗: sin él, dotenv emite un warning en **cada** test ✗ (`file_get_contents(/var/www/html/.env)`) y `php artisan test` sigue corriendo igual ✗ → parece ruido de PHPUnit ✗ y es un `.env` ausente ✗.
3. ⚠️ **`DB_CONNECTION=sqlite` en el `.env` de un worktree hermano** ✗ (`electrobertels-cla599-600` ✗): hace que `migrate:fresh` opere sobre un **fichero sqlite** llamado como la BD ✗ → deja basura ✗ y produce un "duplicate column" que parece un defecto de `Modules/Core` ✗ y no lo es ✗. En este worktree quedó `DB_CONNECTION=mysql` ✔. Las corridas de test deben forzar `DB_CONNECTION=mysql` ✔.
4. ⚠️ **`sites.organization_id` no tiene default** ✗: un `Site::query()->create([...])` a mano revienta ✗; se crea con el fixture ✔.
5. ⚠️ **`ResolveRequestSite` solo resuelve sitios `active`** ✗: un sitio no-activo cae al fallback de Claesen ✔ (y en una BD de test sin Claesen → **404** ✗). Los tests que resuelven por `?site=` deben crear el sitio **activo** ✔.
6. ⚠️ **Toda ruta nueva en `v1/website` hay que documentarla** ✗: `OpenApiContractTest` falla en las dos direcciones ✗ (documentado ↔ registrado ✔).
7. ⚠️ Al añadir una ruta hay que **regenerar los dos snapshots** del baseline ✗ (`routes.txt` y `routes-count.txt`) ✔ con `UPDATE_BASELINE_SNAPSHOTS=1` y **revisar el diff** ✔ (debe ser aditivo ✗).

## Fuera de alcance

- El sync que escribe `src/content/publication.json` en el repo del sitio (su sesión, Fase 1/2 del plan).
- La UI de aprobación con roles (el usuario retiró esa decisión ✗: sigue abierta).
- El estado por `(modelo, atributo, locale)` del contenido dinámico: ya existe (CLA-611) ✔.
