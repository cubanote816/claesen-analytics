# CLA-609 — Veld API (V11): backend de campo

- **Rama:** `electrobertels/knx-api` (sobre `knx-entry-point`). Worktree: `/home/totti/claesen/electrobertels-knx-api`.
- **Linear:** CLA-609 (In Progress). Engram mirror: `odd/cla-609-veld-api-v11/tasks`.
- **Objetivo:** servir los 8 endpoints de `electro-bertels-veld/docs/BACKEND-API-VELD.md` sin tocar el contrato congelado de la app de campo ni el de oficina.
- **Alcance autorizado:** V11.b–d de abajo. Sin push a `main`, sin PR.
- **TDD:** no habilitado por configuración. Checks: `Modules/Knx` en verde + verificación HTTP real de cada endpoint (los bugs de V11.a los encontró el HTTP, no los tests).
- **Doc del módulo:** `docs/Knx/knx-kantoor-backend.md`. Handover: `docs/Knx/handover-frontend.md`.

## Tareas

- [x] **V11.a** Sesión de campo y trabajo del día — `GET /field/session`, `GET /field/today` (+ guard por app `EnsureKnxApp`, login compartido, zonas de lectura compartida). `7246b5a`, `0be219a`
- [x] **V11.b** Proyecto y planos — `GET /field/projects/{code}`, `GET /field/projects/{code}/plans`
- [x] **V11.c** Registro de aparatos — `POST /field/projects/{code}/devices` (idempotente por `clientId`, 409 + conflicto `duplicate_address`)
- [x] **V11.d** Incidencias — `POST /field/projects/{code}/issues` (idempotente por `clientId`, contextualizada)
- [x] **V11.e** Cierre de visita en 3 fases — `POST /field/projects/{code}/visits` (siguiendo `VELD-PLAN.md` §7.4) + informe `hours` real

## Decisiones tomadas (y su porqué)

1. **Alcance = asignado hoy.** `FieldTodayService::isAssignedToday()` es la única respuesta a "¿puede este técnico tocar este proyecto?", y V11.b–d la usan. Código desconocido → `404`; proyecto que existe pero no es suyo hoy → `403` (permiso, no existencia): se distinguen para que un typo no se confunda con un permiso.
2. **`deviceTypes` se deriva de los aparatos del proyecto** (distinct, en orden de inserción). No hay tabla de catálogo en el dominio y no se inventa una. Consecuencia declarada: un proyecto sin aparatos devuelve `[]` y el formulario de campo se queda sin opciones.
3. **`/plans` = documentos de dibujo** (`kind` ∈ `Plan`, `Schema`) del proyecto. `mimeType` se deriva de la extensión del fichero (`.pdf`→`application/pdf`, `.svg`→`image/svg+xml`, …): el documento de oficina no guarda el mime.
4. **`pages` y `mime_type` son columnas nuevas** en `knx_documents` (la app de oficina no tiene ninguno de los dos en su contrato). `pages` por defecto 1: son planos de una hoja y **la propia fixture de Veld declara `pages: 1`** para los mismos documentos, así que el valor no es inventado.
5. **`url` de los planos = la descarga firmada que ya existe**, con caducidad larga para que el cliente pueda cachear el fichero y abrirlo sin conexión.
6. **Registrar un aparato crea DOS cosas**: el aparato (`source = field`, `acknowledged_at = null` → `isNew` en el dossier) y su notificación (el *evento* que ve la bandeja de oficina). Es el mismo par que usa el fixture existente; no se abre un camino paralelo.
7. **409 `address_in_use`: el aparato que molesta va en `existing`** (arriba del cuerpo, como pide el documento de Veld) **y dentro de `errors`** (porque el parser de Veld lee `errors` primero: `data.errors ?? payload` — el sobre siempre emite `errors`, así que si no estuviera ahí el cliente no lo encontraría). Verificado contra su `http.ts`.
8. **El 409 además se registra**: conflicto `duplicate_address`, severidad `critical` (como la fixture), `device_existing` = el aparato registrado, `device_field` = el registro entrante, con la foto como evidencia. El técnico ve el conflicto en Kantoor sin que nadie lo copie a mano.
9. **Las incidencias se reflejan como conflicto** con el `kind` mapeado a los tipos que la oficina YA tiene (`damaged`, `missing_device`, `plan_mismatch`) y la severidad que la fixture ya usa por tipo (`damaged`→`info`, `missing_device`→`warning`, `plan_mismatch`→`warning`). El espacio, el equipo y el canal se conservan en `device_field` (la "descripción humana del registro que viene de campo"): es la convención que la propia fixture ya usa (`tipo · espacio · detalle`), no una invención.
10. **`kind: other` → `422` con `errors.kind`.** El tipo `other` de Veld NO existe en el contrato de oficina (`ConflictType` = duplicate_address | missing_device | plan_mismatch | damaged) y su etiqueta es un `Record<ConflictType, …>` **sin fallback**: guardarlo daría `undefined` en pantalla. Se declara el hueco en vez de inventar un tipo o etiquetar mal. Reversible en una línea si se decide ampliar el contrato de oficina.
11. **Idempotencia por `clientId`**: columna nueva con índice único en `knx_devices` y en `knx_conflicts`, más `captured_at` y `photo_path` en `knx_devices`. El `client_id` es un UUID generado en el dispositivo: único global, así que un reintento nunca duplica ni cruza proyectos.
12. **`registered_at` = `captured_at`** cuando llega: el aparato se registró cuando el técnico lo hizo, y `created_at` ya guarda cuándo lo recibió el servidor. Es lo que permite ver un registro hecho sin conexión.

