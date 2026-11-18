# Módulo Knx — Reporte funcional completo

> **Tipo:** reporte de diagnóstico de solo lectura. No autoriza cambios de código.
> **Rama auditada:** `electrobertels/knx-api` (`HEAD` = `759a426`, upstream sincronizado; 132 commits por delante de `origin/main`, 5 por detrás).
> **Fecha del reporte:** 2026-10-07.
> **Árbol de trabajo al momento del análisis:** limpio salvo `M package-lock.json` (drift de lockfile, sin relación con el módulo).
> **Fuentes:** `Modules/Knx/**` (195 archivos PHP), `docs/Knx/knx-kantoor-backend.md`, `docs/Knx/handover-frontend.md`, `docs/Knx/openapi.yaml`, `odd/tasks/knx-*.md`, `handoff.md`, y los repos frontend `electro-bertels-kantoor` / `electro-bertels-veld`.
> **Reportes previos que este documento NO reemplaza:** `docs/Knx/knx-kantoor-backend.md` es la doc viva del módulo; `docs/Knx/handover-frontend.md` es el contrato con los fronts. Este reporte es una vista de conjunto para planificar el desarrollo.

---

## 1. Resumen ejecutivo

El módulo `Knx` es una **API de gestión de instalaciones KNX para Electro Bertels**, entregada como **un solo dominio Laravel con dos aplicaciones consumidoras**: **Kantoor** (oficina, React) y **Veld** (campo, React). No es un backoffice Filament: es una API REST `/api/v1/knx/*` con Sanctum, pensada para dos SPAs.

**Números del módulo (medidos en este análisis):**

| Métrica | Valor |
|---|---|
| Rutas registradas en `Modules/Knx/routes/api.php` | **52** (33 GET, 12 POST, 4 PATCH, 2 PUT, 1 DELETE) |
| Operaciones documentadas en `docs/Knx/openapi.yaml` | **52**, sobre **44 paths** → contrato **simétrico y verificado por test** |
| Modelos / tablas | **22 / 22** |
| Controllers | **23** (6 en `Http/Controllers/Field/`; los otros 17 en la raíz, de los cuales 2 son de auth compartida y 1 es la bandeja de campo que sirve a oficina) |
| Services | 21 |
| Resources (serialización) | 28 |
| Archivos de test de feature | **27** (no existe suite `Unit` en el módulo) |
| Migraciones / factories | 24 / 19 |
| Locales | `nl`, `en` (8 archivos de traducción cada uno) |
| Comandos Artisan | **1** (`knx:import-worklist`) |
| Jobs en cola | **1** (`GenerateKnxExportJob`) |
| Repos implicados | 3 (api + kantoor + veld) |

**Estado:** funcionalmente **completo para el contrato declarado** — los slices K0–K10 (oficina), V11.a–e (campo) y KNX-3 (planos/marcadores/cuadros/empleados) están cerrados, y KNX-4 convirtió el contrato en artefacto versionado. Lo que falta está **explícitamente diferido**, no a medias: ver §10.

**Los tres bloqueantes reales para seguir** (§11): el **GO técnico pendiente** de KNX-3/KNX-4, la **revisión nativa (RDD) bloqueada por el relay**, y el **programa multiempresa P7** que impide dar de alta un usuario real de Bertels en producción.

---

## 2. Qué es el dominio Knx

Electro Bertels instala y pone en servicio sistemas KNX (domótica de edificio). El módulo digitaliza el ciclo completo del proyecto de instalación:

1. **Oficina (Kantoor)** — modelo el proyecto y su cliente, planifica técnicos por día, y mantiene el **Conflictencentrum**: la lista de trabajo donde se reconcilia lo que dice el plano ETS contra lo que realmente hay en obra. Gobierna además la **preparación de zonas** (8 checks), las **fichas funcionales y pruebas de aceptación** del cliente, y los **informes**.
2. **Campo (Veld)** — el técnico ve solo **los trabajos asignados para hoy**, cachea el proyecto y los planos para trabajar sin señal, y escribe desde obra: **registro de aparatos**, **incidencias** y **cierre de visita** en tres fases.
3. **Cierre administrativo** — informes CSV (`ets`, `dossier`, `delivery`, `hours`) que alimentan la entrega al cliente.

**Lenguaje del negocio:** los proyectos se direccionan por **`code`** natural (`C1618`), nunca por id numérico. El idioma de UI es neerlandés; el módulo tiene traducciones `nl`/`en`.

---

## 3. Arquitectura: un dominio, dos apps, un tenant

- **Multiempresa por organización, no por base de datos.** El dominio Knx pertenece **solo a Electro Bertels**: `knx.organization_slug = 'electro-bertels'` (`Modules/Knx/config/config.php:20`). Toda fila lleva `organization_id` vía el trait `BelongsToKnxTenant`, y `KnxTenant::organization()` **lanza `RuntimeException` si no encuentra la organización** en lugar de caer a Claesen.
- **Todas las rutas llevan `organization:electro-bertels`** (`routes/api.php:56,64,113`). Ver §6.2 para el detalle importante: ese middleware es *no-op* hoy; la frontera real vive en el servicio de auth.
- **Los dos perfiles se separan por un guard de aplicación**, no por abilities de Sanctum: `EnsureKnxApp:office`, `EnsureKnxApp:field`, `EnsureKnxApp:any` (`Modules/Knx/Http/Middleware/EnsureKnxApp.php:31`). Resuelve el `KnxEmployee` y lo deja en `$request->attributes` como `knx_employee`; cuando ninguna identidad encaja responde **401, no 403** — deliberadamente indistinguible de un token expirado.
- **Raíz de rutas:** `RouteServiceProvider` monta el módulo en `api` con prefijo `api` → `POST /api/v1/knx/auth/login`, etc. (`Modules/Knx/Providers/RouteServiceProvider.php:17`).
- **Sin envoltorio de payload.** Las respuestas exitosas son la forma cruda del recurso; el `ApiErrorEnvelope` solo actúa sobre errores y **solo** dentro de `api/v1/knx/*`.

---

## 4. Dominio Oficina (Kantoor)

### 4.1 Entidades y esquema (22 tablas)

Todas las tablas usan prefijo `knx_`, salvo las dos de visitas. `BelongsToKnxTenant` rellena `organization_id` en `create()` y **no es un global scope a propósito** (el aislamiento se hace en la ruta + en el servicio de auth).

