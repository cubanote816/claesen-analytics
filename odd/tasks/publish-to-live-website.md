# Publicar desde el panel a la web en vivo

- **Rama:** `electrobertels/trunk`. Backend: `/home/totti/claesen/electrobertels`. Sitio: `/home/totti/electrobertel_official`
- **Origen:** pregunta del usuario (2026-10-06): «¿sería buena práctica tener un botón que explícitamente le diga al cliente empujar los cambios a la web en vivo? Una cosa es estar editando y otra tenerlo ya listo.»
- **Lo que la disparó:** el usuario editó ajustes en el panel (`:8004`) y **no se veían en el sitio local** (`:8080`).
- **Estado: ANÁLISIS, sin implementación.** Este documento no autoriza trabajo; la decisión A/B/C de abajo sigue abierta.

## Respuesta corta

**La intuición es correcta y el repo ya decidió lo mismo** — tiene acción de publicar, estado por sitio, webhook por sitio, debounce anti-carrera y aprobación por página. **Pero el botón no mueve datos:** el bucle de publicación reconstruye el sitio **desde el snapshot commiteado en Git**, nunca desde el API. Lo que falta no es el botón; es el **paso de datos**.

## Lo que ya existe (verificado, con archivo)

| Pieza | Dónde | Qué hace |
| --- | --- | --- |
| Botón «Publicar ahora» | `app/Filament/Clusters/Website/Resources/ProjectResource/Pages/ListProjects.php:22` | `Action::make('publish_now')` con icono, **modal de confirmación** y notificación. Existe **sólo en la lista de Proyectos** |
| Widget de estado | `ListProjects.php:41` | `PublicationStatusWidget` en el header de esa página |
| Estado por sitio | `website_publication_states` + `Modules/Website/Models/PublicationState.php` | `pending_since`, `last_accepted_at`, `last_error`, `status`; `markPending()`/`markAccepted()`/`markError()` |
| Webhook por sitio | `sites.static_site_webhook_url` / `_secret` / `_debounce_seconds` | Cada empresa tiene su endpoint y su debounce |
| Disparo anti-carrera | `Modules/Website/Jobs/TriggerStaticSiteRebuildJob.php` | Cada `requestRebuild()` genera un UUID; el job viejo aborta si lo superaron. `->afterCommit()` para que un guardado revertido no publique |
| Auto-disparo | `Modules/Website/Providers/WebsiteServiceProvider.php:42-44` | Observers de **`Project`** y **`Media`** (y `ConsultationRequest`) piden rebuild con `reason: content_changed` |
| Aprobación por página | `Modules/Website/Models/PagePublication.php:93`, `PagePublicationController.php:33`, ruta `GET /v1/website/publication-manifest` | El «Goedkeuren»: qué páginas aprobó el cliente |
| Pantalla de revisión | `app/Filament/Clusters/Website/Pages/PagePublicationReviewPage.php` | Donde el cliente aprueba |

O sea: la distinción **«estoy editando / está listo»** ya está modelada. No hay que inventarla.

## Lo que rompe la ilusión

**El bucle de publicación no lleva datos del backend al build.** Cadena completa, seguida archivo por archivo:

```
requestRebuild()  →  webhook al sitio  →  receiver.mjs  →  release.mjs  →  spawn('astro', ['build'])
```

- `scripts/publish/release.mjs:146` — el release corre **sólo `astro build`**.
- `.github/workflows/ci.yml:42` — el CI del sitio corre **sólo `pnpm build`**, que es `astro build`.
- `scripts/dynamic/sync-content.mjs` **no aparece en ningún pipeline**: sólo en su propio archivo, en los lectores del sitio (`src/business/facts.ts`, `src/business/media.ts`), en el doc de la tarea y en tests.

**Consecuencia:** «Publicar ahora» reconstruye el sitio **desde el snapshot ya commiteado**. Publica *lo que ya estaba en Git*. Ningún botón puede cambiar eso mientras el dato no entre al build.

### Los huecos concretos, y cada uno explica un síntoma

| # | Hueco | Evidencia | Síntoma que produce |
| --- | --- | --- | --- |
| 1 | **El paso de datos es manual** (un dev corre el CLI y commitea) | `sync-content.mjs` fuera de todo pipeline | Los cambios del panel no llegan a la web hasta que alguien los sincroniza a mano |
| 2 | **`SiteSetting` no tiene observer** | Observers registrados: `ConsultationRequest`, `Project`, `Media`, `User`, dos de Safety. **Ninguno de `SiteSetting`** | Editar **ajustes** no pide ni siquiera un rebuild. Es exactamente lo que se editó el 2026-10-06 |
| 3 | **Botón y estado sólo en Proyectos** | `ListProjects.php:22,41` | Editar ajustes o legales no muestra «hay cambios pendientes» en ningún lado |
| 4 | **Sin historial de publicación** | `website_publication_states` guarda estado **actual**: `status`, `dispatch_key`, `dispatched_at`, `pending_since`, `last_accepted_at`, `last_error` | No se puede responder «¿qué cambió desde la última publicación?» ni «¿quién publicó y cuándo?» |
| 5 | **En local el botón es un no-op** | `config('static_site.enabled')` = **false** y `webhook_url` = **NULL** en dev | Probar el botón en local y concluir que está roto |

