# Módulo Knx — backend de Kantoor y Veld (Electro Bertels)

> Ticket activo: **CLA-604** (K0). Programa: `Electro Bertels — Web y backoffice multisite`.
> Estado: en implementación, sin push.

## Qué es

El backend que consumen las dos apps KNX de Electro Bertels:

- **Kantoor** (oficina, React) — `/home/totti/knx/electro-bertels-kantoor`
- **Veld** (campo, React) — `/home/totti/knx/electro-bertels-veld`

Los contratos viven en esos repos y **son la fuente de verdad**:

| Documento | Contenido |
|---|---|
| `electro-bertels-kantoor/docs/BACKEND-API.md` | 32 endpoints, tipos exactos (§3), esquema sugerido (§5), checklist (§8) |
| `electro-bertels-kantoor/docs/BACKEND-API-ZONES.md` | Zonas (contrato cerrado), fichas funcionales y pruebas |
| `electro-bertels-kantoor/docs/openapi.yaml` | OpenAPI 3 |
| `electro-bertels-kantoor/src/api/types.ts` | Tipos reales del front — **manda sobre los `.md` cuando difieren** |

El front ya está terminado y funciona en modo `mock`. Enchufarlo es
`VITE_API_MODE=real` + `VITE_API_BASE_URL=/api/v1/knx`: **no se toca el front**.

## Decisiones de arquitectura

1. **Módulo `Modules/Knx`**, no `FieldOps` (eso son luminarias exteriores de Claesen) ni Website. El dominio es la gestión de instalaciones KNX y lo consumen las dos apps.
2. **Rutas `/api/v1/knx/...`** (el repo versiona así; el front usa base configurable).
3. **Tenant: `organization_id` + `organization:electro-bertels`** (middleware existente, CLA-552). El doc dice `company_id`; el esquema usa `organization_id`. `KnxTenant` es el único sitio donde se resuelve, y **falla en voz alta** si Bertels no existe en vez de caer a Claesen.
4. **Auth Sanctum** sobre la tabla `users`; `/auth/refresh` rota el token (el front solo guarda `access_token`).
5. **Formato de error exacto de §2.5** (`message`, `code`, `errors`).
6. **Nada de `unique(project_id, address)`**: la dirección duplicada **es** el conflicto que oficina resuelve.
7. **Personas propias del dominio** (`knx_employees`), ver abajo.
8. **Estados de conflicto: 8** (`open → in_review → ets_listed → applied → downloaded → verified → closed`, + `rejected`), porque `types.ts` los tiene; §3 del doc quedó con 4 y está desactualizado.

## Personas: `knx_employees`

Toda referencia humana del contrato (`lead`, `registeredBy`, `reportedBy`,
`uploadedBy`, `executor`, `updatedBy`) resuelve a esta tabla, y el string que
espera el contrato se **deriva** (`shortName()`), no se duplica.

- `kind`: `office` (Kantoor) | `field` (Veld).
- `knx_role`: rol **de negocio** (`lead`/`planner`/`technician`/`admin`) = el `Session.role` del contrato.
- `user_id` **nullable y unique**: la cuenta, cuando la hay. Se puede planificar trabajo a alguien que aún no ha entrado nunca.
- Roles de **app** `knx_office` / `knx_field` (Spatie): acceso a Kantoor/Veld. **No** dan acceso a los paneles Filament — estos usuarios no son usuarios de backoffice.

### Alta de usuarios Bertels — **PLANIFICADO, no implementado**

Decisión del usuario (2026-09-26):

> Los usuarios son los que **existen en Cafca** (ERP), pero **desde la app KNX los jefes de proyecto pueden crear usuarios que no tienen relación con Cafca**.

Implicaciones a resolver antes de implementar (no bloquea K0-K10):

1. `knx_employees` necesita un enlace opcional al empleado del ERP (`Modules\Cafca\Models\Employee`) además de la cuenta, para poder responder "esta persona es de Cafca" vs "esta persona la creó un jefe de proyecto".
2. El alta desde Kantoor la hace un `project lead` (`knx_role = lead`), es decir **desde la API**, no desde Filament — hay que decidir permisos (¿solo leads? ¿un lead crea solo gente de sus proyectos?) y el dominio de correo permitido (hoy el de Bertels es `@electrobertels.be`, y el flujo de Claesen exige `@claesen-verlichting.be` + un `Employee` del ERP: eso es justo lo que CLA-599 dejó fuera de alcance).
3. Reconcilia con CLA-599: "crear usuarios de Bertels desde el formulario" sigue fuera de alcance del backoffice; la vía buena es que nazca aquí.

