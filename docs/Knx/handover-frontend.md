# Handover backend → frontend (Kantoor y Veld)

> **Estado:** API de **Kantoor completa** (52 rutas entre `BACKEND-API.md` +
> `BACKEND-API-ZONES.md` + KNX-3). API de **Veld completa**: V11.a–e cerrados (sesión, trabajo de
> hoy, zonas, proyecto, planos, registro de aparatos, incidencias y cierre de visita en sus
> 3 fases). Solo quedan las fotos adicionales, que su propio documento marca como próximas.
> **Rama:** `electrobertels/knx-api` · **Tickets:** CLA-604 (oficina) y CLA-609 (campo)
> · **Doc del módulo:** `docs/Knx/knx-kantoor-backend.md`
> **Instrucciones paso a paso para vuestro código:** §7 en Kantoor / §5 en Veld, al final de
> vuestra copia de `docs/handover-backend.md`.
>
> **Contrato de forma (KNX-4):** `docs/Knx/openapi.yaml` en el repo del backend — OpenAPI 3.0.3
> de las **52 rutas** `api/v1/knx/*`, con `Modules/Knx/tests/Feature/OpenApiContractTest.php`
> que falla si el spec y las rutas reales se separan en cualquier dirección.

---

## 0. Resumen en una línea

**Los dos fronts pueden trabajar enteros contra el backend real**: el contrato de Kantoor
está completo, y el de Veld también salvo las fotos adicionales.

**Orden acordado — no hace falta coordinarlo conmigo:**

1. **Kantoor:** `login`/`logout` tipados + `getSession()` como guard al arrancar +
   `clearToken()` en el `401`. Los **tres juntos**, porque se sostienen entre sí (sin el
   tercero, un token caducado deja la app en bucle; sin el primero no hay forma de obtener
   token). Después cambiar a `VITE_API_MODE=real` y recorrer las pantallas en el orden de
   §7.
2. **Veld:** primero la **capa de token**, que hoy **no existe** (paso 1 de §5). Hasta
   entonces *todas* las llamadas al backend responden `401 unauthenticated`. Después ya
   podéis cambiar a `VITE_API_MODE=real` **el flujo entero**, cierre de visita incluido.
3. **`/events` (Kantoor) queda al final**: el polling actual funciona y el contrato lo
   permite, así que no debe ocupar camino crítico.

---

## 1. Cómo apuntar los fronts al backend real

```dotenv
# Kantoor y Veld usan la MISMA base: el dominio KNX es uno solo.
VITE_API_MODE=real
VITE_API_BASE_URL=http://localhost:8002/api/v1/knx
```

En desarrollo, con el proxy de Vite: `VITE_API_BASE_URL=/api/v1/knx` +
`VITE_API_PROXY_TARGET=http://localhost:8002`.

**Servidor de desarrollo del backend** (contenedor `knx-api-tmp`, BD
`electrobertels_knx` sembrada con la misma fixture que vuestros mocks):

| Qué | URL |
|---|---|
| API | `http://localhost:8002/api/v1/knx` |
| Mailpit (correo local) | `http://localhost:8027` |
| Kantoor (front) | `http://localhost:5173` |
| Veld (front) | `http://localhost:5174` |

**Credenciales sembradas** (solo local): `lien.smet@electrobertels.be` /
`Kantoor123!` — y `pieter.aerts@electrobertels.be` / `Kantoor123!`.

> El panel Filament exige MFA a `super_admin`, pero **la API no**: para la API basta
> el Bearer. El MFA solo aparece si entráis al backoffice por navegador.

### Autenticación

```
POST /auth/login    {email, password} → {access_token, expires_in, user:{…}}
POST /auth/refresh  (Bearer o {refresh_token}) → {access_token, expires_in}
POST /auth/logout   → 204
GET  /me/session    → Session   (401 si el token ya no vale)
```

- **Bearer token, no cookies.** Guardad `access_token` donde ya lo hace
  `src/api/auth.ts` (`localStorage['eb-token']`).
- **`expires_in` = 3600 s.** El refresh **rota** el token: el viejo deja de valer
  en la misma llamada.
- **Rate limit:** login 5/min, refresh 10/min. Si repetís pruebas muy seguidas
  veréis `429 rate_limited` — es correcto, no un bug.

