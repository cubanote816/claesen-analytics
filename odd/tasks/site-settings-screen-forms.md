# Pantalla de ajustes del sitio: cada valor con la forma que tiene

- **Rama:** `electrobertels/trunk`. Worktree: `/home/totti/claesen/electrobertels`
- **Origen:** resumen de sesión 2026-10-04 (items 12, 13 y 14). Continuación directa de
  `odd/tasks/website-content-seeders.md` y de CLA-611.
- **Estado:** T0 hecho · T1 hecho · T1b hecho · T2 bloqueado por decisión del usuario.

## Qué queda abierto del 2026-10-04, y por qué

| # | Lo reportado | Estado real al abrir esta feature |
| --- | --- | --- |
| 13 | «BLOQUEANTE: la suite no puede correr sobre una base vacía» | **No reproduce.** Tres corridas verdes (T0) |
| 14 | `opening_hours` escrito y revertido por el bloqueante | **Desbloqueado por T0.** Es lo que implementa T1 |
| — | `mail_notification_email` vacío en los dos sitios | **No es un bug: falta el dato.** T2 |

**La causa común de 12 y 14, que es el motivo de esta feature:** `address_structured` y
`opening_hours` se editaban como **JSON en un campo de una línea** cuya única validación era
`rule('json')`. `"Balennn"` **es** JSON válido, así que un tecleo dentro del blob reescribía el
objeto entero sin que nada lo frenara — así se corrompieron datos dos veces con `causer=5`. El
domicilio ya se arregló (**cuatro campos**, verificado por test). `opening_hours` sigue igual, y
por lo tanto **la pantalla sigue pudiendo borrar los horarios de apertura**. Esto no es cosmética.

## Tareas

- [x] **T0** Corregir el registro del bloqueante de base vacía (docs; sin código de aplicación)
- [x] **T1** `opening_hours` como `Repeater`: un día por fila, `Select` de las cuatro claves del
      contrato, `Toggle` de cerrado. **Test-first**, con la verificación que la vez anterior no se
      pudo tener
- [x] **T1b** `lang/de` y `lang/fr` sin las etiquetas del domicilio: el mismo defecto que T1, en la
      misma pantalla. Medido, no supuesto: `nl` tiene 42 claves, a `en` no le falta ninguna, y a
      `fr`/`de` les faltaban exactamente `address.{street,postal_code,city,country_code}`
- [ ] **T2** (bloqueado por decisión del usuario) destinatario del aviso interno de solicitud nueva
- [ ] **T3** (necesita decisión) `MediaSlotsApiTest` está acoplado a `APP_URL` sin puerto; ver
      «Verificación final»

## T0 — el bloqueante no reproduce: qué se midió

Reproducción del fallo reportado (no una aproximación: misma config y misma base que la suite), más
una corrida desde una base **vacía de verdad**, que es la condición que el bloqueante decía romper.

| Prueba | Comando | Resultado |
| --- | --- | --- |
| Base descartable limpia (`migdiag_20261006`, `APP_ENV=testing`) | `php artisan migrate:fresh` | **EXIT=0** · `create_permission_tables … DONE` |
| Base de tests real (`claesen_analytics_web_testing`) | `php artisan migrate:fresh` | **EXIT=0** · idem |
| **Cero tablas** + suite | `php artisan db:wipe` → `php artisan test tests/Feature/SiteSettingsPageTest.php` | **6 passed (21 assertions)**; `RefreshDatabase` migró desde cero |

`roles` / `model_has_roles` / `role_has_permissions` quedan `ENGINE=InnoDB` correctos. Servidor:
MySQL **8.4.8**, `lower_case_table_names=0`, `foreign_key_checks=1`, sin `sql_mode` inusual.

**La hipótesis, declarada como hipótesis y no como causa probada:** el `1824` de MySQL es *«no puedo
**abrir** la tabla padre»*, no *«no existe»* — la firma de un diccionario de datos corrupto en el
**volumen local de Sail** (un `roles` a medio crear por una migración abortada). `migrate:fresh`
**dropea todas las tablas antes de crear**, así que la primera corrida verde limpió el estado sucio.
Eso explica las dos mitades del reporte: fallaba *siempre* en esa máquina y ahora no.

**Lo que esto implica, y es lo importante:** el defecto **no estaba en el repo**. Lo confirma la
estructura del CI, que ya tenía el paso correcto desde antes:

```
.github/workflows/tests.yml:105-106
  # Proves migrate-from-zero — the gap every differential waiver deferred here.
  run: php artisan migrate --force --no-interaction
```

sobre un servicio `mysql:8.4` limpio (`tests.yml:36-39`). **Por eso T0 no agrega un paso de CI: ya
existe.** Agregar otro sería ruido.