| Modelo | Tabla | Propósito | Reglas no obvias |
|---|---|---|---|
| `KnxClient` | `knx_clients` | Cliente de la instalación | Sin clave natural; solo `organization_id` |
| `KnxEmployee` | `knx_employees` | Persona del dominio (oficina o campo) | `user_id` nullable y unique; `kind` = oficina/campo; `shortName()` **derivado, no almacenado** |
| `KnxProject` | `knx_projects` | Proyecto de instalación | `unique(organization_id, code)`; contadores `devices_planned`/`devices_done`/`photos` denormalizados |
| `KnxProjectRoom` | `knx_project_rooms` | Habitación / espacio | Sin trait de tenant; se acota vía el proyecto |
| `KnxBoard` | `knx_boards` | Cuadro de distribución | `code` es etiqueta corta; **`slug` es la identidad externa** y es el `boardId` del marcador; `unique(project_id,{code,slug})` |
| `KnxBoardModule` | `knx_board_modules` | Módulo físico del cuadro | Identidad = **`slot`** (posición 1-based del worklist); `unique(board_id,slot)` |
| `KnxBoardLink` | `knx_board_links` | El hecho `(canal, habitación, objeto, GA)` | `position` preserva el orden del worklist; `role` por defecto `unknown` |
| `KnxDevice` | `knx_devices` | Aparato registrado | **NO existe `unique(project_id,address)`** — la dirección duplicada *es* el conflicto; `client_id` globally unique (idempotencia de campo); `source` = `ets`/`field`; `acknowledged_at` alimenta `isNew` |
| `KnxPlanningAssignment` | `knx_planning_assignments` | Una asignación técnico/día | `unique(employee_id,date)` es lo que hace idempotente al `PUT` |
| `KnxConflict` | `knx_conflicts` | Conflicto ETS vs obra | 8 estados: `open→in_review→ets_listed→applied→downloaded→verified→closed` + `rejected`; `client_id` nullable unique para reintentos de campo |
| `KnxConflictLog` | `knx_conflict_logs` | Historial append-only del conflicto | **Sin timestamps Laravel**; `at` es tiempo de dominio; `address` es la dirección vigente *en ese momento* |
| `KnxNotification` | `knx_notifications` | Evento de campo para la bandeja de oficina | `board_id` nullable es significativo (cuadro que la oficina nunca modeló); `acked` = `acknowledged_at !== null` |
| `KnxDocument` | `knx_documents` | Plano / documento del proyecto | `size_bytes` crudo → `size` legible; `client_id` unique **por organización**; `is_current` + `supersedes`; `mime_type`/`pages` derivados en servidor |
| `KnxExport` | `knx_exports` | Informe generado | Tipos `ets`/`dossier`/`delivery`/`hours`; estados `queued`/`ready`/`failed` |
| `KnxZone` | `knx_zones` | Zona de preparación de obra | **Sin columna `status`** — se deriva en cada lectura y cada escritura |
| `KnxZoneCheck` | `knx_zone_checks` | Uno de los 8 checks | Sin timestamps Laravel; `unique(zone_id,key)`; el orden lo fija `config('knx.zone_check_keys')` |
| `KnxFunctionSpec` | `knx_function_specs` | Comportamiento acordado de una habitación | Columnas JSON; `version` default 1; estados `draft/proposed/approved/changed` |
| `KnxAcceptanceTest` | `knx_acceptance_tests` | Prueba de aceptación colgada de una función | `issue_id` se genera en el primer fallo y **nunca se sobrescribe**; `physical_check` |
| `KnxTestExecution` | `knx_test_executions` | Historial de corridas append-only | Nunca se actualiza ni se borra: evidencia de cada corrida |
| `KnxPlanMarker` | `knx_plan_markers` | Cuadro ubicado sobre una revisión/página | **`board_id` es un string externo, no FK**; `unique(organization_id,document_id,board_id)`; `nx`/`ny` `decimal(6,5)` como fracción |
| `KnxVisit` | `knx_visits` | Cierre de visita enviado desde obra | Una sola tabla con `type` = `visit_end`/`partial`/`final`; `client_id` unique; `room_id` XOR `room_label`; `minutes` nullable |
| `KnxVisitItem` | `knx_visit_items` | Una línea del cierre | Etiqueta libre + `position`; 4 tipos: `pending`/`reservation`/`verified_function`/`document` |

### 4.2 Rutas de oficina

Grupo en `routes/api.php:113`, middleware `auth:sanctum` + `organization:electro-bertels` + `EnsureKnxApp:office`. `{code}` está restringido a `[A-Za-z0-9._-]+`.

**Autenticación** (`Auth/AuthController`, sin guard de app, con throttle):

| Ruta | Método del controller | Qué hace | Notas |
|---|---|---|---|
| `POST /auth/login` | `login` | Valida email/password, devuelve `{access_token, expires_in, user}` | `throttle:5,1`; **un solo login para las dos apps**; toda falla da el mismo 422 |
| `POST /auth/refresh` | `refresh` | Rota el token presentado (body o Bearer) y borra el viejo | `throttle:10,1`; **re-verifica la cuenta**, así un usuario desactivado no puede mintear token nuevo |
| `POST /auth/logout` | `logout` | Revoca el token presentado → `204` | Resuelve el token de la request, no de `currentAccessToken()` |

**Sesión y tablero:**

| Ruta | Método | Qué hace | Notas |
|---|---|---|---|
| `GET /me/session` | `SessionController@show` | Devuelve la `Session` de oficina | `401` si el token sobrevive al acceso revocado |
| `GET /dashboard` | `DashboardController@show` | Agregado de una sola llamada: `kpis`, proyectos, conflictos abiertos, `techniciansToday` | El estado de técnico solo emite `off`/`on_site` — **nunca `travelling`** |

**Clientes y proyectos:**

| Ruta | Método | Qué hace | Notas |
|---|---|---|---|
| `GET /clients` | `ClientController@index` | Lista clientes, filtro `q` sobre nombre/ciudad/contacto/email | Orden por id |
| `GET /clients/{id}` | `ClientController@show` | Detalle con sus proyectos | `404` vía envelope |
| `POST /clients` | `ClientController@store` | Crea cliente; solo `name` obligatorio → `201` | `organization_id` no se acepta nunca (lo pone el trait) |
| `GET /projects` | `ProjectController@index` | Lista proyectos; filtros `status` (incluye `all`) y `q` | Orden de inserción |
| `POST /projects` | `ProjectController@store` | Crea proyecto + habitaciones en una transacción → `201` | `clientId`/`leadEmployeeId` acotados al tenant; `code` con regex + unique |
| `GET /projects/{code}` | `ProjectController@show` | Detalle del proyecto | `404` si el código no existe |
| `GET /projects/{code}/stats` | `ProjectController@stats` | Estadísticas del proyecto | |
| `GET /projects/{code}/devices` | `ProjectController@devices` | **Dossier**: primero los de campo, luego ETS, por dirección; con `isNew` y `hasConflict` | Las direcciones en conflicto se cargan una sola vez |
| `GET /projects/{code}/activity` | `ProjectController@activity` | Feed de actividad **derivado** (conflictos, aparatos, documentos), límite 50 | `photos_uploaded` **nunca se emite** (ver §10) |
| `GET /projects/{code}/visits` | `ProjectController@visits` | Oficina lee los cierres de visita, más nuevos primero | Orden por `captured_at` desc |

**Cuadros, planos y marcadores:**

| Ruta | Método | Qué hace | Notas |
|---|---|---|---|
| `GET /projects/{code}/boards` | `BoardController@index` | Cuadros con módulos, canales y enlaces | `boards: []` es un estado válido |
| `POST /documents` | `DocumentController@store` | Subida multipart de plano; deriva tamaño/MIME/páginas y guarda en disco `local` | >50 MB → `413`; idempotente por `clientId` → `200`; proyecto o `supersedes` desconocido → `404` |
| `GET /documents` | `DocumentController@index` | Lista documentos; filtros `project`, `q` | `url: null` en listas |
| `GET /documents/{id}` | `DocumentController@show` | Detalle con URL firmada de **30 min** | `url: null` si falta el archivo |
| `GET /documents/{id}/download` | `DocumentController@download` | Streamea el archivo | **Firmada, fuera de `auth:sanctum`** |
| `GET /projects/{code}/plans/{documentId}/markers` | `PlanMarkerController@show` | Marcadores de una página (default 1) | El documento se resuelve *dentro* del proyecto |
| `PUT /projects/{code}/plans/{documentId}/markers` | `PlanMarkerController@update` | Reemplaza el conjunto de la página; base `carryOverFrom` | `boardId` `distinct`, máx 500; `revision` se acepta pero **se ignora**; documentId desalineado → `422` |

**Empleados, planificación y conflictos:**

| Ruta | Método | Qué hace | Notas |
|---|---|---|---|
| `GET /employees` | `EmployeeController@index` | Personas activas, `role` default `office` | Alimenta el selector `leadEmployeeId`; **excluye inactivos** |
| `GET /technicians` | `PlanningController@technicians` | Empleados de campo activos | Solo campo |
| `GET /planning` | `PlanningController@index` | Asignaciones en `[from,to]` | Ambos obligatorios, `to >= from` |
| `PUT /planning` | `PlanningController@store` | Asigna técnico a un día | Idempotente por (técnico, fecha); id de oficina → `422 technicianId`; **el aviso de zonas no preparadas §1.6 NO está implementado** |
| `DELETE /planning` | `PlanningController@destroy` | Quita la asignación del día | Día vacío → `204` |
| `GET /conflicts` | `ConflictController@index` | Lista del Conflictencentrum | `status=active` = `open`+`in_review`; orden por severidad y luego fecha |
| `GET /conflicts/{id}` | `ConflictController@show` | Detalle con historial append-only | `takenAddresses` excluye la propuesta propia |
| `PATCH /conflicts/{id}` | `ConflictController@update` | Cambia estado y/o propuesta | Cada cambio de estado escribe un log; propuesta tomada → `409 address_in_use` con `errors.proposal` |

