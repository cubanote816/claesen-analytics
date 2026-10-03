# Seeders del contenido del sitio (para que el API tenga datos)

- **Rama:** `electrobertels/trunk`. Worktree: `/home/totti/claesen/electrobertels`
- **Pedido por el usuario (2026-10-03):** crear varios seeders que pueblen el website, **copiando del sitio actual** (`/home/totti/electrobertel_official`, servido en `127.0.0.1:8080`), y después enlazar el sitio de Astro para que **consuma la data de nuestro API**.
- **Estado:** P1, P2, P3, **P4 (desbloqueado)** y P5 hechos y verificados. P6/P7 pendientes.

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

- [x] **P1** Arreglar el defecto de `strict_locale_site_keys`: dice `electrobertels` donde todo lo demás dice `electro-bertels` (dos sitios en `Modules/Website/config/config.php`), así que `PublicLocalePolicy::isStrict()` es false para este sitio y los legales caen a neerlandés en vez de devolver null para un idioma no aprobado. Encontrado por la otra sesión al pinchar el contrato; está en trunk porque el merge trajo su línea
- [x] **P2** `SiteSettingSeeder`: las claves reales del whitelist con los valores aprobados. Sin `vat_number`
- [x] **P3** `ProjectSeeder` (DEMO): los 6 casos con sus imágenes, con la marca de demo **visible**
- [x] **P4** (desbloqueado: ver abajo) `MediaSlotSeeder`: los slots de `MediaSlot::SUGGESTED_SLOTS` apuntando a media de proyecto (la validación lo exige)
- [x] **P5** `LegalDocumentSeeder`: los 3 `doc_id`, título real y cuerpo de "en preparación"
- [ ] **P6** Verificar cada uno **por HTTP** contra el backend servido, no solo con tests
- [ ] **P7** Enlazar el sitio de Astro para que consuma el API (la superficie `settings` ya está escrita; las demás son el trabajo que el propio documento del sitio lista)

## Regla de trabajo de esta feature

Una superficie por vez: se siembra, se **mira el endpoint**, y recién ahí la siguiente. Nada se da por bueno porque el test pase.


## P1 y P2: hechos y verificados (2026-10-03)

**P1 — la clave del sitio, corregida.** `strict_locale_site_keys` y
`consent_version_required_site_keys` listaban `electrobertels` (sin guion) mientras el sitio se
llama `electro-bertels` en su `key`, su panel, su seeder y los tests. `isStrict()` compara contra
esa lista, así que nunca coincidía y el idioma estricto quedaba apagado. Corregido en los cuatro
sitios donde estaba. **Consecuencia declarada:** la misma lista mal escrita dejaba **opcional**
`consent_version` en el intake de Bertels; ahora es **requerido**, y el formulario del frontend
todavía no lo envía (lo dice el propio código del controlador). No rompe nada hoy porque el sitio
no consume la API todavía; rompería el primer formulario real.

**P2 — el seeder de ajustes, con los datos reales.** Trece claves del whitelist con los valores de
`facts.snapshot.json` del cliente. `vat_number` y `contact_consent_version` **no** se siembran, a
propósito y con el motivo escrito en el propio seeder.

**Verificado con el juez correcto: el script del sitio.** `node scripts/dynamic/sync-content.mjs
--surface=settings --check` contra nuestro trunk devuelve **`✔ the snapshot is already up to
date`** — es decir, nuestro API reproduce **exactamente** el snapshot congelado que el sitio
considera correcto. En el camino, su validador rechazó el primer intento por un detalle real:

    opening_hours.day_key must be one of sunday, monday, tuesdayToFriday, saturday

El snapshot del sitio llama `dayKey` a ese campo (son sus tipos internos) y **el contrato del API
usa `day_key`**. Los valores eran correctos; la forma no. Y su `--check` hizo lo que tenía que
hacer: **fallar sin tocar el snapshot**. Corregido.

**Trampa mía, declarada:** en la primera versión del seeder escribí horarios y URLs de mapas
**inventados** (09:00-17:00 continuo, una URL de maps fabricada). Los reales son distintos: la
clave de cuatro días `tuesdayToFriday` con **corte de mediodía** y el sábado solo por la mañana.
Se reescribió copiando los valores del snapshot. Es exactamente el error que esta tarea existe
para no cometer.


## P3: los proyectos DEMO, sembrados y servidos (2026-10-03)

