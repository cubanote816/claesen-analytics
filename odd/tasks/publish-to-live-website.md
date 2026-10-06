# Publicar desde el panel a la web en vivo

- **Rama:** `electrobertels/trunk`. Backend: `/home/totti/claesen/electrobertels`. Sitio: `/home/totti/electrobertel_official`
- **Origen:** pregunta del usuario (2026-10-06): «¿sería buena práctica tener un botón que explícitamente le diga al cliente empujar los cambios a la web en vivo? Una cosa es estar editando y otra tenerlo ya listo.»
- **Lo que la disparó:** el usuario editó ajustes en el panel (`:8004`) y **no se veían en el sitio local** (`:8080`).
- **Estado: OPCIÓN C ELEGIDA por el usuario (2026-10-06)** — en planificación. Este documento **todavía no autoriza trabajo**: las unidades de abajo esperan GO.

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

## Plan de ejecución de la opción C

Cinco unidades. La decisión que las ordena es la **WU2**: cambia el significado de «publicado» y por eso va primero.

### WU2 · Decidir y acotar el disparo *(HECHO — 2026-10-06)*

**Qué**: C implica que **el cliente publica deliberadamente**. Hoy los observers de `Project` y `Media` publican solos con 20 s de debounce, y eso haría inútil el «publico cuando esté listo».

**DECISIÓN DEL USUARIO: sin automático, todo explícito.** Nada se publica por guardar; siempre hay que apretar «Publicar», también para proyectos y media.

**El matiz que hace que esto sirva, y no que rompa la señal**: guardar **deja de reconstruir** pero **sigue marcando** «hay cambios sin publicar». Si guardar no marcara nada, el cliente perdería la única señal de que falta publicar — que es justo lo que C necesita para distinguir «estoy editando» de «está listo».

**Forma**: `requestRebuild()` queda como **la acción explícita** (es lo que ya llama el botón «Publicar ahora»), y los disparadores automáticos pasan a `markContentChanged()`, que marca pendiente y **no despacha nada**. Llamadores automáticos a cambiar: `ProjectObserver` (5 eventos), `MediaObserver` (2) y `GenerateGalleryMediaMetadataJob:94` (el camino de la galería, que publica desde su `finally`). **El `ListProjects.php:29` no se toca**: es el botón.