**Lo que sí quedaba mal y se corrigió:** el CI no corrió nunca sobre la punta actual de esta rama
(los últimos runs son del 2026-09-23, y el trunk está **24 commits adelante** de `origin`). Es decir:
el repo tenía la guarda, pero **nadie la había ejercitado** sobre este código. Queda anotado.

**Enmascaramiento que hay que recordar:** `RefreshDatabase` **no vuelve a migrar** si la base ya
existe. Una suite verde no prueba que un checkout limpio arranque; sólo lo prueba una base vacía.

## T1 — `opening_hours`, y la trampa que ya está resuelta

**La forma la fija el contrato, no la imaginación.** El sync del sitio lo rechazó una vez con
`opening_hours.day_key must be one of sunday, monday, tuesdayToFriday, saturday`. Son **cuatro**
claves, no siete:

```
[ { day_key: 'sunday'|'monday'|'tuesdayToFriday'|'saturday',
    hours: null | [ { open: '09:00', close: '12:00' }, … ] } ]
```

`hours === null` **significa cerrado**. Los valores reales tienen corte de mediodía.

**La trampa, que costó una reversión:** el `Repeater` anterior **no estaba roto**. El valor guardado
salía `null` porque **el test** metía a propósito un `day_key` inválido (`everyFullMoon`) y el
`Select` lo **rechaza antes de guardar, abortando todo el guardado, y hace bien**. El guardado nunca
corría por culpa del fixture, y el síntoma (columna vacía) parecía un bug de código. Por eso los
tests de T1 no repiten ese error de diseño: ver abajo.

**Test-first (RED antes de GREEN)**

1. **Round-trip con días válidos:** montar con el valor sembrado, guardar, y afirmar la forma de la
   columna. **Sin asertar orden de claves** de objeto: MySQL las reordena por longitud, así que
   asertar el orden afirma la base y no el código.
2. **Un día fuera del contrato no se guarda.** Se afirma el **mapper de guardado** —que es la última
   línea de defensa—, no el `Select`: el `Select` ya lo hace inalcanzable desde la UI, y ese es
   justamente el punto que confundió la vez anterior.
3. **Un día cerrado guarda `hours: null`**, y uno abierto guarda sus rangos. Es la regla del
   contrato que un Repeater puede romper en silencio.

**Verificación:** tests + **navegador** (los tests son necesarios y no suficientes para UI) + el
**juez del sitio**, `--surface=settings --check`, que debe seguir diciendo «already up to date»: un
round-trip que cambie el snapshot es un fallo, no una mejora.

## T2 — aviso interno: medido, y es un dato que falta

`mail_notification_email` es `NULL` en los dos sitios (dev y demo) y `WEBSITE_CONSULTATION_EMAIL`
está vacío en `.env`. `Site::notificationEmail()` devuelve `null` ⇒ el aviso **se omite por diseño**
(CLA-532), nunca falla. **No es un bug: falta a quién avisarle.** Necesita una decisión del usuario.

## Fuera de alcance (declarado)

- **Línea del sitio, otra sesión:** su `media.snapshot.json`, las superficies `legal`/`projects` del
  sync, la página de edición de legales.
- **SPF/DKIM/DMARC de `electrobertels.be`:** decisión del usuario en su DNS. El remitente ya sale de
  ese dominio; sin autorización DNS cae en spam. No verificable desde acá.

## T1 — resultado (2026-10-06)

**Qué quedó:** `opening_hours` ya no es JSON crudo. Un `Repeater` de **un día por fila** con `Select`
de las cuatro claves del contrato, un `Toggle` de cerrado, y un `Repeater` anidado de períodos
`{open, close}` con `TimePicker` en `H:i`. `distinct()` en el `Select` impide el mismo día dos veces.
Dos helpers (`openingHoursForForm` / `openingHoursForStorage`) son la única puerta entre la forma del
formulario y la del contrato, y el guardado recompone descartando lo que el contrato no define.
Además el *hint* dejó de describir el JSON crudo que se eliminó, en los cuatro locales.

**RED observado antes de tocar el código:** los dos tests que afirman la forma fallaban con
`null does not match expected type "array"` — porque `opening_hours` caía en la rama de JSON crudo y
`json_decode` de un array da `null`.

**GREEN:** 11 tests / 41 aserciones en el archivo (6 preexistentes + 5 nuevos). Regresión del lado del
API, que no toqué: `SiteSettingsWhitelistExtensionTest` 7/7 (44 aserciones).

**Verificado quitando el arreglo** (la disciplina que este repo ya usa):
- Volver al JSON crudo → fallan los tests del round-trip y del día cerrado.
- Quitar `->distinct()` → el test del día duplicado falla con `Component has no errors`, o sea que el
  duplicado **se guardaba**. El test muerde.

**Verificado con el juez externo del sitio:** `node scripts/dynamic/sync-content.mjs
--surface=settings --check` → `✔ the snapshot is already up to date`, y reporta «opening hours for 4
day row(s)».

