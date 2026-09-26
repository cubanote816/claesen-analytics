# Handover backend → frontend (Kantoor y Veld)

> **Estado:** API de **Kantoor completa** (36 rutas, contrato de `BACKEND-API.md` +
> `BACKEND-API-ZONES.md`). API de **Veld pendiente** (ver §4).
> **Rama:** `electrobertels/knx-api` · **Ticket:** CLA-604 · **Doc del módulo:** `docs/Knx/knx-kantoor-backend.md`

---

## 0. Resumen en una línea

Kantoor puede trabajar **entera** contra el backend real hoy mismo; Veld puede
cablear **`GET /zones`** y espera al slice `/field/*` para el resto.

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
```json
[{"id":"14","type":"ets","projectCode":"C1618","projectName":"UV Campus · Gelijkvloers",
  "createdAt":"2026-09-25T18:10:00Z","downloadUrl":"http://…/exports/14/download?expires=…&signature=…"}]
```
`POST /reports` `{projectCode, type}` → **202** con el registro. `type` ∈
`dossier` · `ets` · `delivery` · **`hours` → 422** (ver §3).

### 2.4 Las tres reglas que veréis en el flujo

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
| 2 | `POST /reports` con `type=hours` | **422** | Nada en el dominio registra horas (Veld aún no las reporta): se rechaza antes que inventar números |
| 3 | `techniciansToday[].status` | solo `off` u `on_site` | `travelling` no se emite: vuestro fixture lo inventa por índice (`index === 2`). Llegará cuando Veld reporte llegadas |
| 4 | `projects/{code}/activity` | sin entradas `photos_uploaded` | Nada modela fotos como eventos (el proyecto solo lleva un contador) |
| 5 | `GET /projects` no trae `zonesNotReady` | — | §1.6 lo *recomienda*, pero vosotros lo calculáis con `useZones()`; añadirlo cambiaría una forma que nadie consume. **Se puede añadir si lo preferís** (evitaría cargar todas las zonas) |
| 6 | `PATCH /tests/{id}` guarda histórico de ejecuciones | no se expone | El tipo del front no lo tiene; el informe de entrega es su primer consumidor natural |

Pendientes **no consumidos por el front** (§2.2/§3.2 de `BACKEND-API-ZONES.md`):
crear funciones, aprobar, revisiones, crear pruebas, adjuntar evidencia.

---

## 4. Veld

### 4.1 Qué funciona YA

- **`GET /zones?project=CODE`** → **el mismo serializador que Kantoor** (idéntico, sin
  duplicar contrato). Veld puede cablear el aviso de zona no preparada hoy.
- `POST /auth/login` sirve para obtener el token, **pero `/field/session` no existe**: la
  sesión de campo es parte del slice pendiente.

### 4.2 Qué NO existe todavía

Ninguno de los endpoints de `veld/docs/BACKEND-API-VELD.md` está implementado:
`/field/session`, `/field/today`, `/field/projects/{code}`,
`POST /field/projects/{code}/devices`, `POST /field/projects/{code}/visits`,
`/field/projects/{code}/plans`, `POST /field/projects/{code}/issues`.

Estaba declarado como fuera de alcance del ticket de K0 ("la app Veld y sus endpoints
van en su propio esfuerzo"), y sigue siéndolo: es un slice propio, no un añadido.

### 4.3 Qué necesita el backend para ese slice (propuesta, sin decidir)

1. **Auth de campo.** `GET /field/session` no es `/me/session`: el técnico entra al
   proyecto donde está asignado **hoy**. Decidir si reutiliza `knx_employees` con
   `user_id` (la tabla ya lo soporta: los 5 técnicos sembrados **no tienen cuenta** hoy)
   y qué rol de app (`knx_field`, ya creado).
2. **Idempotencia.** `clientId` en `POST /devices` y en `POST /issues`. La propuesta del
   documento (`devices.client_id` + `field_registration_attempts`) encaja con el módulo:
   `knx_devices` ya tiene `acknowledged_at` y el flujo notificación↔aparato ya está hecho,
   así que **`POST /devices` puede reutilizarlo** en vez de crear un camino paralelo.
3. **`409 address_in_use`** ya está resuelto: es la misma excepción que usa
   `PATCH /conflicts/{id}`. El aparato existente se devuelve con el mismo criterio.
4. **Planos offline.** `GET /field/projects/{code}/plans` sale de `knx_documents`
   (que ya tiene `revision`, `is_current`, `size_bytes`) + URLs firmadas con caché larga
   — el mecanismo de descarga firmada **ya existe** (`documents/{id}/download`).
5. **Cierre de visita en 3 fases** (roadmap §3.1) necesita decidir el modelo antes
   (fin de visita / entrega parcial / aceptación final), hoy solo hay `knx_acceptance_tests`.

**Decisión pendiente de negocio, no técnica:** ¿este slice entra ahora o después? Con
`/field/*` cerrado, el sistema queda completo de punta a punta.

---

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

---

## 6. Cómo comprobar que quedó bien

1. Con `VITE_API_MODE=real`, **Overzicht** debe mostrar 4 proyectos activos, 4 conflictos
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

Si algo no encaja con lo que esperáis, decidme el payload que esperabais y lo ajusto:
**el contrato manda, y `src/api/types.ts` manda sobre los `.md` cuando difieran**.