## Criterios de aceptación
- Los 7 endpoints de V11.a–d responden con la forma del contrato de Veld (`src/api/types.ts` manda sobre los `.md`).
- Un token de oficina no entra en `/field/*` y uno de campo no entra en el API de oficina.
- Un técnico no puede leer ni escribir en un proyecto que no tiene asignado hoy.
- Reprocesar el mismo `clientId` no crea filas nuevas y devuelve el mismo resultado.
- Un choque de dirección → `409 address_in_use` + conflicto visible en Kantoor + foto guardada.
- `Modules/Knx` en verde y **verificación HTTP real** de cada endpoint nuevo.
- Documentos actualizados: módulo + handover de los dos repos de frontend.

## Progreso / evidencia
- V11.b: `e5ae254` (proyecto y planos; columnas `mime_type`/`pages`; PDFs reales de fixture).
- V11.c: `7d0b027` (registro de aparatos; `client_id` global en `knx_devices` y en `knx_conflicts`; 409 + conflicto con foto).
- V11.d: incidencias (mapeo de `kind`, contexto conservado, `other` → 422 declarado).
- Tests: `Modules/Knx` **168/168** (959 aserciones). Verificación HTTP real de los tres endpoints, incluido lo que ve la oficina.
- **Dos bugs reales encontrados por el camino** (ninguno visible con los tests en verde):
  1. El orden de las salas: el índice `(project_id, name)` hacía que MySQL las devolviera alfabéticamente, no en el orden de modelado que espera la fixture de Veld.
  2. `Collection::mapInto()` pasa la **clave** de la colección como segundo argumento del constructor: `DocumentResource` recibía `bool $withUrl` ahí, así que del segundo ítem en adelante salía `true` y `GET /documents` construía una URL firmada por fila. No se veía porque la fixture no tenía ficheros; al empezar a escribir planos reales apareció. Arreglado de raíz en `KnxResource::list()` + `withUrl()` fluido.
- V11.a: `7246b5a` (sesión, hoy, guard por app, login compartido, zonas) y `0be219a` (un login para las dos apps, zonas compartidas, demo usable). 126/126.
- Hallazgo de V11.a que justifica la verificación HTTP: `actingAs()` en los tests nunca pasaba por `/auth/login`, así que dos bugs reales (técnico sin login, `/zones` detrás del guard de oficina) vivieron hasta la prueba manual.

## Siguiente paso
**V11 cerrado entero.** Queda una decisión de contrato de oficina: `kind: other` de las incidencias.
Impacto medido de ampliar `ConflictType` con `other`: **3 ficheros, 6 líneas** en el front de Kantoor
(`src/api/types.ts` unión · `src/lib/constants.ts` `CONFLICT_TYPE_LABEL_KEY` · `src/i18n/translations.ts`
las dos entradas `ct`) y el **compilador lo verifica**, porque el `Record<ConflictType, …>` obliga a
añadir la etiqueta. No hay ningún `switch` ni comparación sobre el tipo en todo su `src`, así que no se
rompe nada más. Alternativa: quitar `other` del formulario de campo y que el técnico elija entre los tres.
En backend son dos líneas (`KIND_TO_TYPE` + `TYPE_SEVERITY`).

## Cierre de auditoría de V11.e — rama aislada `cla-609-v11e-audit-gaps`

Auditoría del commit `d273d24` contra el contrato congelado de Veld
(`electro-bertels-veld/src/api/types.ts` §Visit closing). La forma del contrato **coincide**:
el POST responde exactamente `{id, clientId}`, con `201` en la creación y `200` en el reintento.
Los tres puntos de abajo no rompen el contrato, pero son deuda declarada.