## Slices

| # | Alcance | Estado |
|---|---------|--------|
| **K0** | Módulo, 16 tablas, modelos, personas, envelope de errores, seed del mock | ✅ cerrado |
| **K1** | Sesión: `POST /auth/login\|refresh\|logout`, `GET /me/session` | ✅ cerrado |
| **K2** | Clientes y proyectos: `/clients`, `/clients/{id}`, `/projects`, `/projects/{code}`, `/stats` | ✅ cerrado |
| **K3** | Dossier: `/projects/{code}/devices`, `/projects/{code}/activity` | ✅ cerrado |
| **K4** | Entrada de campo: `/notifications`, `ack`, `ack-all` | ✅ cerrado |
| **K5** | Planificación: `/technicians`, `GET/PUT/DELETE /planning` | ✅ cerrado |
| **K6** | Conflictos: lista/detalle/`PATCH` con histórico y `409 address_in_use` | ✅ cerrado |
| **K7** | Zonas: `GET /zones`, `GET /zones/{id}`, `PATCH /zones/{id}/checks/{key}` con estado derivado | ✅ cerrado |
| **K7b** | `/dashboard` (todos sus agregados ya existen) | ✅ cerrado |
| **K8** | Documentos (URLs firmadas) y `POST /reports` en cola + `/reports/exports` | ✅ cerrado |
| **K9** | Fichas funcionales y pruebas de aceptación | ✅ cerrado |
| **K10** | (Opcional) `GET /events` SSE | ✅ cerrado |
| **V11.a** | **Veld**: cuentas de campo, `GET /field/session`, `GET /field/today` | ✅ cerrado |
| V11.b | Veld: `GET /field/projects/{code}`, `…/plans` (offline) | ✅ cerrado |
| V11.c | Veld: `POST …/devices` (idempotente, `409 address_in_use`) | ✅ cerrado |
| V11.d | Veld: `POST …/issues` (contextualizada, idempotente) | ✅ cerrado |
| V11.e | Veld: `POST …/visits` (3 fases) | ✅ cerrado |

**El contrato está completo:** los 36 endpoints de `/api/v1/knx` cubren las 32 llamadas que hace el cliente real del front (`src/api/real/index.ts`), incluidos login/refresh/logout y las descargas firmadas, que el front todavía no consume.

## Sesión (K1)

| Método | Ruta | Qué hace |
|---|---|---|
| `POST` | `/api/v1/knx/auth/login` | email + contraseña → `{access_token, expires_in, user}` (throttle 5/min) |
| `POST` | `/api/v1/knx/auth/refresh` | rota el token presentado → `{access_token, expires_in}` (throttle 10/min) |
| `POST` | `/api/v1/knx/auth/logout` | revoca el token presentado, `204` |
| `GET` | `/api/v1/knx/me/session` | el `Session` del contrato; `401` si el token ya no vale |

**Quién puede entrar** (dos condiciones, ambas en `KantoorAuthService::authorize()`):
1. la cuenta está activa y tiene el rol `knx_office` → **acceso a la app**;
2. la cuenta pertenece a **Electro Bertels** y está enlazada a una persona de **oficina** activa → **identidad** (sin esa fila no hay `name`/`initials`/`role` que devolver).

**El tenant se comprueba siempre, sin depender del flag.** `organization:electrobertels` es un no-op mientras `config('organizations.enforce')` esté en `false` (el default en todos los entornos), así que apoyarse solo en él habría dejado entrar a una cuenta de Claesen emparejada con una persona de Bertels. En un módulo mono-tenant no hay motivo para depender de que alguien encienda un flag: la regla vive en el servicio. **Sin excepción para `super_admin`** (el ADR es explícito: incluso el super_admin trabaja dentro de una empresa).

**Desviaciones del §7, las dos a propósito:**
- **No hay refresh token separado.** El front solo guarda el access token, así que `refresh` acepta el `refresh_token` del documento *o* el Bearer y rota el que reciba. Un segundo credencial habría sido maquinaria sin usar.
- **`expires_in` = `config('knx.token_expiry_minutes') * 60`** (60 min por defecto; el documento recomienda 15–60).

Un `401` de `/me/session` es lo que el front usa para saber que debe borrar el token. Toda negativa de login responde **igual** (422 `validation_error` con `errors.email`): no se puede averiguar qué cuentas existen, ni cuál de las dos condiciones falló.

### Cuentas del seed (Kantoor)