**Zonas** (lectura compartida con campo; escritura solo oficina):

| Ruta | Método | Qué hace | Notas |
|---|---|---|---|
| `GET /zones` | `ZoneController@index` | Zonas; filtro `project` | Grupo `EnsureKnxApp:any` — **legible por oficina y campo** |
| `GET /zones/{id}` | `ZoneController@show` | Zona con sus 8 checks y estado derivado | |
| `PATCH /zones/{id}/checks/{key}` | `ZoneController@updateCheck` | Fija un check y devuelve la zona recomputada | **Solo oficina**; `failed`/`na` **exigen `note`** (`422`); key/zona desconocidos → `422`/`404` |

**Bandeja de campo (rutas de oficina, pese al controller de campo):**

| Ruta | Método | Qué hace | Notas |
|---|---|---|---|
| `GET /notifications` | `FieldNotificationController@index` | Bandeja, incluye las ya confirmadas | Orden por `reported_at` desc |
| `POST /notifications/{id}/ack` | `FieldNotificationController@ack` | Confirma; **también confirma el aparato** que la originó | Devuelve **`204`**, aunque `openapi.yaml` documenta `200` (ver §13) |
| `POST /notifications/ack-all` | `FieldNotificationController@ackAll` | Confirma todas las pendientes | `204`; idempotente |

**Informes, eventos, fichas y pruebas:**

| Ruta | Método | Qué hace | Notas |
|---|---|---|---|
| `GET /reports/exports` | `ReportController@index` | Historial de informes | `created_at` desc |
| `POST /reports` | `ReportController@store` | Encola un informe → `202` | Tipos `ets`/`dossier`/`delivery`/`hours`; despacha el job |
| `GET /exports/{id}/download` | `ReportController@download` | Streamea el informe listo | **Firmada**; `404` salvo `ready` + archivo presente |
| `GET /events` | `EventStreamController@stream` | **SSE** `text/event-stream` | Solo Bearer (no query param); tope `knx.events.stream_seconds` |
| `GET /projects/{code}/functions` | `FunctionSpecController@index` | Fichas funcionales | `sole()` → `404` |
| `PATCH /functions/{id}` | `FunctionSpecController@update` | Cambia estado; aprobar sella `approvedBy`/`At`, desaprobar los limpia | Solo lo que el front consume |
| `GET /projects/{code}/tests` | `AcceptanceTestController@index` | Pruebas de aceptación | |
| `PATCH /tests/{id}` | `AcceptanceTestController@update` | Registra un resultado | Reglas delegadas al servicio |

### 4.3 Lógica de negocio (servicios de oficina)

- **`KnxAuthService`** — decide el acceso a cada app. Dos condiciones simultáneas: cuenta activa con rol de app **y** fila de persona activa (`:122`). Comprueba `organization_id` **incondicionalmente, sin excepción para `super_admin`**. Ejecuta un `Hash::check` contra un hash dummy cuando el usuario no existe, para no crear un oráculo de tiempos (`:58`). Todas las fallas → una sola `ValidationException` sobre `email`.
- **`ConflictService`** — `list` con orden severidad→fecha; `takenAddressesFor`/`ForList` combinan aparatos del proyecto y propuestas no cerradas, excluyendo la propia (`:37,125`); `update` valida la propuesta **solo cuando cambia** (`:155`) y escribe **un log por cambio real de estado**.
- **`DashboardService`** — cuenta `activeProjects`, `inDelivery`, `devicesThisWeek` (registrados desde el lunes), `openConflicts`, `criticalConflicts`, `techniciansScheduledToday`, `techniciansTotal`, agrupando por empleado.
- **`DocumentService`** — `create` con replay por `clientId` (`:138`); `supersedes` acotado al proyecto (`:170`); la escritura crea la fila y guarda el archivo en `knx/plans/{code}/{id}-{name}` dentro de una transacción (`:185`); MIME con finfo (`:209`), páginas con smalot si es PDF y 1 si no (`:219`); usa `IdempotentWrite`.
- **`PlanMarkerService`** — `replace` borra el conjunto de la página **y cualquier colocación del mismo cuadro en otras páginas**, y reinserta; `effectiveMarkers` fusiona la base `carryOverFrom` con la request (gana la request por cuadro, `:112`); la geometría se guarda verbatim.
- **`PlanningService`** — `assign` es `updateOrCreate` sobre (empleado, fecha); `unassign` borra y no falla si no existía.
- **`ProjectActivityService`** — mezcla entradas de conflicto, aparato y documento, ordena desc y toma 50; los aparatos se agrupan por (persona, día) con `count`.
- **`ZoneService`** — valida que la key exista, **exige `note` para `failed`/`na`**, limpia la nota en el resto, y recomputa con `$zone->refresh()`.
- **`AcceptanceTestService`** — exige nota para `failed`/`blocked`/`not_applicable` (`:31`); en `failed` genera `issue_id` solo si está vacío; **añade una `KnxTestExecution` por cada estado registrado** (`:29,72`).
- **`ExportService`** — **los cuatro tipos salen como CSV** (punto y coma / CRLF, coma decimal): `ets` es el worklist de corrección, `dossier`, `delivery` (zonas + pruebas) y `hours` (desde visitas, incluidas filas sin minutos).
- **`EventStreamService`** — el cursor son **dos `max(id)`** (notificación y conflicto), no timestamps; `currentCursor` arranca en el presente; emite `field.device.created` y `conflict.created`.
- **`FieldNotificationService`** — confirmar una notificación confirma también el aparato enlazado (limpia `isNew`); idempotente; `ackAll` usa dos sentencias masivas.
- **`BoardWorklistImportService`** — importación idempotente: identidad de cuadro = `Str::slug` del nombre de caja, identidad de módulo = `slot` (parte cuando el canal retrocede); reemplaza módulos en reimportación; quita el BOM UTF-8 al leer CSV (`:216`).
- **`ClientService` / `ProjectService`** — escrituras finas; `ProjectService` envuelve proyecto + habitaciones en `DB::transaction` y hace `refresh()` para recoger los defaults de columna.

---

## 5. Dominio Campo (Veld)

### 5.1 Rutas de campo

Grupo en `routes/api.php:64`, middleware `auth:sanctum` + `organization:electro-bertels` + `EnsureKnxApp:field`.