Los 6 del sitio, copiados de sus YAML (no reescritos a mano): título, descripción, categoría,
ubicación, el caso completo del que lo trae (`work_story`, `challenge`, `solution`, `result` y sus
3 imágenes de galería con sus pies de foto) y las 9 imágenes copiadas a
`Modules/Website/resources/assets/demo-projects/` (344K).

**`year` va nulo y no se inventa** — el YAML tampoco lo trae. Igual los `facts` del caso (segmento,
técnica, duración…): el modelo no tiene dónde guardarlos, así que se pierden. Declarado, no
escondido.

**Cómo se marca DEMO, y qué marca de verdad.** El `title` empieza por `DEMO · `, y el `client`
lleva el aviso completo en neerlandés. **Pero el API no sirve `client`**: el `ProjectResource` no
lo expone entre sus campos. Así que la marca que ve un visitante es **el título**, y el aviso del
`client` no llega a ninguna página. Se deja escrito igual porque documenta la intención, pero
nadie debe creer que es la red de seguridad.

**Verificado por HTTP, con los nombres de campo del resource** (mi primera comprobación pidió
`client` y `api_featured_image_url`, que **no existen** — el resource sirve `featured_image` como
objeto con `thumb`/`optimized` y sus variantes AVIF): 6 proyectos, título marcado, imagen hero en
los seis, 3 imágenes de galería y el caso completo en el destacado, `year` nulo.

**Y lo que pasa en otro idioma es lo correcto:** con `Accept-Language: en`, el título vuelve
`null`. Es el sitio estricto funcionando —no inventa una traducción que no existe— y lo que
rellena esos idiomas es el motor de CLA-611.

⚠️ **Detalle observado, no arreglado aquí:** en algunos proyectos la URL del `thumb` apunta al
original en lugar de a una conversión, porque las conversiones de esas imágenes todavía no se han
generado (van por la cola). Con el worker en marcha terminan de generarse.


## P4 — bloqueado por un hueco del backend, no por falta de datos (2026-10-03)

El mapeo slot → imagen **ya está resuelto** y basado en leer el árbol, no en los nombres: es la
tabla S2.1 de `odd/tasks/dynamic-content-sync.md` del repo del sitio. Siete slots tienen imagen
(`over-ons.team`, `over-ons.cert-1..4`, `home.featured-diagram`, `home.shop-photo`), los tres
hero son placeholders vacíos por diseño (`GATE_REAL_MEDIA`: *"3 hero slots are empty"*), y
`winkel.photo` es un slot que la web **nunca** renderiza.

**Lo que impide sembrarlo:** todos esos slot apuntan a `media_id`, y `media` exige dueño
(`model_type` y `model_id` son NOT NULL), y el **único** modelo del módulo con media es
`Project`. Las siete imágenes son **imágenes de sitio** —equipo, certificados, diagrama, tienda—,
no fotos de obra: colgarlas de un proyecto las metería en la galería de ese proyecto, que el API
publica (`gallery` y `detail_gallery`). Un certificado apareciendo como foto de obra es
exactamente el tipo de dato falso que esta tarea existe para no producir.

Y no se puede rodear: la media sin dueño no existe en este esquema, y crear un proyecto que haga
de contenedor sería inventar una entidad para tapar el hueco.

**El hueco, dicho con precisión:** el backend modela **slots de sitio** pero sólo sabe guardar
media **de registro**. Su propio documento ya lo nombra como *"a backend gap to be ticketed, not
guessed away"*. La salida natural es que el sitio pueda tener media propia (`Site` con
`InteractsWithMedia`, o un dueño específico para slots), y entonces esta siembra es trivial.

**Decisión del usuario sobre los derechos, que sí queda registrada:** cuando ese hueco se cierre,
las imágenes del prototipo se siembran **marcadas como DEMO y sin fecha falsa** — el valor de
`usage_rights_confirmed_at` dirá que **no** están confirmados, en vez de afirmar una confirmación
que no existe. El endpoint sólo sirve un slot con esa propiedad *rellena*, así que la marca debe
ser un texto explícito, nunca una fecha inventada.


## P5: los legales, y el efecto de P1 por fin medido (2026-10-03)

Los tres documentos, con el texto que **existe hoy**, que no es el texto legal: título real
(`Privacyverklaring`, `Cookiebeleid`, `Algemene voorwaarden`) y **el aviso de "en preparación"
como cuerpo**, copiado de su propio contenido. Escribir aquí una política inventada sería lo peor
que podría hacer este seeder, y no se hizo.