**Hueco que se cierra de paso (era el #2 del análisis)**: `SiteSetting` no tenía observer, así que editar ajustes no marcaba nada. Con C eso importa más que antes: sin él, editar ajustes no deja rastro de «pendiente» en ningún lado.

**Aceptación**: guardar un proyecto, una media o un ajuste deja el estado en `pending` y **no** despacha `TriggerStaticSiteRebuildJob`; la acción explícita sí lo despacha con `force: true`. Test-first, y el test que hoy afirma lo contrario se invierte.

**Efecto declarado**: es un **cambio de comportamiento** — hoy guardar un proyecto publica. Deja de hacerlo.

**HECHO (`ed61c30`)**. `markContentChanged()` marca sucio y no despacha nada; `requestRebuild()` queda como la acción explícita. Se cerró además el hueco del observer de `SiteSetting`, atado a `created`/`updated`/`deleted` y **no** a `saved`, porque la pantalla escribe las ~19 claves en cada envío y `saved` dispara incluso para filas que no cambiaron. **`Modules/Website` + `ClaesenBaseline`: 338 passed (1187 aserciones).**

**Dónde me equivoqué, y queda escrito**: afirmé que **un solo** test codificaba el auto-publicado. Eran **cuatro**. Mi grep encontró las líneas y **no leí su contexto**. Los cuatro se re-especificaron conservando lo que de verdad protegían: el de multi-sitio ahora asevera **qué sitio** quedó marcado, y como **fila ausente** — porque `PublicationState::current()` crea una fila idle al pasar y habría vuelto vacua la aserción —, y el de la media ajena ahora asevera que **nada** quedó marcado, no sólo que nada se encoló.

### WU3 · Estado y acción donde se edita *(HECHO en la pantalla de ajustes — 2026-10-06)*

**Qué**: el mismo botón + indicador de cambios pendientes en **cada** pantalla de edición, no sólo en la lista de Proyectos. Y que «pendiente» sea honesto: qué cambió desde la última publicación.

**Aceptación**: desde la pantalla de ajustes se ve si hay cambios sin publicar y se puede publicar; **verificado en navegador**, no sólo con tests.

**Dependencia**: necesita la respuesta de WU2 para saber qué estado mostrar.

**HECHO (`e97826d`)**, con un alcance que conviene leer despacio:

- **Un trait** (`ShowsSitePublicationState`) da el widget + la acción, y **una definición única** (`PublishSiteNowAction`) da la acción. No se copió nada: la lista de Proyectos se refactorizó para usar esa definición, conservando su acción de crear.
- **Aplicado a la pantalla de ajustes** (lo que pide la aceptación) y a la lista de Proyectos.
- **La etiqueta `pending` mentía**: decía «In de wachtrij» / «Queued» — en cola —, que era el modelo **automático**. Ahora significa que hay cambios esperando una decisión humana: «Wijzigingen niet gepubliceerd» / «Changes not published», en los cuatro locales.
- **`fr` y `de` no tenían NINGÚN bloque `publication`**, así que el widget y el botón salían como **claves crudas** ahí. Agregado en los dos. Y el test de traducciones del clúster **no podía verlo**: declara por escrito que sólo mira `nl` y `en` y que garantiza «que exista *alguna* traducción».

**Verificado en navegador, los tres estados**: `idle` («Geen wijzigingen») → `pending` («Wijzigingen niet gepubliceerd») → restaurado. Captura en `/tmp/wu3-2-pending.png`. **El flag se provocó por SQL** porque en local `static_site.enabled=false` y editar no puede marcarlo — a propósito, porque acá nada podría publicarlo. La fila quedó como estaba.

**Error mío, declarado**: apunté el UPDATE a `site_id=1` (Claesen) y afectó **0 filas**. El panel de Bertels muestra el estado de **su** sitio (id=2, `electro-bertels`) — es el **aislamiento por sitio funcionando**, no un defecto.

**Corrección de una aserción, no del código**: el widget es un **componente Livewire hijo**, así que `assertSee` contra el HTML de la página falla **incluso en la lista de Proyectos**, que lo tuvo siempre. La herramienta correcta es `assertSeeLivewire`.

**Alcance real, sin adornos**: el header quedó en **ajustes** y en **Proyectos**. Las otras pantallas donde se edita contenido —**Vertaalreview**, **Mediaslots**, **editar un proyecto**— todavía no lo tienen. Con el trait es **una línea cada una**; queda como **WU3b** para no meter seis pantallas en una sola revisión.

**Tests**: `SitePublicationHeaderTest` (3 nuevos) + la regresión de la pantalla de ajustes + el test de traducciones del clúster: **16 passed (337 aserciones)**.

### WU4 · Historial *(backend)*

**Qué**: quién publicó, cuándo, con qué resultado y qué cambió. Hoy `website_publication_states` guarda **estado actual** — una fila por sitio, sin serie temporal.

**Aceptación**: desde el panel se puede responder «¿quién publicó y cuándo?» y «¿qué cambió desde la última publicación?».

**Nota de diseño, antes de escribir la tabla**: «qué cambió» necesita una **base de comparación** — un hash del contenido publicado, o el commit del snapshot. Decidirlo primero.

### WU1 · Llevar el dato al build *(backend + sitio)*

**Qué**: que el build de la web tome el contenido del **API** en vez del snapshot congelado. Forma prevista: el release corre `sync-content.mjs` con `PUBLICATION_API_URL` **antes** de `astro build`. El `--optional` que el script ya soporta hace que, si el API no responde, el build avise y siga con el snapshot commiteado.

**Aceptación**: editar un ajuste en el panel, disparar un release, y que la web publicada muestre el valor nuevo — **medido, no razonado**.

**Toca**: `scripts/publish/release.mjs` y/o `.github/workflows/ci.yml` del **repo del sitio**. Ver la bandera 1.

**Riesgos**: el build pasa a depender de la red hacia el API (mitigado por `--optional`, pero hay que decidir si el aviso **falla** o **sigue**), y hay que confirmar que el runtime del release **alcanza** el API en producción.

### WU5 · Vista previa *(backend + sitio)*

**Qué**: que el cliente **vea** lo pendiente antes de publicar. Encaja con lo que ya hay: el release produce releases con un symlink `current`, así que un candidato puede construirse aparte y servirse en una URL de preview que el panel enlaza.

**Aceptación**: el cliente abre el preview desde el panel y ve el contenido pendiente antes de aprobar.

**Dependencia**: es la unidad más grande y la **menos especificada**. Necesita su propio diseño — dónde vive el preview, con qué URL, quién lo protege — antes de poder estimarla. No la empezaría sin eso.

**Orden propuesto**: **WU2** (la decisión) → **WU3** → **WU4** (las dos sólo backend, y la segunda necesita la primera para saber qué mostrar) → **WU1** → **WU5**.

## Dos banderas de alcance, antes de empezar

**1. El repo del sitio es otra línea.** WU1 y WU5 tocan `scripts/publish/release.mjs`, el CI y/o el receiver de `/home/totti/electrobertel_official` — el repo que esta sesión trató como de **otra línea** (su `media.snapshot.json`, las superficies `legal`/`projects` del sync, la página de edición de legales). Necesito **autorización explícita para tocarlo**, o coordinación con esa línea. Sin eso, WU1 y WU5 quedan fuera de mi alcance y C no se puede completar.

**2. C cambia comportamiento que hoy existe.** Proyectos y media **se publican solos** hoy. C implica publicar deliberadamente. Es la mejora de producto que se pidió, pero es un **cambio de comportamiento**, y queda declarado como tal — no como un detalle de implementación.

## La opción elegida

| | Qué implica | Costo |
| --- | --- | --- |
| **A · Seguir publicando desde el desarrollo** | Lo de hoy. Cero botón para el cliente. Hay que **declararlo**: «la web la publica el equipo» | Nulo |
| **B · Botón real** | WU1 + WU2 + WU3. El botón pasa a prometer lo que cumple | Medio |
| **C · Flujo completo de cliente — ELEGIDA (2026-10-06)** | B + historial (WU4) + vista previa (WU5) | Alto |

**La recomendación era A ahora, B después**, por un motivo que sigue siendo válido y por eso queda escrito: un botón sin el paso de datos promete algo que no cumple. El usuario eligió **C**, que resuelve esa objeción de raíz porque incluye WU1 — pero **el orden importa**: si se entregan WU3/WU4 antes que WU1, el botón queda visible y todavía mentiroso. Conviene no encenderlo hasta que WU1 esté hecho, o decirlo en la propia interfaz mientras tanto.

## Fuera de alcance / no verificado

- **`--surface=projects` no existe**: el CLI del sitio dice que es «a later slice». Sincronizar proyectos desde el API **no está implementado**.
- **No probé el bucle de publicación de punta a punta**: en local `static_site.enabled=false`, así que el webhook nunca se llamó. Todo lo anterior es lectura de código y configuración, más la medición del desfase contra el API local.
- **No toqué `.env` de ningún repo.** `PUBLICATION_API_URL` no está en `.env.local` del sitio ni figura en su `.env.example`, aunque el mensaje de error del CLI manda a «add it to .env.local (see .env.example)»: inconsistencia menor de documentación, medida.
- La línea del sitio (otra sesión) es dueña de `media.snapshot.json` y de las superficies `legal`/`projects` del sync.

## Hechos reutilizables que dejó esta investigación

1. La web **lee un snapshot commiteado**, no el API en vivo. El panel escribe en la base → el API lo sirve al instante → el sitio no lo ve hasta que alguien re-sincroniza. Son tres escalones y el del medio no se mueve solo.
2. `NotifyAstroFrontendJob` está **deprecado** en favor de `StaticSitePublicationService::requestRebuild()` + `TriggerStaticSiteRebuildJob`. Dependía de `GITHUB_ACTION_*` por env; el nuevo usa webhook **por sitio**. No dispatcharlo desde código nuevo.
3. Un observer nuevo es una decisión de producto, no un detalle técnico: cada observer es «esto se publica solo».