### Errores: sobre uniforme

```json
{ "message": "…", "code": "validation_error", "errors": { "email": ["…"] } }
```

`code` ∈ `validation_error` · `unauthenticated` · `forbidden` · `not_found` ·
`conflict` · `address_in_use` · `rate_limited` · `server_error`.
`ApiError.code` es fiable; `errors` solo trae contenido en validaciones (422).

**Los payloads NO van envueltos en `{data: …}`**: son `[…]` o `{…}` planos.

---

## 2. Kantoor — todo listo

### 2.1 Lo que hay que tocar en el código

| # | Cambio | Por qué |
|---|---|---|
| 1 | **Añadir la llamada de login** | `KantoorApi` **no la tiene**: `src/api/auth.ts` solo guarda el token. Hay que llamar a `POST /auth/login` (y añadirlo al contrato tipado, con `refresh`/`logout`). |
| 2 | **`getSession()` al arrancar** | Es lo que valida el token guardado: un `401` significa "borra el token y manda a login". |
| 3 | **Manejar `ApiError.code`** | `unauthenticated` → limpiar token. `validation_error` → pintar `errors` por campo. `address_in_use` → el mensaje ya viene en `errors.proposal`. `rate_limited` → no reintentar en bucle. |
| 4 | **`/events` con `fetch()`, no `EventSource`** | `EventSource` **no puede mandar la cabecera Bearer**. Hay que leer el stream con `fetch()` + reader, o seguir con el polling actual (el contrato lo permite). |
| 5 | **Quitar la dependencia del orden del mock** | La lista de proyectos/funciones/pruebas viene por id (orden de inserción); la de conflictos, por severidad y fecha; la de actividad, de más reciente a más antiguo. Ya viene ordenado: no reordenéis por vuestro criterio o cambiará respecto al mock. |

**El detalle de cómo hacer cada uno (ficheros, esquemas de código y en qué orden) está en
§7 de vuestra copia.** Los puntos 1–3 van juntos en un solo PR: son los que se sostienen
entre sí.

### 2.2 Catálogo (lo que ya responde)

Todas las rutas son relativas a `VITE_API_BASE_URL` y todas exigen Bearer
(salvo login/refresh y las descargas firmadas).

```
POST   /auth/login                      POST /auth/refresh    POST /auth/logout
GET    /me/session

GET    /dashboard

GET    /projects?status=&q=             GET  /projects/{code}
GET    /projects/{code}/stats           GET  /projects/{code}/devices
GET    /projects/{code}/activity        GET  /projects/{code}/functions
GET    /projects/{code}/visits          (cierres de visita enviados desde Veld)
GET    /projects/{code}/tests

GET    /clients?q=                      GET  /clients/{id}

GET    /conflicts?status=&project=&q=   GET  /conflicts/{id}
PATCH  /conflicts/{id}

GET    /zones?project=                  GET  /zones/{id}
PATCH  /zones/{id}/checks/{key}

GET    /technicians                     GET  /planning?from=&to=
PUT    /planning                        DELETE /planning?technicianId=&date=

GET    /notifications                   POST /notifications/{id}/ack
POST   /notifications/ack-all

GET    /documents?project=&q=           GET  /documents/{id}
GET    /reports/exports                 POST /reports
GET    /events                          (text/event-stream)

PATCH  /functions/{id}                  PATCH /tests/{id}
```

### 2.3 Formas exactas (respuestas reales del backend)

`GET /me/session`
```json
{"id":"15","name":"Lien Smet","initials":"LS","role":"lead",
 "email":"lien.smet@electrobertels.be","domain":"kantoor.electrobertels.be"}
```

`GET /projects`
```json
[{"code":"C1618","name":"UV Campus · Gelijkvloers","clientId":"11","clientName":"UV Vastgoed",
  "city":"Heverlee","lead":"L. Smet","devicesPlanned":24,"devicesDone":17,"photos":38,
  "status":"busy","deadline":"2026-10-10"}, …]
```

`GET /projects/{code}/stats`
```json
{"devicesPlanned":24,"devicesDone":17,"openConflicts":2,"photos":38,"deadline":"2026-10-10"}
```