| Email | Contraseña | Persona | Rol de negocio |
|---|---|---|---|
| `lien.smet@electrobertels.be` | `Kantoor123!` | Lien Smet | `lead` |
| `pieter.aerts@electrobertels.be` | `Kantoor123!` | Pieter Aerts | `planner` |

Las crea `KnxDemoSeeder` (`KnxDemoSeeder::DEMO_PASSWORD`) junto con la persona de oficina enlazada. Los técnicos se siembran **sin cuenta** a propósito: son trabajo planificado, no logins (Veld les dará cuenta cuando exista).

## Payloads: sin envoltorio

El contrato describe los objetos y arrays **tal cual** (`{id, name, …}`, `[{code, …}]`), nunca dentro de `{data: …}`. Para lograrlo hay dos piezas, y por eso existe `Http/Resources/KnxResource`:

- `$wrap = null` cubre un **recurso suelto** (la respuesta lee el estático de esa clase);
- `KnxResource::list()` cubre una **lista**, porque `ResourceResponse` lee el envoltorio de la clase *colección* — que es de Laravel y dice `data` siempre, aunque el recurso interno lo desactive.

Cualquier recurso nuevo del módulo debe extender `KnxResource`, y cualquier endpoint que devuelva una lista debe usar `::list()`.

## Dossier (K3)

`GET /projects/{code}/devices` devuelve **el plan ETS y lo registrado en obra**, con los de campo primero (orden del contrato) y, dentro de cada grupo, por dirección. Dos banderas derivadas, nunca columnas:

- **`isNew`** — registro de campo que oficina **aún no ha confirmado** (`acknowledged_at IS NULL`).
- **`hasConflict`** — su dirección choca con un conflicto del proyecto todavía en `open`/`in_review`. Las direcciones se cargan **una vez** para toda la lista, no por aparato.

### Notificación ≠ aparato (importante)

La notificación es el **evento** (lo que reportó Veld, con su `acked`), el aparato es el **registro**. Son dos filas enlazadas por `knx_notifications.device_id`, y por eso:

- el dossier muestra el aparato aunque oficina aún no lo haya confirmado (marcado `isNew`), que es lo que el mock hacía;
- la bandeja de `/notifications` sigue mostrando el elemento pendiente.

Un aparato de campo puede referenciar una **sala o un cuadro que oficina no tenía modelado** ("Zaal 2.07", "Verdeelbord E21"): se crean al vuelo como fila. Sin eso, `Device.room` (que el contrato tipa como string) vendría `null`.

### Actividad derivada, no un log aparte

`GET /projects/{code}/activity` se compone de las filas que ya existen:

| `kind` | De dónde sale |
|---|---|
| `conflict_reported` | los conflictos del proyecto |
| `devices_registered` | aparatos de campo **agrupados por persona y día** (una entrada con `count`, no una por aparato: en un proyecto de 60 aparatos el feed sería ilegible) |
| `plan_uploaded` | documentos subidos |
| `photos_uploaded` | **no se emite**: nada modela las fotos como eventos (el proyecto solo lleva un contador). Llega cuando Veld tenga tabla de fotos. |

## Bandeja de campo (K4)

`GET /notifications` · `POST /notifications/{id}/ack` · `POST /notifications/ack-all`.

La regla de dominio vive en `FieldNotificationService` y en un solo sitio:
**confirmar una registración confirma también el aparato que creó.** Eso es lo que
borra el `isNew` del dossier: notificación y aparato son el mismo hecho visto desde
dos lados (el evento y el registro), así que dejar el aparato sin confirmar después
de que oficina haya dicho "visto" mantendría una alerta sobre algo ya triado.

Las dos operaciones son **idempotentes** (confirmar dos veces no es un error: un
front que hace polling cada 5 s lo hará).

`GET /notifications` incluye los ya confirmados con `acked: true`, para que oficina
siga viendo lo que acaba de limpiar tras el siguiente poll. Un cuadro desconocido
llega como `board: null` + `boardName: null` — es el caso que la bandeja existe
para triar, no un error.

## Planificación (K5)

`GET /technicians` · `GET /planning?from&to` · `PUT /planning` · `DELETE /planning`.