**`version` y `effective_date` van marcados como lo que son**, por decisión del usuario: la versión
dice `DEMO` y la fecha de entrada en vigor es la de hoy. Ninguna de las dos significa nada
legalmente —el documento que entraría en vigor no existe— y el esquema las exige no nulas, así que
la alternativa era no sembrar.

**Y aquí queda medido el efecto de P1**, que estaba prometido para cuando existieran legales:

| Idioma | `title` | `body` | `translation_status` |
| --- | --- | --- | --- |
| `nl` (el que tiene contenido) | `Privacyverklaring` | el aviso | `machine` |
| `en` (sin contenido) | **`null`** | **`null`** | `missing` |

En inglés **no** devuelve el texto neerlandés: devuelve `null`. Eso es la política de idioma
estricto funcionando, y era el defecto que P1 corrigió —la clave del sitio estaba escrita sin el
guion, `isStrict()` nunca coincidía, y con el idioma estricto apagado estos campos caían a
neerlandés. Ahora mismo, con la clave bien, el comportamiento es el correcto y está comprobado por
HTTP en los dos idiomas.


## P4 desbloqueado: el sitio tiene su propia media (2026-10-03)

Decisión del usuario: *«crea sus propios dueños si es una buena práctica»*. **Lo es**, y por eso se
hizo: una foto de equipo o un certificado **no** es una foto de obra, así que su dueño natural es el
**sitio**, no un proyecto. Es aditivo (`media` de Spatie es polimórfico, no toca el esquema) y no
cambia nada de Claesen.

Lo que se hizo:

1. **`Site` gana media** (`InteractsWithMedia` + colección `imagery`, mismo disco y formatos que las
   colecciones de proyecto).
2. Las **conversiones** salen a un trait compartido, `Modules\Core\Models\Concerns\SiteImageConversions`,
   con los mismos tamaños y calidades que ya tenía `Project`. Vive en **Core** y no en Website
   porque lo usan los dos, y un modelo de Core no debe depender de Website: la dirección se respeta.
   ⚠️ El método se llama `registerSiteImageConversions`, **no** `registerMediaConversions`: ese
   último es el hook de `InteractsWithMedia` y dos traits con el mismo método en la misma clase son
   un **fatal** (`has not been applied ... because of collision`). Se descubrió rompiendo el
   arranque de la app, que es una forma cara de aprenderlo.
3. `Project::MEDIA_MIME_TYPES` **sigue resolviendo** aunque la constante viva en el trait, porque las
   constantes de trait pasan a ser de la clase. Su test de contrato (`ProjectMediaContractTest`,
   7/7) es la prueba.
4. El selector de media del panel (`MediaSlotResource::mediaOptions`) ahora ofrece también la media
   del sitio, no sólo la de proyectos. Antes era imposible elegirla.
5. `MediaSlotSeeder`: los **7 slots con imagen** de la tabla S2.1 del sitio. Los **3 hero no se
   siembran** (no existe imagen suya: su repo los documenta vacíos por diseño) y `winkel.photo`
   tampoco (*«the API offers a slot the site never renders»*).
6. **Derechos de uso marcados como lo que son**: el valor dice que **no** están confirmados
   (imágenes del prototipo), nunca una fecha. El API sólo sirve el slot si esa propiedad está
   rellena, así que el texto es lo que permite probar el circuito sin afirmar una confirmación.

**Verificado por HTTP**: 7 slots servidos con `url` de conversión (los originales siguen privados),
`width`, `height` y `checksum` `sha256:…` — exactamente el contrato que su documento fijó.

⚠️ **Defecto encontrado en el controlador del sitio, no arreglado aquí** (es de la otra línea):
`MediaSlotController::conversionMeta()` cachea el resultado **vacío** durante una hora con una clave
que incluye el `updated_at` del media pero **no** el hecho de que la conversión ya exista. Consecuencia:
recién sembrado, mientras la cola genera las conversiones, el endpoint responde `width`/`height`/
`checksum` en `null` **y los sigue respondiendo hasta una hora después** de que el fichero exista.
Se comprobó vaciando la caché: los valores aparecen de inmediato. El arreglo natural es no cachear el
caso vacío, o incluir en la clave la existencia de la conversión.