| Ruta | Método | Qué hace | Notas |
|---|---|---|---|
| `GET /field/session` | `FieldSessionController@show` | Devuelve la sesión de campo: id, nombre, iniciales y el host de Veld | **Sin rol ni email a propósito**; lee `knx_employee` de los atributos de la request |
| `GET /field/today` | `FieldTodayController@index` | Los trabajos del día, modelados para caché offline: por asignación, proyecto, zona que necesita atención, `tasks` derivadas, contadores, `openConflicts`, `visitClosed` | **Dos consultas agregadas agrupadas** para todo el día, no una por trabajo; el alcance es `whereDate('date', today)` sobre el empleado |
| `GET /field/projects/{code}` | `FieldProjectController@show` | El proyecto que la app cachea para registrar sin conexión: habitaciones, cuadros, plantas, `deviceTypes`, contadores | `404` si el código no existe vs **`403` si no está planificado hoy** — distintos a propósito, para que un typo no parezca un problema de permisos; `deviceTypes` se deriva de los aparatos del propio proyecto y **no hay tabla de catálogo**, así que un proyecto sin aparatos responde `[]` |
| `GET /field/projects/{code}/plans` | `FieldProjectController@plans` | Los planos del proyecto, en orden | Solo `kind ∈ {Plan, Schema}` **y** con MIME resoluble y archivo presente en disco `local`; la `url` es **firmada** y vale **1 semana** por defecto, porque la app la cachea para obra |
| `GET /field/projects/{code}/devices` | `FieldProjectController@devices` | Lista completa de aparatos (registros de campo + plan ETS) | Orden campo-primero y luego por dirección, **ordenado en PHP** para evitar el `ORDER BY FIELD()` específico de MySQL |
| `POST /field/projects/{code}/devices` | `FieldDeviceController@store` | Registra un aparato desde obra | **Idempotente por `clientId`**: `201` al crear, `200` al repetir; el alcance se resuelve **antes** de validar; `projectCode` del body debe igualar al de la URL o `422`; colisión de dirección → **`409 address_in_use`** con el aparato ocupante; acepta `photoDataUrl` |
| `POST /field/projects/{code}/issues` | `FieldIssueController@store` | Reporta una incidencia → un `KnxConflict` | **Idempotente por `clientId`**; `kind ∈ {damaged, missing, plan_mismatch, other}` y el servicio mapea `kind`→`type` de oficina; una incidencia **es** un conflicto, no una entidad nueva; acepta foto |
| `POST /field/projects/{code}/visits` | `FieldVisitController@store` | Cierra una visita en una de 3 fases con 4 listas de texto libre | **Idempotente por `clientId`**; `type ∈ {visit_end, partial, final}`; cada lista `max:50` ítems de `max:200` caracteres; `minutes` es el **primer dato de control horario del dominio**; no muta nada más |

**Frontera importante:** `GET /area/zones` es compartida (`EnsureKnxApp:any`), pero **la bandeja de notificaciones vive en el grupo de oficina**: un token de campo **no puede leer su propio inbox** (`routes/api.php:155-158`). El campo solo *escribe* notificaciones indirectamente al registrar aparatos.

### 5.2 Lógica de negocio (servicios de campo)

- **`FieldTodayService`** — el día del técnico y **el único chequeo de alcance** `isAssignedToday()` (`:101`) que usan todos los endpoints `/field/*`. Deriva `zoneNeedingAttention` (la primera zona no preparada, o la primera) y `tasks` (registrar aparatos si `devices_done < devices_planned`; probar la iluminación si hay pruebas en `pending`/`failed`/`blocked`).
- **`FieldProjectService`** — autoriza y carga el proyecto para campo: `resolveAuthorized()` busca por `code`, precarga cliente, habitaciones (orden por id) y cuadros, y **lanza `AuthorizationException` cuando no está asignado hoy** (`:41-57`).
- **`FieldDeviceService`** — registrar/reconocer un aparato. Invariantes: (1) `clientId` decide la identidad y un replay devuelve la fila existente; (2) el **mismo `clientId` en otro proyecto es `422 client_id_reused`**, nunca un replay; (3) dirección en uso → **no crea**, registra un conflicto `duplicate_address` de severidad `critical` y su log `reported` (con la foto), y devuelve la colisión para que el controller emita el `409`. La habitación debe pertenecer al proyecto; el cuadro se crea con `firstOrCreate` **sin nombre** si la oficina no lo modeló. Aparato y notificación se crean juntos en una transacción (`:138`). Un reintento del mismo intento **no** escribe un segundo conflicto de dirección.
- **`FieldIssueService`** — reportar una incidencia como conflicto de oficina. `KIND_TO_TYPE` mapea los 4 kinds; `other` es severidad `warning` **a propósito** (`:46`). Ancla al aparato registrado por dirección cuando lo encuentra; cuando no, escribe `'— niet gevonden ter plaatse'` (neerlandés porque es **dato**, no UI, `:60`). La dirección vacía está permitida (reporte de solo espacio).
- **`FieldVisitService`** — cierra la visita. Resuelve la habitación por nombre exacto case-insensitive; si no hay, **conserva la palabra del técnico en `room_label`, nunca ambas** (`:120-124`). Guarda las cuatro listas ordenadas con `position`, recortando y saltando etiquetas vacías. Nada más se muta.
- **`FieldPhotoService`** — decodifica y guarda un `photoDataUrl` para ambas escrituras. Acepta forma `;base64` y percent-encoded; extensiones png/jpeg/jpg/webp/gif/svg+xml; **máximo 5 MB decodificados** (`:25`). El archivo se nombra con el `clientId`, así un reintento no puede duplicarlo (`:76`). Ruta `knx/devices/{clientId}.{ext}` en disco `local`.

---

## 6. Transversalidad

### 6.1 Autenticación y sesión

- **Un solo login para las dos apps.** `KnxAuthService::profile()` decide qué forma de `Session` se devuelve: `KIND_FIELD` → `FieldSessionResource` (sin rol ni email), el resto → `SessionResource`.
- **Sanctum con vidas cortas.** `expires_in` = `knx.token_expiry_minutes` (default **60 min**). El refresh **rota** el token (borra el viejo) y **vuelve a chequear la cuenta**, así que desactivar o quitar el rol a alguien invalida su capacidad de renovar. No existe almacén separado de refresh tokens (desviación documentada).
- **⚠️ Hallazgo:** `issueToken()` **siempre** nombra el token `'knx:kantoor'` con ability `['knx:office']`, **incluso para logins de campo** (`AuthController.php:131-134`). Y `EnsureKnxApp` decide a partir del **rol de Spatie del usuario**, no de las abilities. Conclusión: **las abilities del token son decorativas hoy**; no hay nada que las haga cumplir. Quien lea `knx:office` en un token no debe inferir separación de apps.
- **Rechazo de login deliberadamente uniforme:** una sola falla para cuenta inexistente / contraseña incorrecta / inactiva / sin rol / sin fila de persona. El mensaje **no nombra ninguna de las dos apps** (CLA-627) y un `Hash::check` dummy evita el oráculo de tiempos.
- **`EnsureKnxApp` responde 401, no 403**, cuando ninguna identidad encaja. Esto es un guard de aplicación necesario: sin él, un token de técnico podría leer toda la API de oficina, porque el middleware de tenant es no-op (ver siguiente punto).

### 6.2 Aislamiento por organización — **el detalle que hay que conocer**

Existen **tres** capas, y solo una está activa hoy:

1. **Middleware de ruta** `organization:electro-bertels` — presente en las tres grupos (`routes/api.php:56,64,113`)… pero `RequireOrganization` está **condicionado a `config('organizations.enforce')` y es no-op con el valor por defecto** (`Modules/Core/Http/Middleware/RequireOrganization.php:34-36`). El enforcement del programa multiempresa (P6) todavía no está activado.
2. **Relleno del trait** `BelongsToKnxTenant` — auto-rellena `organization_id` en `create()` y lanza si Bertels no existe. **No es global scope**, deliberadamente: no filtra lecturas.
3. **`KnxAuthService::authorizeFor()`** — comprueba `organization_id` **incondicionalmente** (`:127-131`). **Esta es la frontera real que sostiene el aislamiento hoy.**

Implicación para quien siga desarrollando: **cualquier endpoint nuevo queda aislado solo por pasar por el servicio de auth**, y cualquier consulta nueva que se haga fuera de la ruta autenticada (un comando, un job, un default de Filament) **no tiene red de seguridad de tenant**. No asumir que el middleware la cubre.

### 6.3 Idempotencia de escrituras

`IdempotentWrite::run(callable $write, callable $recover)` (`Modules/Knx/Support/IdempotentWrite.php`): ejecuta la escritura; captura `UniqueConstraintViolationException`; hace la **lectura de recuperación fuera de la transacción fallida** (si no, REPEATABLE READ replicaría el snapshot del perdedor); devuelve la fila ganadora; si la lectura no encuentra nada, **relanza** — porque entonces fue un fallo real, no una carrera perdida.

**Cuatro usos:** `FieldDeviceService:85`, `FieldIssueService:115`, `FieldVisitService:49` y el `DocumentService:73` de oficina.