### Medición del desfase (2026-10-06)

```
$ PUBLICATION_API_URL=http://localhost:8004 node scripts/dynamic/sync-content.mjs --surface=settings --check
settings: 4 locale(s), opening hours for 4 day row(s)
✖ src/business/facts.snapshot.json is out of date — the snapshot is stale and will be updated.
  run the sync without --check and commit the result.
```

Y el diff entre el snapshot commiteado y lo que el API sirve hoy: `street` `"Benoit Jansenstraat 4"` → `"…444"` y `opening_hours` `tuesdayToFriday` `09:00` → `11:00`. Valores de prueba del usuario, guardados correctamente por los dos campos nuevos — el validador del sitio los aceptó.

## Las dos decisiones de producto que hay detrás

### 1. ¿Publicación continua o deliberada?

Hoy están **las dos encendidas a la vez**: los observers piden rebuild solo (debounce 20 s) *y* existe el botón forzado. Con el automático encendido, un cambio a medias del cliente **se publica 20 segundos después**. La frase «guardo mis cambios y publico cuando esté listo» **no es expresable** en el sistema actual.

Hay que elegir. Si se quiere publicación deliberada, el camino automático debe **acotarse o apagarse** en las superficies que edita el cliente.

### 2. ¿Qué significa «listo» sin vista previa?

La aprobación por página existe, pero aprobar sin ver el resultado renderizado es un acto de fe. La mitad difícil de este pedido es **preview/staging**, no el botón. Un release candidato con `astro preview` es la forma que encaja con lo que ya hay.

## Orden de trabajo propuesto

Ninguna de estas tareas está autorizada todavía.

- [ ] **P0 · Decidir A/B/C** (ver abajo). Todo lo demás depende de esto.
- [ ] **P1 · Llevar el dato al build.** Que el pipeline de release corra el sync antes de `astro build` (`sync-content.mjs --optional` con `PUBLICATION_API_URL` puesto), o que el backend escriba/empuje el snapshot. **Sin esto, «publicar» es reconstruir contenido viejo.** Es el trabajo real y yo lo pondría primero.
- [ ] **P2 · Cobertura del disparo.** Decidir explícitamente qué superficies avisan (falta `SiteSetting`) y cuáles quedan sólo con acción manual. Consecuencia de la decisión 1.
- [ ] **P3 · Estado y acción donde se edita.** El mismo botón + widget en cada pantalla de edición, o un indicador global de «cambios pendientes».
- [ ] **P4 · Historial.** Quién publicó, cuándo y qué cambió. Requiere decidir si `website_publication_states` deja de ser estado-actual y gana una tabla de eventos.
- [ ] **P5 · Vista previa.** Release candidato + preview antes de aprobar. La parte grande.

## Opciones

| | Qué implica | Costo |
| --- | --- | --- |
| **A · Seguir publicando desde el desarrollo** | Lo de hoy. Cero botón para el cliente. Hay que **declararlo**: «la web la publica el equipo» | Nulo |
| **B · Botón real** | P1 + P2 + P3. El botón pasa a prometer lo que cumple | Medio |
| **C · Flujo completo de cliente** | B + P4 + P5 | Alto |

**Recomendación:** **A ahora, B cuando el cliente edite de verdad.** Un botón sin el paso de datos promete algo que no cumple, y eso es peor que no tener botón.

## Fuera de alcance / no verificado

- **`--surface=projects` no existe**: el CLI del sitio dice que es «a later slice». Sincronizar proyectos desde el API **no está implementado**.
- **No probé el bucle de publicación de punta a punta**: en local `static_site.enabled=false`, así que el webhook nunca se llamó. Todo lo anterior es lectura de código y configuración, más la medición del desfase contra el API local.
- **No toqué `.env` de ningún repo.** `PUBLICATION_API_URL` no está en `.env.local` del sitio ni figura en su `.env.example`, aunque el mensaje de error del CLI manda a «add it to .env.local (see .env.example)»: inconsistencia menor de documentación, medida.
- La línea del sitio (otra sesión) es dueña de `media.snapshot.json` y de las superficies `legal`/`projects` del sync.

## Hechos reutilizables que dejó esta investigación

1. La web **lee un snapshot commiteado**, no el API en vivo. El panel escribe en la base → el API lo sirve al instante → el sitio no lo ve hasta que alguien re-sincroniza. Son tres escalones y el del medio no se mueve solo.
2. `NotifyAstroFrontendJob` está **deprecado** en favor de `StaticSitePublicationService::requestRebuild()` + `TriggerStaticSiteRebuildJob`. Dependía de `GITHUB_ACTION_*` por env; el nuevo usa webhook **por sitio**. No dispatcharlo desde código nuevo.
3. Un observer nuevo es una decisión de producto, no un detalle técnico: cada observer es «esto se publica solo».