`GET /dashboard`
```json
{"kpis":{"activeProjects":4,"inDelivery":1,"devicesThisWeek":88,"openConflicts":4,
         "criticalConflicts":2,"techniciansScheduledToday":0,"techniciansTotal":5},
 "activeProjects":[…],"openConflicts":[…],
 "techniciansToday":[{"technician":{"id":"24","initials":"JV","name":"Jan Van Dyck"},
                      "projectCode":null,"projectName":null,"city":null,"status":"off"}, …]}
```

`GET /projects/{code}/devices` — primero los de campo, luego el plan ETS
```json
[{"id":"350","address":"1.1.101","type":"Aanwezigheidsdetector","room":"Gang gelijkvloers",
  "board":"E11","serial":"000E-991A","registeredBy":"M. Claes","source":"field",
  "isNew":false,"hasConflict":false}, …]
```
`isNew` = registro de campo sin confirmar · `hasConflict` = su dirección choca con un
conflicto `open`/`in_review` · `board: null` = cuadro desconocido · `room` es un nombre.

`GET /conflicts`
```json
[{"id":"16","severity":"critical","type":"duplicate_address","address":"1.1.116",
  "projectCode":"C1618","projectName":"UV Campus · Gelijkvloers",
  "deviceExisting":"Aanwezigheidsdetector · Vergaderzaal · rij 3",
  "deviceField":"KNX-drukknop 4-voudig · Gang gelijkvloers · rij 5",
  "reportedBy":"J. Van Dyck","reportedAt":"2026-09-26T07:42:00Z",
  "note":"Zit naast de deur, niet in de vergaderzaal.","photoUrl":null,
  "status":"open","proposal":"1.1.133","takenAddresses":["1.1.100","1.1.134", …],
  "history":[{"at":"2026-09-26T07:42:00Z","action":"reported"}]}, …]
```
`PATCH /conflicts/{id}` con `{status?, proposal?}` devuelve el conflicto actualizado y
**añade una línea al `history`** (es la lista de trabajo ETS). Con una propuesta ocupada
→ **409 `address_in_use`** con `errors.proposal`.

`GET /zones`
```json
[{"id":"5","projectCode":"C1618","projectName":"UV Campus · Gelijkvloers","name":"Vergaderzaal",
  "floor":"Gelijkvloers","status":"blocked",
  "blockingReason":"DALI-driver niet geleverd — kan verlichting niet testen",
  "blockedBy":"S. Wouters","nextReviewAt":"2026-09-28",
  "checks":[{"key":"installed","status":"passed","updatedAt":"2026-09-25T14:00:00Z",
             "updatedBy":"S. Wouters","note":null}, … los 8, siempre en orden]}, …]
```
`PATCH /zones/{id}/checks/{key}` `{status, note?}` → **la zona ya recomputada**.
`failed` y `na` **exigen `note`** (422 en `errors.note`).

`GET /notifications`
```json
[{"id":"7","address":"1.2.047","type":"Aanwezigheidsdetector","room":"Zaal 2.07",
  "board":"E21","boardName":"Verdeelbord E21","serial":"00C1-9A20",
  "projectCode":"239870","projectName":"Kantoor Hectaar · 2e verdieping",
  "reportedBy":"M. Claes","reportedAt":"2026-09-26T06:52:00Z","acked":false}, …]
```
`POST /notifications/{id}/ack` → 204 **y confirma también el aparato**: el `isNew` de
`/projects/{code}/devices` desaparece. Refrescade esa lista tras un ack.

`GET /reports/exports` / `POST /reports`

`GET /projects/{code}/visits` es **aditivo**: no estaba en `BACKEND-API.md` y nadie está
obligado a llamarlo. Existe porque el cierre de visita se firma desde el móvil y la
oficina es su única lectora.
```json
[{"id":"14","type":"ets","projectCode":"C1618","projectName":"UV Campus · Gelijkvloers",
  "createdAt":"2026-09-25T18:10:00Z","downloadUrl":"http://…/exports/14/download?expires=…&signature=…"}]
```
`POST /reports` `{projectCode, type}` → **202** con el registro. `type` ∈
`dossier` · `ets` · `delivery` · `hours`. **Los cuatro se generan** (ver §3 para el
formato y para el histórico de pruebas de aceptación, que sigue sin exponerse).