**Contrato en HTTP:** los tres POST de campo devuelven **`201` al crear / `200` al repetir**, decidido por `wasRecentlyCreated` (no por la pre-lectura). El mismo `clientId` en otro proyecto es `422 client_id_reused`, nunca un replay. La garantía duradera es el índice `unique` sobre `client_id` de cada tabla.

### 6.4 Envelope de errores

`ApiErrorEnvelope` actúa **solo** dentro de `api/v1/knx/*` (`:63`). Cuerpo: `message`, `code`, `errors` (objeto, **siempre presente**) y `context` para las subclases de `KnxApiException`.

Códigos: `bad_request`, `unauthenticated`, `forbidden`, `not_found`, `conflict`, `validation_error`, `payload_too_large`, `rate_limited`, `server_error`. Los **5xx nunca filtran internos** (`:130-134` devuelve "Something went wrong.").

`AddressInUseException` tiene **dos formas a propósito**: el `PATCH /conflicts/{id}` de oficina lee `errors.proposal`; el POST de campo recibe el aparato ocupante **tanto en `errors` como en el `existing` de nivel superior**, porque el cliente de campo construye sus `details` desde `errors ?? payload`.

### 6.5 i18n

Existen `lang/{nl,en}` con 8 archivos cada uno: `auth`, `documents`, `field`, `planning`, `projects`, `reports`, `tests`, `zones`.

**⚠️ Deuda con impacto real:** `SetLocaleFromHeader` **no está aplicado a ninguna ruta de Knx** (sí lo está en FieldOps y Website). Un `Accept-Language: en` **no cambia nada** y el archivo `en` del módulo **hoy no es alcanzable por HTTP**. Todo cliente ve neerlandés.

No traducido a propósito: las `tasks` de `FieldTodayService` son cadenas de display neerlandesas **derivadas de datos**, y `'— niet gevonden ter plaatse'` es **dato** de oficina, no una cadena de request.

### 6.6 Jobs, SSE y notificaciones

- **Un solo job:** `GenerateKnxExportJob` (`ShouldQueue`). Escribe el CSV en `knx/exports/…` del disco `local`, pasa el `KnxExport` de `queued` a `ready`, o a `failed` vía `failed()` con `Log::error`. Se despacha **solo** desde `ReportController@store` de oficina.
- **No existe directorio `Events/`** en el módulo, ni broadcasting, ni observers. La fila `KnxNotification` **es** el evento de dominio, y se crea dentro de la transacción de registro de aparato.
- **SSE es pull-based:** `GET /events` usa polling cada `knx.events.poll_seconds` (2 s) acotado por `knx.events.stream_seconds` (55 s) para no retener un worker PHP; el navegador reconecta solo. El cursor son dos `max(id)`, no timestamps — por eso no se pierden eventos por desfase de reloj.
- **Limitación de UX:** SSE exige **Bearer**, no query param, así que el `EventSource` nativo del navegador **no sirve**; el front necesita un cliente SSE con headers.

### 6.7 Configuración (`Modules/Knx/config/config.php`)

| Clave | Default | Qué gobierna |
|---|---|---|
| `organization_slug` | `electro-bertels` | La frontera de tenant del dominio |
| `demo_seed_confirm` | `false` | `KnxDemoSeeder` **reemplaza todos los datos KNX del tenant**; se niega a correr fuera de tests sin `KNX_DEMO_SEED_CONFIRM=true` |
| `token_expiry_minutes` | `60` | Vida del access token |
| `kantoor_domain` | `kantoor.electrobertels.be` | Fallback del host mostrado en el sidebar de oficina |
| `veld_domain` | `veld.electrobertels.be` | Host mostrado en la pantalla de carga de campo |
| `events.stream_seconds` / `events.poll_seconds` | `55` / `2` | Tope y cadencia del SSE |
| `plans.url_minutes` | `10080` (1 semana) | Validez de la URL firmada del plano — una semana **a propósito**: el técnico lo descarga una vez y lo abre sin conexión |
| `zone_check_keys` | 8 keys en orden | El orden importa: `blockingReason`/`blockedBy` salen del **primer** check fallido |

### 6.8 Comando Artisan

**`knx:import-worklist {directory} {code}`** — lee `linking-worklist.csv` y `group-addresses.csv` de `directory`, resuelve el proyecto por `code` y delega en `BoardWorklistImportService::import`, imprimiendo el conteo de cuadros/módulos/canales/enlaces. Falla con código no-cero si falta un CSV o el proyecto. Es el **único** comando registrado del módulo.

---

## 7. Contrato de forma: OpenAPI + test anti-drift

El contrato **no es un mensaje ni una convención**: es un artefacto versionado.

- **`docs/Knx/openapi.yaml`** — OpenAPI 3.0.3, **44 paths / 52 operaciones**.
- **`Modules/Knx/tests/Feature/OpenApiContractTest.php`** — chequeo **bidireccional**: cada path documentado existe como ruta registrada, y cada ruta registrada está documentada (`:28` y `:52`). El drift en cualquier dirección **rompe el test**.
- Mismo patrón ya usado en `docs/api/website-v1-openapi.yaml` + `Modules/Website/tests/Feature/OpenApiContractTest.php`.
- **Verificado en este análisis:** 52 rutas registradas (`grep -cE "Route::(get|post|put|patch|delete)\("`) = 52 operaciones documentadas. **Simetría exacta.**
- El spec se mantiene **a mano**: no hay generador instalado. Consecuencia práctica: **agregar una ruta obliga a editar el YAML en el mismo commit**, o el test falla.

---

## 8. Los tres repositorios

| Repo | Ruta local | Remoto | Rama actual | Rol |
|---|---|---|---|---|
| **API (este)** | `~/claesen/electrobertels-knx-api` | — | `electrobertels/knx-api` | `Modules/Knx` + Core compartido; API `/api/v1/knx/*` |
| **Kantoor** | `~/knx/electro-bertels-kantoor` | `github.com/cubanote816/electro-bertels-kantoor` | `feat/kantoor-plano-viewer` | SPA de oficina (React/Vite + Playwright e2e) |
| **Veld** | `~/knx/electro-bertels-veld` | `github.com/cubanote816/electro-bertels-veld` | `main` | SPA de campo (React/Vite + Playwright e2e) |

**Documentos de contrato que los fronts mantienen como fuente:** `electro-bertels-veld/docs/BACKEND-API-VELD.md` y `electro-bertels-kantoor/docs/BACKEND-API.md` (más `BACKEND-API-ZONES.md`). Los comentarios del backend los citan como el vocabulario de referencia — **ojo:** esos docs usan `company_id` donde el esquema usa `organization_id`; el módulo conserva el vocabulario del doc en la API.

Ambos fronts tienen su propia carpeta `odd/` con tareas y han corrido su propia revisión nativa. **Kantoor tiene trabajo activo** (commits del 2026-10-07, viewer de planos) y **dos bloqueantes declarados** en su `odd/`; conviene leerlos antes de tocar el contrato de planos/marcadores.

---

## 9. Qué está implementado y verificado

### 9.1 Cierres declarados

| Bloque | Alcance | Evidencia |
|---|---|---|
| **K0–K10** | Oficina: sesión, dossier, bandeja, planificación, conflictos, zonas, dashboard, documentos e informes, fichas y pruebas, SSE | `docs/Knx/knx-kantoor-backend.md:70-88` — todos ✅ |
| **V11.a–e** | Campo: sesión y trabajo del día, proyecto y planos, registro de aparatos, incidencias, cierre de visita en 3 fases | `docs/Knx/knx-kantoor-backend.md:80-84` |
| **KNX-3** | `POST /documents` con metadatos derivados + `supersedes` + idempotencia; `code` de proyecto con la restricción de lectura; `GET /employees?role=`; marcadores por documento/revisión con `carryOverFrom`; `GET /projects/{code}/boards` + `knx:import-worklist` | Linear KNX-3 **Done**; commits `c90e6a6`, `f6b3144`, `5af2b3c`, `694969e`, `b8232dd`, corrección `da7ed94` |
| **KNX-4** | Contrato OpenAPI + test anti-drift bidireccional | Linear KNX-4 **Done**; commit `ce6f9d9` |
| **CLA-627** | El rechazo de login deja de nombrar una de las dos apps; el test pasa a comparar el payload completo entre los 6 motivos | commit `7b321ae`; `Modules/Knx/tests` 183 passed |
| **CLA-630** | Fin del trackeo de assets de Filament + endurecimiento del deploy | commits `ffeeacd`, `ef08440` |