**Verificado en navegador real** (Playwright + Chromium contra `:8004`, login con MFA por correo leído
de Mailpit, **sin guardar**):
- La sección renderiza con sus etiquetas y **ninguna clave de traducción cruda** en pantalla.
- Los 4 días con su estado real: `sunday` y `monday` cerrados, `tuesdayToFriday` y `saturday` abiertos.
- Los horarios reales tal como están sembrados: `09:00 12:00 13:00 18:00 09:00 12:00`, y **cero**
campos de hora en los dos días cerrados.
- Abrir un día cerrado **revela su período** (6 → 8 campos): es el `Toggle` gobernando la visibilidad
con `live()`.
- Al terminar se devuelve el estado original, sin guardar.

**Dos cosas que declarar, porque NO están verificadas:**

1. **El filtro `isOpeningHoursDay` del mapper es defensa en profundidad que ningún test alcanza.** El
   `Select` y la validación cortan antes, así que desde la UI no hay forma de meter un día fuera del
   contrato. No lo cuento como verificado.
2. **El test del día inválido pasaba —vacuamente— también antes del cambio**: con el JSON crudo,
   `rule('json')` rechazaba el array y no se guardaba nada, así que el `assertNull` era cierto por
   otra razón. Por eso ahora asevera **la ruta de error exacta** (`opening_hours.0.day_key`), que sí
distingue el rechazo del campo de cualquier otra rotura.

**Hallazgo del mismo tipo, en otra sección — ARREGLADO (`9778384`)**: `lang/de` y `lang/fr` no tenían
el bloque `address` que sí tienen `nl` y `en`, así que el domicilio mostraba claves crudas
(`website.site_settings.address.street`) en esos dos locales. Es preexistente (viene del commit
`4315313`), pero se arregló acá porque publicar un bloque nuevo sólo en algunos locales **es el mismo
defecto**, y las claves de `opening_hours` sí quedaron en los cuatro. **Medido, no supuesto**: `nl`
tiene 42 claves bajo `site_settings`, a `en` no le falta ninguna, y a `fr` y `de` les faltaban
exactamente cuatro: `address.street`, `address.postal_code`, `address.city`, `address.country_code`.
Después del arreglo los tres locales dan 0 faltantes y las etiquetas resuelven a texto real.

## Verificación final (2026-10-06)

**Suite completa del trunk: `2215 passed / 3 skipped / 1 failed`** (7883 aserciones, 37 min),
corrida con `php artisan test` dentro del contenedor.

**El único fallo no es de esta feature, y está probado con un experimento controlado.**

```
MediaSlotsApiTest > it serves slots with conversion url dimensions checksum and locale maps
Failed asserting that 'http://localhost:8004/storage/32/conversions/slot-optimized.webp'
              starts with "http://localhost/storage/".
```

El test hace una request **relativa** (`getJson('/v1/website/media/slots')`), así que Laravel le
antepone `config('app.url')`; el endpoint construye la URL desde el host de la request —que es ese
mismo— y después la aserción la compara contra `http://localhost/storage/` **hardcodeado**. Con
`APP_URL=http://localhost:8004`, el host lleva puerto y la aserción falla.

**La prueba de que no es de esta feature: la única variable que cambié fue `APP_URL`.**

| `APP_URL` | `MediaSlotsApiTest` |
| --- | --- |
| `http://localhost:8004` (el del contenedor) | **1 fallo** |
| `http://localhost` (override) | **8/8 passed** |

Y el `.env` del disco dice `http://localhost` (mtime del 2026-09-28): **el puerto lo aporta el entorno
del contenedor**, no el repo. O sea: el defecto es del test —una aserción que fija el host mientras su
propio comentario dice que quiere desacoplarse de `APP_URL`— y aparece en el entorno que el equipo
usa de verdad. **No lo toqué**: está en otro módulo y fuera del alcance aprobado, así que queda como
T3 con la causa ya medida.

**Irónico y vale decirlo:** la aserción que dice *«asserting the full `$media->getUrl('optimized')`
here would re-introduce the coupling to the configured APP_URL»* es, ella misma, una afirmación sobre
the configured `APP_URL` — sólo que hardcodeada.

**Nota de método:** el estado de un `Repeater` de Filament **está indexado por UUID**, no por índice.
Asertar `[0 => …]` contra el estado del formulario falla contra una clave uuid y **parece** un bug de
mapeo. Se lee con `array_values()`.

**Decisión de UX, con su razón:** un día cerrado llega al formulario con **un período en blanco**, para
que abrir el `Toggle` deje una fila lista para completar en vez de un hueco detrás de un botón
«añadir». `defaultItems` no puede hacerlo: cuando el día se abre, el `Repeater` ya tiene estado. El día
cerrado nunca lo guarda, porque el mapper escribe `hours: null` siempre que el `Toggle` dice cerrado.