### 2.4 Un cambio en **vuestro propio** contrato (2026-09-27)

`ConflictType` tiene **cinco** valores ahora: se le añadió `other` para que un técnico
pueda reportar un hallazgo que no encaja en los otros cuatro. Lo hicimos nosotros, en
vuestro repo, y está en tres sitios:

| Fichero | Cambio |
|---|---|
| `src/api/types.ts` | `\| 'other'` en la unión `ConflictType` |
| `src/lib/constants.ts` | `other: 'other'` en `CONFLICT_TYPE_LABEL_KEY` |
| `src/i18n/translations.ts` | `other: 'Overig'` (nl) y `other: 'Otro'` (es) |

**No tenéis nada que hacer**: `tsc --noEmit` pasa limpio y el mapa es un
`Record<ConflictType, …>`, así que si algún día se añade otro tipo el compilador os lo
recuerda. Si preferís otra etiqueta (`Overig`, `Anders`, `Varia`…), es una línea — decidlo.
Sin este valor, el backend tenía que rechazar esas incidencias con un `422`, porque no
había etiqueta que pintar.

### 2.5 Las tres reglas que veréis en el flujo

1. **Confirmar una notificación confirma el aparato.** No hace falta una segunda llamada.
2. **Mover un conflicto escribe historia.** El `history` que devuelve `PATCH` ya trae la
   línea nueva: no hace falta recargar ni optimizar el estado a mano.
3. **`takenAddresses` nunca incluye la propuesta del propio conflicto** — así que
   validar el campo contra esa lista es correcto (si incluyera la suya, nunca podría
   cambiarla).

---

## 3. Huecos declarados — no los persigáis, están decididos

| # | Hueco | Qué veréis | Motivo |
|---|---|---|---|
| 1 | **Informes como CSV**, no PDF/XLSX | `downloadUrl` apunta a un `.csv` | §4.9 describe la presentación (PDF/XLSX) y esas plantillas no existen. El de `ets` **está completo** (lista de corrección ETS); `dossier` y `delivery` llevan datos reales en CSV |
| 2 | Histórico de `PATCH /tests` | **no expuesto** | El tipo del front no tiene campo para él; las ejecuciones se guardan append-only pero no se devuelven |
| 3 | `techniciansToday[].status` | solo `off` u `on_site` | `travelling` no se emite: vuestro fixture lo inventa por índice (`index === 2`). Llegará cuando Veld reporte llegadas |
| 4 | `projects/{code}/activity` | sin entradas `photos_uploaded` | Nada modela fotos como eventos (el proyecto solo lleva un contador) |
| 5 | `GET /projects` no trae `zonesNotReady` | — | §1.6 lo *recomienda*, pero vosotros lo calculáis con `useZones()`; añadirlo cambiaría una forma que nadie consume. **Se puede añadir si lo preferís** (evitaría cargar todas las zonas) |
| 6 | `PATCH /tests/{id}` guarda histórico de ejecuciones | no se expone | El tipo del front no lo tiene; el informe de entrega es su primer consumidor natural |

Pendientes **no consumidos por el front** (§2.2/§3.2 de `BACKEND-API-ZONES.md`):
crear funciones, aprobar, revisiones, crear pruebas, adjuntar evidencia.

---

## 4. Veld

### 4.1 Qué funciona YA

**V11.a–e están cerrados: el contrato de Veld está completo** (salvo las fotos adicionales, que su propio documento marca como próximas):

- **`GET /zones?project=CODE`** → **el mismo serializador que Kantoor** (idéntico, sin
  duplicar contrato).
- **`GET /field/session`** → `{id, name, initials, domain}` (sin `role` ni `email`, a
  propósito).
- **`GET /field/today`** → un trabajo por asignación de hoy, con `room`/`zoneStatus`/
  `blockingReason` de la zona que **necesita atención** y `tasks` derivadas.
- **`GET /field/projects/{code}`** → salas (con `id`), cuadros, plantas y tipos de aparato.
- **`GET /field/projects/{code}/plans`** → los dibujos del proyecto con una URL firmada de
  **una semana**, para que la app los cachee y los abra sin cobertura.
- **`POST /field/projects/{code}/devices`** → idempotente por `clientId`; `201` (o `200` en un
  reintento), `409 address_in_use` si la dirección ya está tomada.