- **Solo los `field` son técnicos**: el personal de oficina nunca se planifica, y mandar un id de oficina responde 422 con el error en `technicianId` (un `abort(422)` no lleva errores por campo, así que el front no podría señalarlo).
- **`PUT` es idempotente en (technicianId, date)**: la misma llamada dos veces deja una fila, y con otro proyecto **reemplaza** (es lo que hace un planificador al arrastrar la fila). Lo garantizan `updateOrCreate` **y** el índice único de la tabla, así que dos peticiones concurrentes tampoco crean dos filas.
- **`DELETE` de un día vacío no es error** (204): el estado que pide el llamante ya es el que hay.
- `abort(422, …)` **no** produce `errors` por campo: para fallos que el front debe señalar en un control hay que lanzar `ValidationException`.

⚠️ **§1.6 pendiente de decisión de producto:** el contrato sugiere que `PUT /planning` responda con un *warning* estructurado cuando el proyecto tenga zonas sin preparar. No se implementa aún (definido como decisión abierta) y el aviso de momento es cliente. El endpoint devuelve el `PlanningAssignment` plano que el contrato tipa.

## Conflictos (K6)

`GET /conflicts?status&project&q` · `GET /conflicts/{id}` · `PATCH /conflicts/{id}`.

- Orden del contrato: severidad (`critical` → `info`) y después fecha descendente. `status=active` = `open`+`in_review` (el conjunto de trabajo de oficina, que **no** es un estado almacenado).
- **El histórico es append-only.** Cada cambio de estado escribe una fila en la misma transacción, con la dirección que estaba en juego en ese momento: ese log **es** la lista de corrección ETS, así que no puede ser un efecto secundario que se pueda saltar. Mandar el mismo estado **no** añade línea (el front manda la propuesta junto al estado en su botón "siguiente paso"; eso tampoco ensucia el log).
- **Ninguna máquina de estados**, a propósito: el contrato dice que las transiciones son reversibles y que documentar una es opcional. Inventar una aquí impediría deshacer un clic equivocado; lo que el backend garantiza es que **todo** cambio queda registrado.
- **`409 address_in_use`** (`conflict` + `errors.proposal`) cuando la propuesta **cambia** hacia una dirección ocupada. `takenAddresses` = los aparatos del proyecto + las propuestas de los conflictos que aún la mantienen (liberan al pasar a `closed`/`rejected`), **excluyendo siempre la propuesta del propio conflicto** — si no, nunca podría cambiarla. Un conflicto cuya dirección ya es la de un aparato (el fixture tiene uno) **sí** puede avanzar: solo se valida cuando la dirección cambia de verdad.

## Zonas (K7)

`GET /zones?project=` · `GET /zones/{id}` · `PATCH /zones/{id}/checks/{key}`.

El contrato está **cerrado** (`BACKEND-API-ZONES.md` §1) y la derivación vive en el modelo, así que este slice solo expone:

- `status`, `blockingReason` y `blockedBy` **derivados en cada lectura y cada escritura** (no hay columna `status`). `blockingReason`/`blockedBy` salen del **primer** check `failed` en orden de enum: es la causa raíz, el siguiente fallo suele ser la acción de seguimiento. Hay test del caso real (limpiar el primer fallo mueve el blocker al siguiente).
- Los 8 checks **siempre en orden**, aunque la BD no lo garantice.
- `PATCH` responde la zona **ya recomputada** (el llamante nunca adivina el efecto), fija `updatedAt = now` y `updatedBy` = la persona de oficina autenticada.
- `failed` y `na` **exigen `note`** (422 en `errors.note`): una zona bloqueada sin motivo es justo la señal inútil que este modelo evita.
- `key` desconocida → 422; zona inexistente → 404.

**`zonesNotReady` en `/projects`: NO se implementó.** §1.6 lo *recomienda*, pero el front lo calcula él mismo (`useZones()` + su propio aviso en Planning), así que añadirlo cambiaría una forma ya fijada sin que nadie la consuma. Queda como mejora opcional si algún día se quiere evitar que el front cargue todas las zonas.

## Dashboard (K7b)

`GET /dashboard` — un agregado, una llamada, porque es lo primero que abre oficina.

Dos de sus números **difieren a propósito del fixture del front**:

- `devicesThisWeek` cuenta los aparatos realmente registrados desde el lunes. El fixture lo tiene hardcodeado a 42.
- `techniciansScheduledToday` cuenta quién tiene asignación **hoy**, así que en fin de semana es 0 legítimamente (el planificador de la fixture es de lunes a viernes).

⚠️ **`travelling` nunca se emite.** El contrato lo lista y el fixture del front lo produce… por índice (`index === 2 ? 'travelling' : 'on_site'`), no por ningún dato. Nada en este dominio sabe si un técnico está de camino: eso es un *check-in* de campo (Veld), y hasta que exista, emitirlo sería inventar un estado que oficina luego se creería. Hoy `status` solo responde `off` u `on_site`.

