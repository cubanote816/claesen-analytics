# Seeders del contenido del sitio (para que el API tenga datos)

- **Rama:** `electrobertels/trunk`. Worktree: `/home/totti/claesen/electrobertels`
- **Pedido por el usuario (2026-10-03):** crear varios seeders que pueblen el website, **copiando del sitio actual** (`/home/totti/electrobertel_official`, servido en `127.0.0.1:8080`), y después enlazar el sitio de Astro para que **consuma la data de nuestro API**.
- **Estado:** en curso.

## Por qué esto es la pieza que falta (y no una idea mía)

El repo del sitio **ya diseñó** este trabajo: `odd/tasks/dynamic-content-sync.md` (269 líneas) fija la arquitectura —*un CLI, una superficie a la vez*, `scripts/dynamic/sync-content.mjs --surface=<settings|media|legal|projects>`, **copiando el molde de `sync-publication.mjs`**— y tiene una sección explícita: *"Why no live happy path exists today"*, porque el backend contesta `{"data":[]}`. `--surface=settings` **ya está implementado** y su contrato está **pinchado contra `trunk` en el commit `74a65f4`**. Falta el dato en el backend: **esto**.

## Lo que se puede sembrar, y con qué se marca

Medido antes de escribir, porque el sitio tiene gates propios:

| Superficie | Qué hay hoy en el sitio | Cómo se siembra |
| --- | --- | --- |
| **settings** | **Datos reales del cliente**, verificados contra el handoff aprobado (`src/business/facts.snapshot.json`) | Valores reales. `vat_number` **no existe** → se **omite** (el contrato dice que las claves ausentes se omiten, nunca se emiten como null) |
| **projects** | 6/6 **fixtures ficticios** — el propio YAML dice *"not approved public content"* | Decisión del usuario: **sembrar marcados como DEMO**, para poder probar el circuito |
| **media** | 24 imágenes de `src/assets/mockup/` — material del prototipo de diseño, con gate propio (`GATE_REAL_MEDIA`) | Igual: **DEMO**, y además el modelo exige `usage_rights_confirmed_at` para el camino feliz |
| **legal** | Los 3 son **borradores sin cuerpo** (`status: draft`, `GATE_LEGAL_COPY`: *"real privacy policy copy has not been supplied"*) | Título real, cuerpo **con el aviso de que está en preparación**: no se inventa copy legal |

**Nada de esto es contenido real del cliente** salvo los datos de empresa de `settings`. El copy de las 7 páginas **sí es real**, pero vive en Git por decisión D-F y **no** pasa por la API.

## Tareas

- [ ] **P1** Arreglar el defecto de `strict_locale_site_keys`: dice `electrobertels` donde todo lo demás dice `electro-bertels` (dos sitios en `Modules/Website/config/config.php`), así que `PublicLocalePolicy::isStrict()` es false para este sitio y los legales caen a neerlandés en vez de devolver null para un idioma no aprobado. Encontrado por la otra sesión al pinchar el contrato; está en trunk porque el merge trajo su línea
- [ ] **P2** `SiteSettingSeeder`: las claves reales del whitelist con los valores aprobados. Sin `vat_number`
- [ ] **P3** `ProjectSeeder` (DEMO): los 6 casos con sus imágenes, con la marca de demo **visible**
- [ ] **P4** `MediaSlotSeeder`: los slots de `MediaSlot::SUGGESTED_SLOTS` apuntando a media de proyecto (la validación lo exige)
- [ ] **P5** `LegalDocumentSeeder`: los 3 `doc_id`, título real y cuerpo de "en preparación"
- [ ] **P6** Verificar cada uno **por HTTP** contra el backend servido, no solo con tests
- [ ] **P7** Enlazar el sitio de Astro para que consuma el API (la superficie `settings` ya está escrita; las demás son el trabajo que el propio documento del sitio lista)

## Regla de trabajo de esta feature

Una superficie por vez: se siembra, se **mira el endpoint**, y recién ahí la siguiente. Nada se da por bueno porque el test pase.
