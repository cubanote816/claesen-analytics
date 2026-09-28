# CLA-611 (parte) — Manifiesto de publicación por página e idioma

- **Rama:** `electrobertels/i18n-publication` (apilada sobre `electrobertels/site-content-api`). Worktree: `/home/totti/claesen/electrobertels-i18n-publication`
- **Linear:** sirve a CLA-611 (gap G2 del informe de backend) y al gate de la web (`bertels/docs`: `odd/tasks/translation-publication-gate.md` del repo del sitio)
- **Objetivo:** que el build de la web pueda preguntar **qué `(página, idioma)` está publicado** y obtener una respuesta; hoy esa decisión vive en una constante TS ✗.
- **Alcance autorizado:** este slice. **No** toca lo ya construido de CLA-611 (tabla `ai_translation_states`, motor, glosa, pantalla de revisión) — lo da por bueno ✗.
- **TDD:** no habilitado por configuración; tests de feature + verificación HTTP real.
- **Doc de diseño:** `translation-publication-gate.md` en el repo del sitio.

## Por qué hace falta, y por qué no se reutiliza lo que ya hay

CLA-611 ya modela el estado por `(modelo, atributo, locale)` con `missing → machine → reviewed → published` ✔. **Pero eso es para contenido dinámico** (registros con fila propia): proyectos, media, ajustes, legales. El copy de las **8 páginas vive en Git** (decisión D-F) ✗ → **no tiene fila ni modelo detrás** ✗ → el estado de esas páginas no tiene dónde vivir hoy.

Y `PublicationState` (tabla `website_publication_states`) **no sirve para esto**: ese es el estado del **despliegue/rebuild** del sitio (`idle/dispatched/pending/accepted` + webhook) ✗. Son dos cosas distintas con nombres parecidos — de ahí que este modelo se llame `PagePublication` y no `PublicationState` ✗.

## Tareas

- [ ] **P1** Migración `website_page_publications`: `(site_id, page, locale, status, reviewed_by_user_id, reviewed_at)` con `unique(site_id, page, locale)`
- [ ] **P2** Modelo `PagePublication` con los estados que se implementan: `machine` (la IA/borrador renderiza, no se indexa) · `reviewed` (leído, aún no publicable) · `published` (indexable). Ausencia de fila = `missing` ✔
- [ ] **P3** Endpoint público `GET /api/v1/website/publication-manifest` → `{version, published: {página: [locales]}}` — exactamente la forma del snapshot que la web ya consume ✔
- [ ] **P4** Seeder del estado real de hoy: las 7 páginas de contenido con `nl: published` (las 3 legales **fuera** ✗: su gate es su propio borrador)
- [ ] **P5** Tests: forma del manifiesto · no aparece lo no publicado · el sitio no ve páginas de otro sitio · unicidad `(site, page, locale)`
- [ ] **P6** Verificación HTTP real + commit + push

## Decisiones tomadas en este slice

1. **Granularidad por página, no por clave.** El plan del sitio ya lo decidió (D-C: «revisión por página, permite avanzar por secciones»). Guardar 274 claves sería pedir al cliente que apruebe 274 cosas ✗.
2. **Solo lo `published` viaja en el manifiesto.** `machine` y `reviewed` existen como estado pero no publican nada ✔ — la web solo pregunta «¿puedo indexar?».
3. **No se inventa el estado inicial en una migración.** Los datos de hoy (NL publicado) van en un seeder, porque una migración no debe decidir contenido editorial ✗.
4. **Trampa declarada para el sync:** un manifiesto **vacío** no debe escribirse jamás encima del snapshot ✗ (convertiría toda la web en borrador). El sync debe fallar en vez de escribir `{}`.

## Fuera de alcance

- El sync que escribe `src/content/publication.json` en el repo del sitio (su sesión, Fase 1/2 del plan).
- La UI de aprobación con roles (el usuario retiró esa decisión ✗: sigue abierta).
- El estado por `(modelo, atributo, locale)` del contenido dinámico: ya existe (CLA-611) ✔.