### 9.2 Evidencia de verificación

- **Suite del módulo (declarada por el propio proyecto):** `Modules/Knx/tests` **234 passed / 1 skipped / 0 failed** (1352 aserciones) al cierre de KNX-4; **232 passed / 1 skipped / 0 failed** (1299 aserciones) tras la corrección `da7ed94` de KNX-3. → Ver §9.3 para la corrida independiente de este análisis.
- **Revisión nativa (RDD) de KNX-3:** U2 (`f6b3144`) y U3 (`5af2b3c`) **approved** y quemados. **U1 (`c90e6a6`) pasó reviewer y refuter**, y el refuter encontró **3 defectos CRITICAL reales** introducidos en ese commit: `putFileAs` podía devolver `false` y commitear un documento con `path` vacío; un `clientId` vacío esquivaba el replay y chocaba con el índice único (500); y el índice único de `client_id` era global mientras la relectura es por organización. Los tres se corrigieron en `da7ed94` con 3 tests de regresión.
- **Verificación HTTP real en `:8002`:** login de oficina; `POST /documents` de un PDF real de 3 páginas y 2 MB → `201` con `pages:3`/`mimeType`/URL firmada; reintento `200`; `supersedes` deja el documento anterior `isCurrent=false`; `PUT`/`GET` de 5 marcadores; `GET /projects/000026/boards` → **5 cuadros, 13 módulos, 52 canales, 160 enlaces** (los números medidos del worklist real); `GET /employees`; y `code = "C 1699"` → `422`.
- **Aislamiento de la suite:** los tests corren en un stack Docker aislado (`knx-api-tmp`, puerto 8002), sin tocar otros checkouts.

### 9.3 Evidencia medida directamente en este análisis

Todo lo siguiente se midió en el checkout auditado, no se citó de la documentación:

| Medición | Comando | Resultado |
|---|---|---|
| Rutas registradas | `grep -cE "Route::(get\|post\|put\|patch\|delete)\(" Modules/Knx/routes/api.php` | **52** (33 GET, 12 POST, 4 PATCH, 2 PUT, 1 DELETE) |
| Operaciones documentadas | parseo de `docs/Knx/openapi.yaml` | **52** sobre **44** paths, con la misma distribución por verbo |
| Simetría ruta ↔ contrato | comparación de ambos | **exacta, sin asimetría** |
| Archivos PHP del módulo | `find Modules/Knx -name '*.php'` | 195 |
| Modelos / migraciones / services | conteo en disco | 22 / 24 / 21 |
| Controllers / resources / factories | conteo en disco | 23 / 28 / 19 |
| Tests de feature | `ls Modules/Knx/tests/Feature/*.php` | 27 (sin suite `Unit`) |
| Traducciones | conteo en disco | `nl` 8, `en` 8 |
| Marcadores de código pendiente | grep de `TODO`/`FIXME`/`XXX`/`not implemented`/`abort(501`/`stub` sobre todo `Modules/Knx` | **cero hallazgos genuinos** |
| Discrepancia contrato ↔ implementación | lectura de `openapi.yaml` + controller + test | **confirmada** (§13.1) |

**Lo que este análisis NO verificó:** no corrió la suite de tests ni Pint. Los conteos de `234 passed / 1 skipped` son **declarados por el proyecto** (`handoff.md:84`) y se citan como tales en §9.2; una corrida independiente de la suite es trabajo de verificación aparte.

### 9.4 Cobertura de tests por comportamiento

27 archivos, todos `Feature`, todos bajo `Modules/Knx/tests/Feature`. Esta tabla dice **qué comportamiento está realmente clavado por un test** — es la mejor guía para saber qué se puede tocar con red y qué no.

| Archivo | Qué comportamiento asegura |
|---|---|
| `KnxSessionTest.php` | Login, payload de sesión, **uniformidad del rechazo**, anti-throttle, rotación del refresh, logout, y que un token de Claesen se rechaza |
| `KnxTenantTest.php` | Resolución de tenant; **falla ruidosamente** en lugar de caer a Claesen |
| `KnxProjectsAndClientsTest.php` | Formas de listado, filtros, stats, proyecto por `code` |
| `KnxOfficeWritesTest.php` | Crear cliente/proyecto + habitaciones; código inválido o duplicado → `422`; referencias cruzadas entre organizaciones rechazadas; guard office-only |
| `KnxProjectDossierTest.php` | Forma del dossier, orden campo-antes-de-ETS, `isNew` sin confirmar, bandera de conflicto abierto, feed de actividad |
| `KnxDashboardTest.php` | Forma del dashboard, conteo de KPIs, filas por técnico, conteo semanal, **ausencia de `travelling`** |
| `KnxPlanningTest.php` | Forma de técnicos, validación de rango, asignación idempotente, borrado, `401` |
| `KnxConflictsTest.php` | Forma y orden del listado, filtros, `takenAddresses`, historial append-only, `409`, flujo reversible |
| `KnxZonesTest.php` | Forma de zona, estado/bloqueante derivados, filtro, recomputo en `PATCH`, exigencia de nota, `401` |
| `KnxFunctionsAndTestsTest.php` | Formas de ficha y prueba, marcado de aprobación, exigencia de nota + creación de incidencia, ejecuciones append-only |
| `KnxFieldSessionTest.php` | Forma de sesión de campo, separación de apps, trabajos de hoy, alcance `isAssignedToday`, lectura compartida de zonas |
| `KnxFieldProjectTest.php` | Payload de proyecto de campo, habitaciones/plantas, `deviceTypes` derivados, planos + URL descargable, filtro de solo-dibujos, `403/404` de alcance |
| `KnxFieldDeviceTest.php` | Registro de aparato, `isNew` en el dossier, idempotencia, `409` + evidencia de conflicto/foto, autocreación de cuadro, validación |
| `KnxFieldIssueTest.php` | Incidencia → conflicto, anclaje, mapeo de `kind` incluido `other`, idempotencia, evidencia fotográfica, visibilidad en oficina |
| `KnxFieldVisitTest.php` | Cierre en 3 fases, resolución de habitación, orden del listado, idempotencia, invariante "no muta nada más", lectura en oficina |
| `KnxFieldInboxTest.php` | Forma y orden de la bandeja, cuadro desconocido → `null`, ack confirma el aparato, ack-all, ack idempotente |
| `KnxFieldIdempotencyRaceTest.php` | **Carrera real con una segunda conexión**: ambos reintentos responden `200` del ganador, no `500` |
| `KnxDocumentsAndReportsTest.php` | Listado/detalle de documentos, descarga firmada, historial de informes, CSVs `ets`/`dossier`/`hours`, helper de tamaño |
| `KnxDocumentsUploadTest.php` | Subida multipart + metadatos derivados, transacción de `supersedes`, idempotencia, `413`, rollback por fallo de almacenamiento, `clientId` vacío o de otra organización |
| `KnxPlanMarkersTest.php` | `PUT`/`GET` de marcadores por documento, reemplazo idempotente, aislamiento por revisión, `carryOverFrom`, validación, `404`, office-only |
| `KnxBoardsTest.php` | Importación de worklist, forma de cuadros, `slot` de módulo, objetos de canal, **`boardId` = id del marcador**, importación idempotente |
| `KnxEmployeesTest.php` | Listado de empleados de oficina y campo, filtro de rol, inactivos excluidos, office-only |
| `KnxEmployeeModelTest.php` | `shortName`/`initials`, scopes de oficina y campo, reglas de tenant + cuenta, **sin acceso a Filament** |
| `KnxDemoSeederTest.php` | Siembra del fixture en las dos apps, fórmula de generación, reemplazo en re-ejecución, guardas de producción y de confirmación |
| `KnxEventStreamTest.php` | Cursor del SSE, dos eventos en orden, headers + autocierre, `401` |
| `KnxContractShapeTest.php` | Envelope de error uniforme, código y cuerpo del `409`, derivación de zona, exigencia de nota |
| `OpenApiContractTest.php` | **Anti-drift bidireccional**: documentado ↔ registrado |

