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
| `hours` | — | ❌ **422**: nada en este dominio registra horas (Veld aún no las reporta). Se rechaza en vez de inventar números |

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

## Entorno local

- BD propia: `electrobertels_knx` (aislada de los demás stacks).
- App servida en `http://localhost:8002` cuando se levante su stack.
- Mail a Mailpit (el `.env` de un checkout que apunta al tenant real trae `MAIL_MAILER=microsoft-graph` y entonces no llega ningún correo — ver `docs/qa-test-users.md`).

## Decisiones abiertas

1. **Alta de personas** desde Kantoor (arriba).
2. ¿El aviso de zona no preparada bloquea `PUT /planning` o solo advierte? (§1.6 recomienda warning estructurado; el front ya avisa en cliente).
3. ¿Fichas funcionales y pruebas se validan con negocio antes de exponerlas? (`ROADMAP.md` §9.2; el front ya las tiene hechas).