## Documentos y informes (K8)

`GET /documents?project&q` · `GET /documents/{id}` · `GET /reports/exports` · `POST /reports` · descargas firmadas.

### Por qué las descargas van firmadas y **fuera** del grupo autenticado

Un `<a href>` del navegador **no puede mandar la cabecera Bearer**. Por eso el contrato pide URLs firmadas/temporales: la firma **es** la credencial, y caduca (30 min). Las rutas `documents/{id}/download` y `exports/{id}/download` están detrás de `signed` y **no** de `auth:sanctum` por eso mismo. Si el fichero no está en disco, el `url` sale `null` en lugar de un enlace que devuelve 404.

`url` solo se construye en `GET /documents/{id}` (el listado devuelve `url: null`, igual que la fixture del front): firmar N URLs para pintar una tabla sería tirar trabajo.

### `size`: se guardan bytes y se devuelve texto

`size_bytes` es lo que se almacena y el recurso lo formatea igual que la fixture del front (`4,2 MB`, `860 kB`, `38 MB`). Guardar el texto sería pérdida de información; hay test con esas tres cadenas byte a byte.

### Informes: **los cuatro tipos se generan como CSV por ahora** ⚠️

Desviación declarada: §4.9 describe la *presentación* (dossier y entrega en PDF, horas en XLSX) y esas plantillas no existen todavía. El fichero lleva **datos reales** y dice lo que es; un `.pdf` con contenido CSV sería una mentira.

| Tipo | Contenido | Estado |
|---|---|---|
| `ets` | lista de corrección ETS: un conflicto por línea con la dirección en juego y el último paso del flujo | ✅ **completo** (el informe por el que existe el módulo) |
| `dossier` | todos los aparatos del proyecto | ✅ datos reales, formato CSV |
| `delivery` | zonas con su estado + recuento de pruebas | ✅ datos reales, formato CSV |
| `hours` | ✅ **real desde V11.e** | Una línea por cierre de visita con sus minutos (y las horas en decimal con coma). Un cierre sin tiempo declarado aparece con las columnas vacías en vez de esconderse |

`ExportRecord` incluye además `downloadUrl` (extensión que §4.9 recomienda): el tipo del contrato no tiene enlace, así que la app solo podría decir que el informe existe, nunca abrirlo.

## Fichas funcionales y pruebas (K9)

`GET /projects/{code}/functions` · `PATCH /functions/{id}` · `GET /projects/{code}/tests` · `PATCH /tests/{id}`.

**Solo lo que consume el front.** §2.2 propone además `POST /projects/{code}/functions`, `POST /functions/{id}/approve` y `GET /functions/{id}/revisions`, y §3.2 `POST /projects/{code}/tests` y `POST /tests/{id}/evidence`. **Ninguno lo usa la app de oficina**, y construirlos ahora significaría inventar el flujo de edición (y con él la regla "editar siempre incrementa `version`"). Quedan pendientes y declarados.

`PATCH /functions/{id}` marca quién aprueba: aprobar fija `approvedBy` + `approvedAt`, y salir de `approved` los borra — "aprobado por X" en un borrador sería engañoso.

### Las tres reglas de una prueba que no pueden perderse

1. **`failed`, `blocked` y `not_applicable` exigen `note`** (422 en `errors.note`): un fallo sin motivo es inservible en la entrega.
2. **Un fallo crea (o vincula) una incidencia** sin perder contexto: la prueba ya sabe su función, su zona y la acción, así que solo faltaba la referencia. Si ya había `issueId`, **no se sobrescribe nunca**.
3. **Repetir una prueba corregida no borra el fallo anterior** → tabla `knx_test_executions`, append-only. Una sola columna `status` no puede cumplir eso (la segunda pasada borraría la evidencia de la primera), y esa evidencia es parte de lo que el cliente firma en la entrega. La fila de la prueba mantiene el estado actual; la tabla es el rastro detrás. **No se expone todavía** (el tipo del front no la tiene); el informe de entrega de K8 es su primer consumidor natural.

## Eventos en tiempo real (K10)

`GET /events` (`text/event-stream`), opcional según §6: sin él, el polling de 5–15 s sigue funcionando. Dos eventos, los dos sobre algo que *aparece*:

```
event: field.device.created
data: {"notificationId":"7","projectCode":"239870","address":"1.2.047","room":"Zaal 2.07"}
event: conflict.created
data: {"conflictId":"16","projectCode":"C1618","address":"1.1.116"}
```