**Observación de valor:** el único hueco de cobertura relevante es que `OpenApiContractTest` verifica **existencia** de rutas, no **forma** (códigos de estado, esquemas de request/response). Por eso la discrepancia `204 vs 200` de `notifications/{id}/ack` (§13.1) sobrevive al test de contrato. Endurecer ese test es un ticket con retorno alto y bajo coste.

---

## 10. Qué falta

**Método:** se leyó `odd/tasks/knx-*.md`, `docs/Knx/*.md` y se grepeó todo `Modules/Knx` buscando `TODO`, `FIXME`, `XXX`, `not implemented`, `NotImplemented`, `abort(501`, `unimplemented`, `stub`, `placeholder`.

**Resultado del grep: cero marcadores de código pendiente.** Los únicos hits son intencionales: dos `LogicException` del `KnxDemoSeeder` que se niegan a sembrar en producción o sin confirmación (`:105,111`), y `writePlaceholderPdf()` — un helper del seeder que escribe PDFs reales de una página. **No hay código a medias escondido.** Todo lo que falta está declarado en la documentación.

### 10.1 Diferido por decisión de producto (no es olvido)

| Item | Evidencia | Naturaleza |
|---|---|---|
| **Aviso de zonas no preparadas en `PUT /planning`** (§1.6) | `docs/Knx/knx-kantoor-backend.md:182` — "pendiente de decisión de producto… No se implementa aún"; decisión abierta #2 | Decisión de producto pendiente |
| **Alta de usuarios Bertels desde Kantoor** (la oficina crea personas fuera del ERP) | `docs/Knx/knx-kantoor-backend.md:52` — "**PLANIFICADO, no implementado**"; decisión abierta #1 | Requiere decisión + ticket |
| **`techniciansToday[].status` nunca emite `travelling`** | `docs/Knx/knx-kantoor-backend.md:216`; comentario en `TechnicianTodayResource.php:13-16`; asertado por `KnxDashboardTest.php:122-128` | Espera un check-in de campo que no existe |
| **`photos_uploaded` nunca se emite en el feed de actividad** | `docs/Knx/knx-kantoor-backend.md:153`; `ProjectActivityService.php:21`; hueco #4 del handover | Bloqueado: no hay tabla de fotos |
| **Informes: los 4 tipos salen como CSV**; no existen plantillas PDF/XLSX de `dossier`/`delivery`/`hours` | `docs/Knx/knx-kantoor-backend.md:234` — "esas plantillas no existen todavía"; hueco #1 del handover | Deuda declarada, **visible al cliente final** |

### 10.2 Fuera de alcance declarado

| Item | Evidencia |
|---|---|
| `zonesNotReady` en `GET /projects` | `docs/Knx/knx-kantoor-backend.md:205` — "**NO se implementó**" |
| Endpoints de escritura no consumidos por el front de oficina: `POST /projects/{code}/functions`, `POST /functions/{id}/approve`, `GET /functions/{id}/revisions`, `POST /projects/{code}/tests`, `POST /tests/{id}/evidence` | `docs/Knx/knx-kantoor-backend.md:249` — "Quedan pendientes y declarados" |
| Historial `knx_test_executions` **almacenado pero no expuesto** por API | `docs/Knx/knx-kantoor-backend.md:257` — "**No se expone todavía**" |
| `POST /field/projects/{code}/photos` | `docs/Knx/handover-frontend.md:330-334` — "⏳ marcado próximamente"; las fotos viajan inline en aparato/incidencia |
| Capa de token de Veld (repo front, no backend) | `docs/Knx/handover-frontend.md:30` |

### 10.3 Deuda técnica

| Item | Evidencia | Impacto |
|---|---|---|
| **`lang/en` inalcanzable por HTTP** — `SetLocaleFromHeader` no aplicado a rutas Knx | `handoff.md:78` (CLA-627) | Un usuario anglófono ve siempre neerlandés |
| **Abilities de Sanctum decorativas** — el token de campo se emite como `knx:office` | `AuthController.php:131-134` vs `EnsureKnxApp` | Confusión al leer el token; hoy sin impacto funcional porque el guard usa roles |
| **`RequireOrganization` es no-op** con `organizations.enforce` off | `RequireOrganization.php:34-36` | El aislamiento depende de un solo punto (`KnxAuthService`); no hay segunda red |
| **Comentario obsoleto** en `KnxExport::TYPE_HOURS` ("no data source in this domain yet") mientras `ExportService::hours()` sí lo genera | `KnxExport.php:22` vs `ExportService.php:150` | Menor, pero engaña al leer |
| **Fotos sin modelo propio** — sin tabla, sin endpoint de listado, sin evento | ver §10.1 | Limita auditoría y el feed de actividad |
| **Deriva de lockfile** — `M package-lock.json` en el árbol de trabajo | `git status` | Ensucia el candidato de revisión (mismo patrón que el incidente CLA-630) |

### 10.4 Sin ticket abierto

Ninguno de los items de §10.1–§10.3 tiene ticket en el team Linear **KNX** — que hoy solo contiene **KNX-3** y **KNX-4**, ambos `Done`. El backlog de KNX es, literalmente, este documento.

Existen además tres **proyectos** (no issues) en Linear que corresponden a despliegues por cliente: **KNX · Kruiri**, **KNX · De Brand**, **KNX · Marien**. Si el plan es que cada cliente tenga su instancia, ese es el trabajo estructural que aún no está descompuesto en tickets.

---

## 11. Riesgos y bloqueantes

| # | Riesgo | Evidencia | Estado |
|---|---|---|---|
| 1 | **Revisión nativa (RDD) bloqueada por el relay.** El reviewer del `pi_host_relay` no produce texto: `reviewer-empty-output`, con `stopReason` `length` a los 153 s y luego `stop` en 1,3 s. Ocurrió de forma determinista **dos veces** sobre KNX-4 (candidato de 1060 líneas) y ya había bloqueado la corrección de U1 en KNX-3 (el binding vigente se rechazó como *stale* en los cuatro estados probados, y `advance` no soporta `capture-correction-plan`). | `odd/tasks/knx-openapi-contract.md`; `odd/tasks/knx-planos-cuadros.md`; `handoff.md:82,84` | **Abierto.** Sin veredicto y sin autoridad quemada. El diagnóstico del proyecto es que es el relay, no el diff. No se usó RESET/RECOVER (destructivos). |
| 2 | **GO técnico pendiente** de KNX-3 y KNX-4 (y de CLA-627/CLA-630) | `handoff.md:78,82,84` | **Abierto.** La rama está push a su upstream, pero **no a `origin/main`** (132 commits de diferencia). |
| 3 | **Programa multiempresa P7 incompleto.** `NOT NULL`, flag activo en producción, alta de Bertels y decisión de MFA están pendientes y bloqueados por staging (CLA-530/531/525). Regla de hierro: **ningún usuario real de Bertels hasta que P5 esté verificada.** | `known-risks.md:174,297-307`; `CLAUDE.md` §multiempresa | **Bloqueante externo al módulo.** Sin esto no hay usuarios reales de Kantoor/Veld en producción, por completo que esté el feature. |
| 4 | **Slice U4/U5 de KNX-3 y todo KNX-4 sin veredicto nativo.** Descansan sobre tests + HTTP manual, no sobre la autoridad RDD exigida. | §11.1 | Abierto por decisión del usuario (2026-10-06): cerrar con lo verificado. |
| 5 | **Informes CSV** — el contrato promete presentación, la realidad es CSV | §10.1 | Aceptado y declarado; visible al cliente. |
| 6 | **Deriva de `package-lock.json`** en el árbol | §10.3 | Menor, pero **es exactamente el mecanismo que rompió el review de CLA-630**: como la proyección de review es el *working tree*, el drift puede tapar lo commiteado. |

