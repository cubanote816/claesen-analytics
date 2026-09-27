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