Dos decisiones:

- **El cursor son dos "mayor id ya enviado"**, no timestamps: los ids son monótonos y no los reordena un desfase de reloj. Y una conexión nueva empieza **en el presente**, no en el principio: quien acaba de abrir la pestaña ya se trajo el estado actual y no quiere que le repitan el historial.
- **La conexión es acotada** (`config('knx.events.stream_seconds')`, 55 s por defecto) y cierra con un comentario; el navegador reconecta solo. Un worker de PHP retenido para siempre es un worker que no tiene el resto de oficina. En tests se pone a 0 para poder verificar los headers sin esperar.

**Autenticación: Bearer, no token en la query.** Un token en la URL acaba en logs de acceso y en el historial del navegador — y por eso el cliente **no puede usar el `EventSource` nativo**, que no manda cabeceras: tiene que leer el stream con `fetch()`.

## Handover al frontend

**`docs/Knx/handover-frontend.md`** es el documento para el equipo de frontend:
cómo apuntar los fronts al backend real, checklist de cambios en su código, catálogo
de endpoints con respuestas reales, los huecos declarados, la situación de Veld y las
trampas conocidas. Mantenedlo actualizado al cerrar cada slice.

## Veld — campo (V11)

### V11.a — sesión y trabajo del día

`GET /field/session` → `{id, name, initials, domain}` (a propósito **sin** `role` ni `email`: el móvil solo pinta un nombre, y darle el payload de oficina invitaría a la app a pedir cosas de oficina).

`GET /field/today` → un trabajo por asignación **de hoy**, con la zona que necesita atención (no la primera sin más), su estado derivado y su blocker, más `tasks`.

**Un solo login para las dos apps.** `POST /auth/login` acepta cuenta de oficina **o** de campo y responde el `Session` que le corresponde a cada perfil (el payload del móvil no lleva `role` ni `email`). El contrato de Veld no define endpoint de login propio, así que el compartido tiene que servir para los dos — y `refresh`/`logout` también.

**`GET /zones` es compartido** (el contrato de Veld dice explícitamente que usa el serializador de Kantoor): va en un grupo aparte con guard `any`, y sigue siendo legible por ambos perfiles. `PATCH /zones/{id}/checks/{key}` **no**: cambiar una comprobación es acción de oficina.

**Las dos apps están separadas por un guard** (`EnsureKnxApp:office|field`). Antes de esto, `auth:sanctum` solo decía "alguien entró", no *para qué*: un token de técnico podía leer **todo** el API de oficina (clientes, proyectos, conflictos, documentos) porque el middleware de tenant es un no-op con `organizations.enforce` en `false`. Responde **401** (no 403) porque para ese cliente es indistinguible de un token caducado, que es justo lo que ya sabe manejar.

**`room`/`zoneStatus`/`blockingReason`** describen la zona que necesita atención: la primera que no está lista, o la primera si todo está listo. Un trabajo es "ve a este espacio", así que apuntar a una zona lista en un proyecto con una bloqueada no serviría de nada.

**`tasks` es el único sitio de esta API que devuelve texto de presentación.** El front lo pinta tal cual (`job.tasks.join(' · ')`) y su fixture trae etiquetas en holandés, así que van en holandés — pero **derivadas de los datos** (aparatos aún por registrar, pruebas aún abiertas), no una lista fija. Si algún día quieren traducirlas, la forma a enviar es una lista de *kinds* y son dos líneas.

**Alcance = la asignación, no la empresa.** Un técnico solo ve los proyectos que planificación le puso **hoy**. `FieldTodayService::isAssignedToday()` es la única respuesta a esa pregunta y la usarán V11.b/c/d también.

### V11.b — proyecto y planos (`GET /field/projects/{code}`, `…/plans`)

Lo que la app cachea para registrar sin cobertura: salas (con `id`, que es lo que devuelve al registrar), cuadros, plantas y tipos de aparato.

**`deviceTypes` se deriva de los aparatos del proyecto.** No hay tabla de catálogo en el dominio y no se inventa: la lista son los tipos que ese proyecto ya usa. Consecuencia declarada: un proyecto sin aparatos devuelve `[]` y el selector de la app se queda sin opciones.

**`/plans` solo sirve dibujos** (`kind` ∈ `Plan`, `Schema`) **que tengan fichero en disco** y cuyo tipo se pueda nombrar. La app descarga el fichero y lo abre sin conexión, así que un plano que no puede abrir es peor que uno que no ve. El `mimeType` sale de la extensión (el documento de oficina no guarda tipo de contenido) y la `url` es una **firma de una semana** (`knx.plans.url_minutes`): los 30 minutos del visor de oficina caducarían antes de que el técnico llegue a la obra.