Trabajo en **rama aislada** a propósito: el worktree compartido tenía una verificación en
vuelo de la instancia que lo posee, y escribir ahí habría contaminado su corrida. Base `d273d24`.

- [x] **A1 — Carrera de idempotencia.** `FieldVisitService::close()` obtuvo el `clientId` con un
  `first()` y recién después inserta: dos reintentos concurrentes pasan ambos el chequeo y el
  segundo `INSERT` viola el índice único de `client_id` → **`500` en vez de `200`**. El contrato
  dice que un reintento devuelve el mismo resultado, así que un `500` es una respuesta
  **incorrecta**, no un caso raro: el cliente encola cierres sin conexión y reintenta por diseño.
  El mismo patrón existe en V11.c; arreglarlo ahí queda **fuera de alcance** de esta rama.
- [x] **A2 — `workDone` sin asertar.** Viaja en el payload y ningún test comprueba su valor, ni en
  `work_done` ni en el JSON de respuesta. Es el único campo que el técnico escribe con sus
  palabras y el contrato lo describe como "trabajo realizado / alcance entregado / resumen".
- [x] **A3 — `404` sin test.** `/visits` con un `code` inexistente (`resolveAuthorized` →
  `ModelNotFoundException`) no está cubierto; sólo el `403` de "no asignado hoy".

Evidencia de cierre se anota acá al terminar cada punto.

### Cierre y evidencia

**A1 — arreglado.** `close()` ahora relee a través de un único helper `replayFor()` (sin duplicar el
chequeo de proyecto ajeno) y captura `UniqueConstraintViolationException` alrededor de la
transacción. La recuperación relee **fuera** de la transacción a propósito: bajo el
`REPEATABLE READ` por defecto, una lectura dentro de la transacción fallida repetiría la foto
vieja y nunca vería la fila que ganó. Si no encuentra ganador, relanza en vez de inventar un
cierre. El contrato de `close()` (`created: bool`) no cambió, así que el 201/200 del controlador
quedó intacto.

Dos tests nuevos, y la violación es real, no simulada: la fila ganadora se escribe por una
**segunda conexión** dentro de un hook `creating`, la pre-consulta del servicio falla de verdad y
el `INSERT` choca contra el índice único real.

**RED (sin el arreglo, verificado que el `catch` no estaba en el código que corría):**

```
⨯ a retry that loses the insert race answers with the row that won
    Failed asserting that 500 is identical to 200.
⨯ a retry that won the race in another project is rejected
    Failed asserting that 500 is identical to 422.
Tests: 2 failed (2 assertions)
```

**GREEN (con el arreglo):** `Modules/Knx` **185 passed (1031 assertions)**, 0 fallos.

**A2 — cubierto.** `work_done` se asevera al crear, y además se comprueba que un reintento con
otro texto **no reemplaza** el parte guardado: la idempotencia vale para el contenido, no sólo
para el `id`.

**A3 — cubierto.** `test_an_unknown_project_code_is_not_found` afirma `404` con el sobre del
módulo (`code: not_found`), y de paso que el alcance corre **antes** de la validación (un código
desconocido no es un error de `projectCode` del cuerpo).

### Trampa del banco de pruebas (costó dos corridas falsas)

El worktree aislado tenía `vendor` como **symlink** al del worktree compartido. PHP resuelve
`__DIR__` al destino del symlink, así que `vendor/composer/autoload_static.php` calculaba su
`baseDir` en **aquel** directorio y las clases `Modules\Knx\*` se cargaban desde el worktree
compartido. Consecuencia: las corridas ejecutaban el código de la otra sesión, se veían dos
fallos que no eran míos (un test de `kind: other` recibiendo `201` cuando en `d273d24` debe dar
`422`) y mi arreglo no se ejercitaba nunca. La comprobación barata que lo delata:

```
php -r 'require "vendor/autoload.php"; echo (new ReflectionClass("Modules\\Knx\\Models\\KnxVisit"))->getFileName();'
```

Arreglo: `vendor` real dentro del worktree (copiado, no enlazado) más `composer dump-autoload`
ejecutado **dentro** del worktree. Regla para la próxima: en un worktree enlazado, nunca compartir
`vendor` por symlink si se va a ejecutar código.

La base de datos sí quedó aislada de verdad desde el principio (`knx_v11e_testing` en el mysql
propio del repo, puerto 3308), así que nada de esto tocó los datos de la otra sesión.