---

## 12. Cómo seguir desarrollando

### 12.1 Lectura mínima antes de escribir código

```
docs/Knx/knx-kantoor-backend.md      ← doc viva del módulo (todas las decisiones)
docs/Knx/openapi.yaml                ← el contrato: se edita en el mismo commit que la ruta
docs/Knx/handover-frontend.md        ← qué esperan los dos fronts, y sus huecos declarados
docs/Knx/knx-feature-report.md       ← este documento (vista de conjunto y gaps)
```

Y en el repo del front que se toque: `electro-bertels-{kantoor,veld}/odd/` — tienen decisiones y bloqueantes propios.

### 12.2 Orden sugerido de trabajo

**Desbloqueo (antes de feature nueva):**
1. **Cerrar el GO técnico** de KNX-3/KNX-4 con el usuario y decidir el push de `electrobertels/knx-api` a `origin/main`.
2. **Atacar el relay del RDD** como incidente propio (reason code `reviewer-empty-output`): mientras siga roto, cada slice nueva nace sin veredicto nativo. Vale la pena diagnosticarlo con un candidato trivial para separar "diff grande" de "relay roto".
3. **Limpiar la deriva de `package-lock.json`** antes de abrir cualquier candidato de revisión.

**Trabajo de producto (a decidir con el usuario, cada uno requiere ticket):**
4. **Presentación de informes** (PDF/XLSX para `dossier`/`delivery`/`hours`): es lo único de la lista que el cliente final ve como incompleto.
5. **Fotos como entidad de primera clase** (tabla + endpoint + evento `photos_uploaded`): desbloquea tres items declarados a la vez (`photos_uploaded`, el POST de fotos de Veld, y la auditoría de evidencia de conflicto).
6. **Check-in de campo** → habilita el estado `travelling` del dashboard y el aviso de zonas no preparadas en planificación (§1.6).
7. **Alta de usuarios Bertels desde Kantoor** — el hueco funcional más grande que queda, y el único que cambia el modelo de datos.
8. **`SetLocaleFromHeader` en las rutas Knx** — deuda pequeña con impacto directo y arreglo conocido (basta con imitar `Modules/FieldOps/Routes/api.php:23`).

**Trabajo de plataforma:**
9. Descomponer **KNX · Kruiri / De Brand / Marien** en tickets, si el destino es multi-cliente.

### 12.3 Trampas conocidas — leer antes de tocar

1. **Agregar una ruta sin tocar `docs/Knx/openapi.yaml` rompe `OpenApiContractTest`.** No es opcional.
2. **No agregar `unique(project_id,address)` a `knx_devices`.** La dirección duplicada *es* el conflicto; es el mecanismo del Conflictencentrum.
3. **No convertir `BelongsToKnxTenant` en global scope** ni asumir que `organization:electro-bertels` filtra: hoy es no-op y la frontera real es `KnxAuthService`.
4. **No "arreglar" las abilities del token** sin decidir antes el modelo de autorización: `EnsureKnxApp` usa roles de Spatie, y `knx:office` en un token de campo es hoy inocuo.
5. **La identidad de cuadro es el `slug` del worklist**, la misma cadena que el `boardId` del marcador. `revision` se **deriva del documento, no se duplica**. Tocar esto rompe marcadores y cuadros a la vez.
6. **`PUT .../markers` reemplaza el conjunto de la página y borra la colocación del mismo cuadro en otras páginas.** No es un merge.
7. **La posición física de una luminaria / el `slot` de un módulo son identidades estables.** Un cambio de canal retrocediendo en el worklist **parte el módulo**; es intencional.
8. **`KnxDemoSeeder` reemplaza todos los datos KNX del tenant.** Se niega a correr fuera de tests sin `KNX_DEMO_SEED_CONFIRM=true`. No habilitarlo contra una base con trabajo real.
9. **Los endpoints de descarga son firmados y viven fuera de `auth:sanctum`**; la URL del plano de campo vale **una semana** a propósito (offline). No acortarla "por seguridad" sin resolver primero el offline.
10. **El SSE requiere Bearer**, no query param: `EventSource` nativo no sirve.
11. **`EnsureKnxApp` responde 401, no 403** — es deliberado; no "corregirlo" a 403 sin revisar el contrato con los fronts.

### 12.4 Cómo verificar sin romper nada

```bash
# Suite del módulo (requiere el stack Docker; el checkout usa DB_HOST=mysql)
php artisan test --testsuite=Modules --filter=Knx

# El contrato, aislado (es el test que más fácilmente se rompe sin querer)
php artisan test Modules/Knx/tests/Feature/OpenApiContractTest.php

# Simetría ruta ↔ spec, sin correr nada
grep -cE "Route::(get|post|put|patch|delete)\(" Modules/Knx/routes/api.php   # debe dar 52
# y: 44 paths / 52 operaciones en docs/Knx/openapi.yaml

# Estilo (no usar --fix sin querer)
vendor/bin/pint --test Modules/Knx

# Importación de cuadros desde worklist real
php artisan knx:import-worklist {directory} {code}
```

El contenedor de verificación HTTP usado por este proyecto es `knx-api-tmp` en el **puerto 8002**; mantiene aislada la suite respecto de otros checkouts del mismo repo.

---

## 13. Apéndice: discrepancias detectadas en este análisis

Hallazgos **nuevos** de este reporte (doc vs código), ninguno bloqueante, todos verificables:

| # | Qué | Evidencia | Severidad |
|---|---|---|---|
| 1 | **Los dos endpoints de confirmación de bandeja documentan `200` y devuelven `204`.** `POST /notifications/{id}/ack` y `POST /notifications/ack-all` están documentados en `openapi.yaml:643` y `openapi.yaml:635` como `"200": { description: OK }`, pero ambos hacen `response()->noContent()` (`FieldNotificationController.php:37,44`) y el test asertá `assertNoContent()` (`KnxFieldInboxTest.php:107,124,142`). Es una discrepancia real contrato-implementación que el test anti-drift **no detecta**, porque solo compara existencia de rutas, no códigos de estado. **Confirmado en este análisis leyendo las tres fuentes.** | `docs/Knx/openapi.yaml:635,643` vs `FieldNotificationController.php:37,44` vs `KnxFieldInboxTest.php:107` | Media — un front que espere cuerpo en el `200` se rompe. **Candidato claro a ticket**, y el arreglo es barato: decide uno de los dos (lo natural es corregir el YAML a `204`, porque el `204` ya está testeado) y endurece `OpenApiContractTest` para validar códigos, no solo paths. |
| 2 | **El comentario de `zone_check_keys` está desprendido de su array.** El bloque que explica los 8 checks en orden está antes de `events`, y el array vive al final del archivo. | `config/config.php:74-90` vs `:115` | Baja (documentación) |
| 3 | **La doc del módulo dice "16 tablas"; hay 22.** | `docs/Knx/knx-kantoor-backend.md` (K0) vs 22 modelos/migraciones | Baja |
| 4 | **`GET /field/projects/{code}/devices` existe en código y en el spec, pero no tiene fila propia en la tabla de slices en prosa** — no hay un "V11.f". | `routes/api.php:84`; `docs/Knx/openapi.yaml:723`; sin mención `V11.f` en `docs/Knx/*` | Baja (solo documentación; no es hueco funcional) |
| 5 | **`KnxExport::TYPE_HOURS` dice que no hay fuente de datos, pero `ExportService::hours()` la genera.** Comentario obsoleto. | `KnxExport.php:22` vs `ExportService.php:150` | Baja |
| 6 | **Las abilities de Sanctum son decorativas:** todo token se emite como `knx:kantoor` / `knx:office`, incluso el de campo, y el guard decide por rol. | `AuthController.php:131-134`; `EnsureKnxApp.php:31` | Media (malentendido latente) |

Los puntos 1 y 6 son los únicos con consecuencia real si alguien construye encima sin conocerlos.