Columnas nuevas en `knx_documents`: `mime_type` y `pages`. El contrato de oficina no tiene ninguna de las dos (solo lista documentos); `pages = 1` está corroborado por la propia fixture de Veld para esos mismos documentos. El sembrador escribe **PDFs reales de una página** para los planos, rellenados hasta el tamaño que declara la fixture de oficina: sin fichero no hay nada que cachear, y así el tamaño que muestra la oficina sigue siendo cierto sobre el fichero que hay.

### V11.c — registro de aparatos (`POST /field/projects/{code}/devices`)

La app puede estar horas sin conexión, así que el mismo registro puede llegar dos veces o llegar cuando la dirección ya no está libre. Tres reglas:

1. **El `clientId` de la app decide la identidad.** Único **global** en `knx_devices` (un UUID hecho en el teléfono no colisiona entre proyectos). Reintento → `200` con el mismo aparato, nunca una fila nueva.
2. **Mismo `clientId` en otro proyecto → 422** en `clientId`. Devolver el aparato guardado le daría a un proyecto el aparato de otro.
3. **Dirección ocupada → `409 address_in_use`** con el aparato que molesta, **y además se escribe un conflicto** `duplicate_address` (critical) con la foto como evidencia y la línea `reported` en el histórico. Sin eso el técnico se queda con un error y la oficina sin enterarse de que ETS y la obra no coinciden. Un reintento de la misma tentativa no duplica el conflicto: para eso está `knx_conflicts.client_id`.

**Un aparato registrado crea DOS cosas**: el aparato (`source = field`, `acknowledged_at = null` → `isNew` en el dossier) y su notificación (el *evento* que confirma la bandeja de oficina). Es la misma pareja que ya usa el fixture; no se abre un camino paralelo.

`registered_at` = la hora de captura del técnico (`created_at` ya guarda la llegada), y `captured_at` la conserva explícitamente. La sala tiene que ser del proyecto (el id sale de `GET /field/projects/{code}`): adivinar por el nombre libre pondría aparatos en el sitio equivocado. Un cuadro que la oficina no tiene modelado **se crea sin nombre**: sabemos el código, no cómo lo llama ella.

**El `409` lleva el aparato dos veces a propósito**: en `existing` arriba del cuerpo (como pide el documento de Veld) y dentro de `errors` — porque el parser de la app construye `details` como `data.errors ?? payload`, y como el sobre **siempre** emite `errors`, si no estuviera dentro no lo encontraría nunca. Verificado contra su `http.ts`.

### V11.d — incidencias (`POST /field/projects/{code}/issues`)

Una incidencia **es** un conflicto: el Conflictencentrum ya es esa lista, y duplicar el concepto daría dos listas de trabajo que mantener sincronizadas. El `kind` de la app se mapea a los tipos que la oficina ya tiene (`damaged`, `missing` → `missing_device`, `plan_mismatch`) con la severidad que su fixture ya usa por tipo.

**El contexto no se pierde.** El contrato insiste en que una incidencia siempre lleva ubicación y, cuando se conoce, equipo y canal; los cuatro valores se conservan en `device_field` (`espacio · dirección · cuadro · canal`), que es exactamente la «descripción humana del registro que viene de campo» en el vocabulario de la fixture. `device_existing` dice qué hay registrado en esa dirección, o la frase de la fixture (`— niet gevonden ter plaatse`) cuando no hay nada. Cuando hay un aparato registrado ahí, el conflicto se ancla a él (`device_id`).

**⚠️ `kind: other` → `422` (`errors.kind`).** El tipo `other` de Veld **no existe** en el contrato de oficina: `ConflictType` son cuatro valores y `CONFLICT_TYPE_LABEL_KEY` es un `Record<ConflictType, …>` **sin fallback**, así que guardarlo daría `undefined` en el Conflictencentrum. Etiquetar el hallazgo como uno de los tres sería mentir sobre lo que vio el técnico; se rechaza con un mensaje que dice las alternativas. Es un hueco del contrato de oficina, no una incidencia que se pueda registrar — ver decisiones abiertas.

### V11.e — cierre de visita en 3 fases (`POST /field/projects/{code}/visits`)