- **`POST /field/projects/{code}/issues`** → idempotente por `clientId`; `201` con
  `{id, clientId}`.
- **`POST /field/projects/{code}/visits`** → idempotente por `clientId`; `201` con
  `{id, clientId}`. Las tres fases (`visit_end`, `partial`, `final`), y `minutes` es lo
  que por fin da datos al informe de horas de la oficina.
- `POST /auth/login` para el token (el mismo endpoint que Kantoor).

**Cuentas de campo sembradas** (solo local): `jan.van.dyck@electrobertels.be` y
`mira.claes@electrobertels.be`, ambas con `Veld123!`. **Solo dos de los cinco técnicos
tienen cuenta, a propósito**: la regla de alcance ("solo los proyectos donde estás
asignado hoy") no se puede demostrar ni con todos ni con ninguno, y Jan y Mira están en
proyectos distintos.

⚠️ **Las dos apps están separadas:** un token de oficina (`knx_office`) **no** vale en
`/field/*` y viceversa — responde `401`. Es deliberado.

⚠️ **`tasks` es el único campo de esta API con texto de presentación** (holandés, como en
vuestra fixture) porque vuestra pantalla lo pinta tal cual. Va **derivado de los datos**
(aparatos por registrar, pruebas abiertas), no es una lista fija. Si queréis traducirlo,
decidlo y paso a enviar *task kinds*.

### 4.2 Qué NO existe todavía

Solo las fotos adicionales, que vuestro propio documento ya marca como "próximamente":

| Endpoint | Estado |
|---|---|
| `POST /field/projects/{code}/photos` | ⏳ vuestro documento lo marca así; hoy la foto va dentro de cada registro/incidencia |

Ya **no queda nada bloqueado** por una decisión de producto: las 3 fases del cierre se
implementaron siguiendo la recomendación del propio `VELD-PLAN.md` §7.4 ("las 3, porque
condiciona el modelo de datos").

### 4.3 Decisiones ya tomadas

**Decidido e implementado** en V11.a–e:

1. **Auth de campo:** se reutiliza `knx_employees` con `user_id` y el rol `knx_field`.
   `POST /auth/login` sirve para **las dos apps** y devuelve el `Session` que corresponde
   al perfil de la cuenta. **Guard por app**: un token de oficina no vale en `/field/*` ni
   al revés — `401` deliberado.
2. **Alcance:** un técnico solo ve y toca los proyectos donde planificación le puso **hoy**
   (`FieldTodayService::isAssignedToday()`). Código desconocido → `404`; proyecto que
   existe pero no es suyo hoy → `403`.
3. **Idempotencia:** el `clientId` de la app es la identidad de la operación, en
   `devices`, `issues` **y `visits`**. Un reintento devuelve **el mismo resultado** (`200`
   con el mismo `id`), nunca una fila nueva. El `clientId` es único **global**: si lo
   reutilizáis para otro proyecto, la respuesta es `422` en `clientId` (devolveros el
   aparato del otro proyecto os daría datos ajenos).
4. **`409 address_in_use`:** el aparato que ocupa la dirección viaja en `existing`, arriba
   del cuerpo **y dentro de `errors`** (vuestro cliente lee `details = data.errors ?? payload`,
   así que dentro de `errors` es donde lo encuentra).
5. **Planos:** los dibujos (`kind` `Plan`/`Schema`) cuyo fichero existe, con `mimeType`
   derivado de la extensión y `pages` registrado por el documento. Un plano que no se puede
   abrir no se ofrece: la app cachea el fichero, no la fila.
6. **`deviceTypes` se deriva de los aparatos del proyecto** (no hay catálogo en el dominio).
   Consecuencia: un proyecto sin aparatos devuelve `[]` y el selector se queda sin opciones.

7. **Las 3 fases del cierre están implementadas** (§4.4), siguiendo la recomendación del
   propio `VELD-PLAN.md` §7.4. **Un cierre no cambia nada más**: no cierra el proyecto, no
   abre conflictos desde `pending` ni marca pruebas desde `verifiedFunctions`. Son
   etiquetas de texto libre, y la oficina decide qué hace con ellas — si esperabais que un
   `final` cerrara el proyecto, decidlo y lo hablamos antes de inventarlo.

### 4.4 El cierre de visita, en detalle

Las tres fases del contrato (`visit_end`, `partial`, `final`) se guardan y la oficina las
lee como **una sola historia** del proyecto (`GET /projects/{code}/visits`, más reciente
primero). Las cuatro listas se conservan **en el orden en que las escribisteis** y **tal
como las enviasteis**: son etiquetas, no referencias, y emparejarlas por su texto con
fichas de función o documentos sería inventar un vínculo que el técnico nunca hizo.

- **`minutes` alimenta el informe de horas de la oficina**, que hasta ahora respondía `422`.
  Un cierre sin tiempo declarado aparece en ese informe **con las columnas vacías**, no se
  esconde.
- **La ubicación**: si el nombre coincide con una sala del proyecto manda la sala; si no, se
  guarda vuestra palabra (`Zolder boven de keuken`). Nunca las dos.
- **`type` es obligatorio y son los tres del contrato**; un cuarto valor es `422 errors.type`.
- Las listas se aceptan **para cualquier tipo** aunque la tabla de vuestro documento diga
  cuáles aplican: prefiero guardar una lista inesperada antes que perder lo que escribió un
  técnico. Vuestro `type` es lo que le dice a la oficina cómo leerlas.

## 5. Trampas conocidas

1. **Re-sembrar la BD invalida los tokens.** El seeder de demo recrea las cuentas, así
   que un `/me/session` que devuelve `401` después de que yo siembre **no es un bug del
   cliente**: hay que volver a hacer login.
2. **No hay `{data: …}`.** Si veis `.data` en vuestro código para el modo real, es un
   resto del mock.
3. **`size` llega como texto formateado** (`"4,2 MB"`, `"860 kB"`), no como número: el
   backend formatea los bytes guardados. Pintadlo tal cual.
4. **`id` de cliente/proyecto/conflicto son strings** aunque por dentro sean enteros.
5. **La descarga firmada no lleva token**: el `url` ya incluye `signature=` y caduca a
   los 30 min. No le añadáis la cabecera Authorization ni la guardéis en caché larga.
6. **`GET /events` mantiene la conexión 55 s** y luego cierra; el navegador reconecta
   solo. No lo tratéis como un error.
7. **El `409` de `POST /field/projects/{code}/devices` trae el aparato que ocupa la
   dirección en `existing`**: arriba del cuerpo y también dentro de `errors`. Vuestro
   `http.ts` construye `details` como `data.errors ?? payload`, así que leedlo de `details`
   como hacéis ahora: funciona. **Un `409` no se reintenta** (es una respuesta final, no un
   fallo de red): reintentarlo no crea nada nuevo, pero llena la lista de trabajo de oficina.
8. **Los dos `POST` son idempotentes por `clientId`.** Reintentad con el mismo id sin miedo:
   la respuesta es `200` con el mismo `id`, nunca una fila nueva. Guardad el `clientId` en la
   cola offline y **no** generéis uno nuevo al reintentar, o crearéis un aparato duplicado.
9. **La foto va como data URL en `photoDataUrl`** y se aceptan las dos codificaciones que
   puede producir la app: `data:image/png;base64,…` (una foto de cámara por canvas) y
   `data:image/svg+xml;utf8,…` (lo que genera vuestra propia fixture). Límite 5 MB ya
   decodificada; formatos aceptados: png, jpeg, webp, gif y svg.
10. **Si vuestro reintento llega mientras la original se está procesando, la respuesta es
    `200` con la misma fila, nunca un `500`.** El backend resuelve esa carrera en el índice
    único. Un `500` en el reintento de un `POST` idempotente es un bug: reportadlo.
11. **El `url` de un plano es una firma de una semana** (la del visor de oficina son 30
    minutos). Está pensada para descargar el fichero una vez y abrirlo sin conexión: podéis
    cachearlo.

---

## 6. Cómo comprobar que quedó bien

### 6.1 Kantoor (`VITE_API_MODE=real`)

1. **Overzicht** debe mostrar 4 proyectos activos, 4 conflictos
   abiertos (2 críticos) y 5 técnicos.
2. **Projecten** debe listar los 5 códigos de la fixture (`C1618`, `239870`, `232146`,
   `240512`, `228104`) — el mismo conjunto que en modo mock.
3. **Conflictencentrum**: al mover un conflicto a `ETS-lijst`, el detalle debe mostrar la
   línea nueva en el histórico **sin recargar**.
4. **Zonas**: `PATCH` de un check a `failed` sin nota debe dar error de campo, y con nota
   debe cambiar `blockingReason` y `blockedBy` a vuestro usuario.
5. **Bandeja**: `ack` de una notificación debe quitar el `isNew` del aparato en el
   dossier.
6. **Plannen**: subir una revisión nueva (`isCurrent: false` en la anterior) debe verse
   como tal.
7. **Informes**: los **cuatro** tipos se generan, incluido `hours` (antes respondía `422`
   porque nada registraba tiempo). `hours` sale de los cierres de visita: una línea por
   cierre, con `hours` en decimal con coma. Un proyecto sin cierres da un CSV con solo la
   cabecera — que es la respuesta honesta.

### 6.2 Veld (en cuanto tengáis la capa de token)

1. Con `jan.van.dyck@electrobertels.be / Veld123!`, **Hoy** debe mostrar **C1618 ·
   Vergaderzaal · `blocked`** con el motivo del DALI y dos tareas.
2. Con `mira.claes@electrobertels.be / Veld123!` debe mostrar **239870** y **no** C1618.
   Los dos técnicos están en proyectos distintos **a propósito**: es la comprobación de
   alcance.
3. Un token de **oficina** contra `/field/*` → `401`. Un token de **campo** contra
   `/projects` → `401`. Es deliberado.
4. `GET /zones?project=C1618` con el token de campo → `200` (la lectura es de las dos
   apps). El `PATCH` de un check con ese mismo token → `401` (escribir checks es de
   oficina).
5. Tras cada siembra de la BD que haga yo, **volved a hacer login**: las cuentas se
   recrean y vuestro token deja de valer.
6. **Proyecto** (`GET /field/projects/C1618`): la lista de salas debe venir en el orden en
   que se modelaron (Inkomhal, Gang gelijkvloers, Vergaderzaal…) y `floors` con las plantas
   de esas salas. `deviceTypes` son los tipos que **ese proyecto ya usa**.
7. **Planos** (`…/plans`): el Wayfinding debe venir con `mimeType: application/pdf`,
   `sizeBytes: 4404019`, `pages: 1` y una `url` que descarga un PDF de verdad. Los documentos
   que no son dibujos (el export ETS, el informe de inspección, el zip de fotos) **no**
   aparecen: la lista es de planos, no un explorador de documentos.
8. **Registrar un aparato** (`POST …/devices`) con una dirección libre → `201` con la forma
   `FieldDevice`; el mismo `clientId` otra vez → `200` con el mismo `id`; una dirección ya
   ocupada (p. ej. `1.1.111` en C1618) → `409 address_in_use` con `existing`.
9. **Y comprobad el otro lado**: cada registro vuestro tiene que aparecer en el dossier de
   oficina marcado como nuevo (`isNew`), y un `409` tiene que aparecer en el
   Conflictencentrum como `duplicate_address` **con vuestra foto**. Si la oficina no lo ve,
   el aparato está a medias y quiero saberlo.
10. **Incidencia** (`POST …/issues`) → `201` con `{id, clientId}`; en el Conflictencentrum
    debe conservar espacio, equipo y canal en `deviceField`. Los cuatro `kind` funcionan,
    `other` incluido (§3).
11. **Alcance, también al escribir**: un `POST` sobre un proyecto que no es vuestro hoy →
    `403`; sobre un código que no existe → `404`.
12. **Cierre de visita** (`POST …/visits`): `201` con `{id, clientId}`; el mismo `clientId`
    otra vez → `200` con el mismo `id`. Cerrad un `final` con `verifiedFunctions` y
    `documents` y comprobad que **el proyecto no cambia de estado** ni aparecen conflictos
    nuevos: un cierre registra lo que firmasteis, no decide nada por la oficina.

Si algo no encaja con lo que esperáis, decidme el payload que esperabais y lo ajusto:
**el contrato manda, y `src/api/types.ts` manda sobre los `.md` cuando difieran**.