`VELD-PLAN.md` §7.4 dejaba abierto "¿las 3 fases ya, o solo fin de visita?" y su propia recomendación era **las tres, «porque condiciona el modelo de datos»**. Se implementan las tres, y por eso van en **una tabla** (`knx_visits` con `type` ∈ `visit_end|partial|final`): son el mismo acto —alguien cerró algo desde obra— y la oficina las lee como **una sola historia** del proyecto.

`knx_visit_items` guarda las cuatro listas (`pending`, `reservations`, `verifiedFunctions`, `documents`) **como el técnico las escribió**: son etiquetas de texto libre, no referencias, y con `position` porque una lista leída en otro orden es otra lista para quien la escribió. Emparejarlas por su texto con fichas de función o documentos sería inventar un vínculo que la app nunca envió.

**La ubicación sigue la regla de V11.c**: si el nombre coincide con una sala del proyecto, manda la sala (`room_id`); si no, se guarda la palabra del técnico (`room_label`). Nunca las dos, para que no existan dos copias de un nombre que puedan contradecirse.

**Lo que un cierre NO hace, a propósito** — es la parte que tienta y que se decide no tomar:
- **No cambia el estado del proyecto.** Que un `final` sea la aceptación del cliente es una decisión de oficina sobre su propio registro, no algo que deba deducirse de un formulario.
- **No abre conflictos desde `pending`.** La lista es texto libre; convertirla en la lista de trabajo del Conflictencentrum pondría palabras en boca de la oficina.
- **No cierra pruebas de aceptación desde `verifiedFunctions`.** Son etiquetas, no resultados de prueba.

Las tres cosas están cubiertas por un test (`test_closing_a_visit_changes_nothing_else_in_the_office_records`) para que no se "mejoren" por accidente.

**`minutes` es la primera cosa de este dominio que registra tiempo**, y por eso **el informe `hours` dejó de ser un 422**: se genera desde los cierres, una línea por visita en orden de captura, con `hours` en decimal con coma (el CSV es para Excel en una máquina belga). Un cierre sin tiempo declarado **aparece con las columnas vacías**, no se esconde: la oficina necesita ver que hubo visita y que nadie apuntó cuánto duró.

**La oficina la lee** (`GET /projects/{code}/visits`, más reciente primero): la app de campo solo las envía y **nunca las lee** — su `CloseVisitPage` muestra su propia cola local, no datos del servidor. Ese endpoint es aditivo: el contrato de oficina no lo tenía y nadie está obligado a llamarlo.

### Cuentas de campo sembradas

| Email | Contraseña | Técnico |
|---|---|---|
| `jan.van.dyck@electrobertels.be` | `Veld123!` | Jan Van Dyck (JV) |
| `mira.claes@electrobertels.be` | `Veld123!` | Mira Claes (MC) |

**Solo dos de los cinco técnicos tienen cuenta, a propósito**: la regla de alcance no se puede demostrar ni con todos ni con ninguno, y Jan y Mira están en proyectos distintos.

### Bug del fixture corregido por el camino

El sembrador usaba `now()->setTime(9, 42)` para "hoy"; sembrando **antes de las 09:42** eso genera un timestamp **en el futuro**. No se notaba hasta que el reloj pasó de medianoche y el histórico de conflictos salió ordenado al revés (la entrada nueva quedaba *antes* de la de origen). Ahora un helper `at()` garantiza pasado, y los tests dejan de depender de la hora a la que se ejecuten.

## Entorno local

- BD propia: `electrobertels_knx` (aislada de los demás stacks).
- App servida en `http://localhost:8002` cuando se levante su stack.
- Mail a Mailpit (el `.env` de un checkout que apunta al tenant real trae `MAIL_MAILER=microsoft-graph` y entonces no llega ningún correo — ver `docs/qa-test-users.md`).

## Decisiones abiertas

1. **Alta de personas** desde Kantoor (arriba).
2. ¿El aviso de zona no preparada bloquea `PUT /planning` o solo advierte? (§1.6 recomienda warning estructurado; el front ya avisa en cliente).
3. ¿Fichas funcionales y pruebas se validan con negocio antes de exponerlas? (`ROADMAP.md` §9.2; el front ya las tiene hechas).
4. **`kind: other` de Veld** (V11.d): hoy responde `422` porque `ConflictType` de oficina no lo tiene y su etiqueta no tiene fallback. Las dos salidas son ampliar el contrato de oficina (un valor más en la unión + su entrada en el mapa de etiquetas + el texto en nl/en: tres líneas) o dejar que el técnico elija entre los tres tipos existentes. Mientras no se decida, el hueco está declarado y es reversible en una línea.
