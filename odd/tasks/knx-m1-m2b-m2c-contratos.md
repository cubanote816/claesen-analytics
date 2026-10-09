# KNX M1 + M2 + M2b + M2c — contrato primero, rebanadas por ticket

- **Rama:** `electrobertels/knx-api`. Worktree: `/home/totti/claesen/electrobertels-knx-api`.
- **Linear (project P-KNX-30 · KNX · Módulo Kantoor + Veld):** KNX-20, KNX-18, KNX-24, KNX-51,
  KNX-44, KNX-45, KNX-46, KNX-47, KNX-52, KNX-25, KNX-53, KNX-54, KNX-56, KNX-55, KNX-26, KNX-50,
  KNX-48, KNX-49.
- **Engram mirror:** `odd/knx-m1-m2b-m2c-contratos/tasks`.
- **Doc del módulo:** `docs/Knx/knx-kantoor-backend.md`; contrato `docs/Knx/openapi.yaml`;
  contrato nuevo `docs/Knx/openapi-next.yaml`; handover `docs/Knx/handover-frontend.md`.

## Reglas de este programa

1. **El contrato manda.** Las rutas nuevas nacen en `docs/Knx/openapi-next.yaml` (fuera del test
   anti-deriva) y **pasan** a `docs/Knx/openapi.yaml` en el MISMO commit en que se implementan.
2. **Un ticket = al menos un commit.** No se juntan KNX-54/56/55/26.
3. **Toda ruta por id sin proyecto** (`PATCH /rooms/{id}`, `GET /intents/{id}/versions`, …) resuelve
   a través del proyecto **y** la organización → test `otra organización → 404`. `KnxProjectRoom`
   NO tiene `BelongsToKnxTenant`: no asumirlo, resolverlo explícito.
4. Cada ruta de `openapi-next.yaml` lleva `x-ticket` y `x-status: planned|provisional|pending-decision`.
5. Al cerrar cada rebanada: `php artisan test --filter=Knx` + `OpenApiContractTest` verdes, HTTP
   real en `:8002` con salida cruda, Pint sobre lo tocado, handover regenerado en los DOS fronts.
6. Sin push y sin PR. No se avanza al siguiente ticket sin cerrar el anterior.

## Decisiones tomadas (y su porqué)

1. **Habitación canónica = la que crea oficina.** ETS / worklist / plano entran como `aliases`; lo
   que no casa queda visible sin resolver (`roomId: null`). El vocabulario canónico quedó abierto en
   `planos-extraccion-medicion.md:269`; esta es la decisión operativa.
2. **Zona = habitación**, `unique(room_id)` en `knx_zones`. Zonas de demo sin habitación: se
   **conservan** como zonas huérfanas legibles (`roomId: null`) y se documenta; no se borran datos de
   demo al migrar. La migración crea una zona por habitación existente y **no** toca las sin habitación.
3. **DWG provisional** hasta la medición de KNX-49 (los DWG de Den Brand nunca se midieron).
4. **Intents sin escritura de campo**: `pending-decision` en el contrato, no se implementa.
5. **Mal funcionamiento = `KnxConflict`** con discriminador `lifecycle: ets|malfunction`.
   Transiciones validadas SOLO para `malfunction` (`open→diagnosing→resolved→verified`, +`rejected`);
   el ciclo ETS sigue libre y reversible (decisión K6). `lifecycle` se expone en el recurso.
6. **`PATCH /conflicts/{id}`** para malfunction valida la transición y escribe línea de histórico con
   autor y hora, reutilizando `knx_conflict_logs`.
7. Correo: reutilizar el transporte transaccional de `Modules/Mailing`
   (`MicrosoftGraphTransport`, decisión D11: de Mailing se comparte transporte, nunca la plataforma de
   campañas).

## Rebanadas

- [x] **R0** `docs/Knx/openapi-next.yaml` — contrato COMPLETO de todas las rutas nuevas (34 paths /
  43 operaciones), `x-ticket`/`x-status`, esquemas de request/response, códigos y ejemplos. Commit
  `b802bf7`. Aviso en el handover + plan de rebanadas. Commit aparte: `knx-feature-report.md`
  (`9f52b4a`, estaba sin trackear).
- [x] **R1a** KNX-20 — `204` en los dos `ack`, cuerpos reales de los 3 POST de campo y
  `AssertDocumentedSuccessCode` (guard test-only que compara todo 2xx real del suite contra el spec).
  Commit `61dcdce`.
- [x] **R1b** KNX-18 — test guardián: `PUT /markers` sin la clave `markers` → `422` (y el
  conjunto vacío sigue siendo `200`). Commit `7b3ac16`. **KNX-18 cerrada en Linear.**
- [x] **R1c** KNX-24 — `SetLocaleFromHeader` en las rutas Knx (`lang/en` alcanzable por HTTP).
  Commit `b38e791`. **KNX-24 queda In Progress:** el ticket cubre además abilities de Sanctum y
  `RequireOrganization`, y la orden de trabajo acotó esta rebanada al locale.
- [x] **Colateral** — `ef3cf00`: flake preexistente del seeder (día 0) que apareció al correr R1c con
  el reloj del contenedor en 08:34; arreglado en commit aparte, no mezclado con KNX-24.
- [x] **R2** KNX-51 — dirección de la obra (`siteAddress` obligatoria en el alta), `GET`/`PATCH
  /projects/{code}`, `siteAddress` en `ProjectResource`, `FieldProjectResource` y `FieldTodayJobResource`,
  `resolve()` con filtro de organización. Commit `PENDIENTE_R2`.
- [x] **R3** KNX-44 — habitaciones canónicas + alias (`knx_room_aliases`), zona automática con los 8
  checks (`ZoneService::createForRoom`), `unique(room_id)` en `knx_zones` con backfill de una zona por
  habitación, `roomId` resuelto por nombre/alias en los enlaces de cuadro, `Zone.roomId` y
  `BoardLink.roomId`. Commit `PENDIENTE_R3`.
- [x] **R4** KNX-45 — modelo de plano por revisión: `knx_plan_rooms`, `GET`/`PUT
  /projects/{code}/plans/{documentId}/rooms`, `carryOverFrom` misma página → `proposed`, `present|array`,
  `roomId` distinct. Commit `PENDIENTE_R4`.
- [x] **R5** KNX-46 — ficha de habitación oficina `GET /projects/{code}/rooms/{id}/sheet`
  (`room`, `zone` con checks, `devices`, `boardLinks`, `functions`, `issues`; `intents`/`photos`
  viajan `[]` hasta KNX-55/KNX-26). Commit `PENDIENTE_R5`.
- [x] **R6** KNX-47 — campo: `GET /field/projects/{code}/rooms` (fichas), `GET …/plans/{documentId}/model`
  (sólo confirmadas + marcadores), `floor` en `POST /documents`, `floor`/`isCurrent` en `DocumentFile`
  y en los planos de campo, `PATCH /documents/{id} {floor}`. Commit `PENDIENTE_R6`.
- [x] **R7** KNX-52 — cuadros y módulos oficina: `origin`/`reviewState`/`updatedBy`/`updatedAt`,
  alta/edición/borrado de módulos, `PUT …/links`, confirmaciones, y reimportación que **no pisa** lo
  editado a mano (lo cuenta como `skipped`). Commit `f9d75ec`.
- [x] **R8** KNX-25 — campo: `GET /field/projects/{code}/boards` (misma forma que oficina, con
  `origin`/`reviewState`; hoy `/boards` daba 401 con token de campo). Commit `e020384`.
- [x] **R9** KNX-53 — campo: `POST /field/projects/{code}/boards` y `POST …/boards/{boardId}/modules`
  (idempotentes por `clientId`, nacen `field`/`unreviewed`), `409 slot_taken` con el módulo, y
  `boardId`/`moduleSlot`/`channel` opcionales en el registro de aparatos (expuestos por `FieldDevice`
  y por el `Device` del dossier). Commit `PENDIENTE_R9`.
- [x] **R10** KNX-54 — avisos generalizados (`kind`, filtros, SSE) + `GET|PUT
  /me/notification-preferences` + correo inmediato/digest (`KnxNoticeMail`, comando
  `knx:notification-digest`, `emailed_at` como dedupe). Commit `PENDIENTE_R10`.
- [x] **R11** KNX-56 — mal funcionamiento (`lifecycle: malfunction`, ciclo validado, `POST /projects/{code}/issues`). Commits `e049918` + `1ca942d`.
- [ ] **R12** KNX-55 — intents (oficina lectura+escritura, campo lectura). Escritura de campo pendiente.
- [ ] **R13** KNX-26 — fotos como entidad + evento `photos_uploaded`.
- [ ] **R14** KNX-50 — carry-over de habitaciones entre revisiones + aviso de cambios.
- [ ] **R15** KNX-48 — propuesta automática de habitaciones en PDF vectorial.
- [ ] **R16** KNX-49 — MEDIR DWG primero, reportar, y recién después conversión en servidor.

## Progreso / evidencia

- **R0 (`b802bf7`).** `docs/Knx/openapi-next.yaml`: 34 paths / 43 operaciones, 34 schemas, todas con
  `x-ticket` y `x-status`. YAML parsea con `Symfony\Component\Yaml`. Reparto por ticket: KNX-25:1,
  KNX-26:2, KNX-44:3, KNX-45:2, KNX-46:1, KNX-47:2, KNX-48:1, KNX-49:2, KNX-51:3, KNX-52:9,
  KNX-53:3, KNX-54:4, KNX-55:5, KNX-56:5. Handover actualizado con el anuncio para los dos fronts.
  Test de contrato (`OpenApiContractTest`) sigue verde porque `openapi.yaml` no se tocó:
  **2 passed / 53 assertions**.
- **R0b (`9f52b4a`).** `docs/Knx/knx-feature-report.md` deja de estar sin trackear.

- **R1a (`61dcdce`).** `openapi.yaml`: `204` en `/notifications/ack-all` y
  `/notifications/{id}/ack`; cuerpos reales de los 3 POST de campo (aparato / incidencia / visita) con
  ejemplo de `201` y de `200`. Nuevo `AssertDocumentedSuccessCode` registrado sólo bajo
  `runningUnitTests()`: compara todo 2xx real del suite contra las respuestas declaradas. Dos tests
  nuevos en `OpenApiContractTest` (guard registrado + toda operación declara un 2xx).
  - Suite: **243 passed / 1 skipped / 0 failed** (1434 aserciones), Pint limpio en lo tocado.
  - Prueba del guard: revirtiendo el spec de `ack-all` a `200`, el suite falla con
    `Undocumented success code: POST /notifications/ack-all returned 204, and docs/Knx/openapi.yaml declares [200]`.
  - HTTP real `:8002`: `POST /field/projects/C1618/devices` → `201` cuerpo real y reintento `200`
    mismo aparato; `POST …/issues` → `201 {"id":"87",…}`; `POST …/visits` → `201 {"id":"9",…}`;
    `POST /notifications/44/ack` → `204`; `POST /notifications/ack-all` → `204`.
  - **Siguiente paso (no barato todavía):** validar el cuerpo contra el esquema. `openapi.yaml` usa
    ejemplos inline, no `components/schemas`, así que la validación de forma exige primero migrar el
    spec a schemas. Queda anotado para KNX-20 como deuda.

- **R1b (`7b3ac16`).** Test guardián nuevo. HTTP real: `{"page":1}` → `422
  "The markers field must be present."`; `{"page":1,"markers":[]}` → `200`. KnxPlanMarkersTest
  12 passed / 67 assertions.
- **R1c (`b38e791`).** `SetLocaleFromHeader` en el grupo `v1/knx`. HTTP real: sin cabecera y con
  `Accept-Language: nl` → «Deze inloggegevens kloppen niet.»; con `Accept-Language: en` →
  «These credentials are incorrect.» (el default sin cabecera sigue siendo neerlandés).
- **Colateral (`ef3cf00`).** Flake de reloj en `KnxDemoSeeder::at()` + reloj congelado a las 08:30 en
  `KnxConflictsTest`. Suite de M1 completa: **245 passed / 1 skipped / 0 failed** (1443 aserciones).

- **R2 (`PENDIENTE_R2`).** Migración `2026_10_08_100000_add_site_address_to_knx_projects_table`
  (`site_street`, `site_postal_code`, `site_city`, `site_access_note`, nullable). `POST /projects`
  exige `siteAddress` y deriva `city`; `PATCH /projects/{code}` nuevo; los tres recursos exponen
  `siteAddress`; `ProjectController::resolve()` filtra por organización. Suite completa (fin de M1+M2
  temprano): **247 passed / 1 skipped / 0 failed** (1473 aserciones).
  - HTTP real `:8002`: `POST /projects` sin `siteAddress` → `422`; con `siteAddress` (y un `city`
    distinto inventado) → `201` con `siteAddress` y `city: "Mol"` derivado; `PATCH` → `200` con
    nombre y dirección nuevos y `accessNote` reseteado a `null` (el bloque reemplaza entero).
  - Artefactos de verificación borrados de la BD dev.

- **R3 (`PENDIENTE_R3`).** Migraciones `2026_10_08_110000` (aliases + `unique(room_id)` + backfill) y
  `2026_10_08_120000` (`room_id` en `knx_board_links`). Endpoints `GET`/`POST /projects/{code}/rooms`
  y `PATCH /rooms/{id}` (resuelve por proyecto + organización). El importador resuelve el nombre de
  habitación del worklist a la habitación canónica por nombre **o alias**; lo que no casa queda
  `roomId: null` visible.
  - **Decisión sobre zonas de demo sin habitación:** se conservan como huérfanas legibles
    (`roomId: null`); la migración solo crea zonas para habitaciones que no tienen una y nunca borra
    filas de oficina. Verificado en la BD dev: 32 habitaciones → 32 zonas con `room_id`, 0 habitaciones
    sin zona, `unique` presente, y 3 zonas de demo sin habitación intactas.
  - Suite completa: **253 passed / 1 skipped / 0 failed** (1518 aserciones).
  - HTTP real `:8002`: `GET /projects/C1618/rooms` → 7 habitaciones con `zoneId`; `POST` → `201` con
    `zoneId: 134` y alias; `PATCH /rooms/540` → `200` con nombre y alias reemplazados.
  - Bug propio atrapado por la verificación real: la migración insertaba `created_at` en
    `knx_zone_checks`, que a propósito no tiene esa columna; los tests no lo veían porque el backfill
    corre sobre tablas vacías. Arreglado y la migración quedó idempotente (MySQL auto-commitea DDL).

- **R4 (`PENDIENTE_R4`).** Migración `2026_10_08_130000_create_knx_plan_rooms_table`; modelo
  `KnxPlanRoom`; `PlanRoomService`; `PlanRoomSetResource`; `PlanRoomController`; 7 tests
  (`KnxPlanRoomsTest`). `proposalStatus` queda `none` (KNX-48 lo llena).
  - HTTP real `:8002`: GET inicial `{proposalStatus:none, rooms:[]}`; PUT con polígono/`areaM2` → `200`
    con la geometría devuelta tal cual; PUT sin la clave `rooms` → `422`; PUT `rooms:[]` → `200` y limpia.

- **R5 (`PENDIENTE_R5`).** `RoomController::sheet` + ruta + `openapi.yaml`; ruta movida de
  `openapi-next.yaml`. 8 tests en `KnxRoomsTest`.
  - HTTP real `:8002`: sheet de Inkomhal con las 8 claves, `zone` 99 con 8 checks, 3 dispositivos;
    `intents`/`photos` = `[]`; habitación de otro proyecto → `404`.

- **R6 (`PENDIENTE_R6`).** Migración `2026_10_08_140000` (`knx_documents.floor`); `RoomSheetService`
  compartido por la ficha de oficina y el listado de campo; `FieldProjectController::rooms` y
  `::planModel`; `floor`/`isCurrent` en `FieldPlanResource`; `PATCH /documents/{id}`. Cinco rutas
  movidas/actualizadas en `openapi.yaml`.
  - HTTP real `:8002`: `/field/projects/C1618/rooms` → 8 fichas con las 8 claves; planos de campo con
    `floor`/`isCurrent`; modelo del documento 89 → `{rooms:[], markers:[]}`; `POST /documents` con
    `floor` → guardada; `PATCH {floor:"2e verdieping"}` → `200`; `{floor:null}` → `200`; sin la clave
    → `422`. Artefacto de subida borrado.
  - Bug propio: `PATCH` validaba `floor` con `required`, que rechaza un `null`; el contrato dice que
    `null` LIMPIA la planta. Pasó a `present|nullable`.

- **R7 (`f9d75ec`).** Migración `2026_10_08_150000`; `origin`/`review_state`/`updated_by` en
  `knx_boards` y `knx_board_modules`; `BoardService`; `BoardResource`/`BoardModuleResource`;
  `BoardController` con 8 endpoints de escritura; el importador salta cuadros con procedencia humana
  y lo reporta. Nuevo `KnxBoardWritesTest` (6 tests).
  - HTTP real `:8002`: alta de cuadro → `201` `office`/`confirmed`/`L. Smet`; módulo → `201`;
    slot ocupado → `409 slot_taken` con el módulo actual; `PUT links` → `200`; `confirm` → `200`.
    Artefactos borrados de la BD dev.
  - Suite completa: **269 passed / 1 skipped / 0 failed** (1677 aserciones).

- **R8 (`e020384`).** `FieldProjectController::boards` + ruta de campo + `openapi.yaml`;
  `GET` movido de `openapi-next.yaml` (el `POST` de KNX-53 sigue en staging). 1 test nuevo.
  - HTTP real `:8002`: 3 cuadros con `origin: import`/`reviewState: confirmed`; 239870 → `403`;
    token de oficina contra la ruta de campo → `401`.
  - Suite completa: **270 passed / 1 skipped / 0 failed** (1687 aserciones).

- **R9 (`PENDIENTE_R9`).** Migración `2026_10_08_160000` (`client_id` en cuadros/módulos + `photo_path`
  + `module_slot`/`channel` en aparatos); `FieldBoardService` + `FieldBoardController`; `BoardService`
  expone `uniqueSlug`; `FieldDeviceService` resuelve `boardId` y guarda `moduleSlot`/`channel`;
  `FieldDeviceResource` y `DeviceResource` los exponen. 5 tests nuevos (`KnxFieldBoardTest`).
  - HTTP real `:8002`: board desde obra → `201` `field`/`unreviewed`; reintento → `200`; module → `201`;
    mismo slot con otra tentativa → `409 slot_taken` con `{device: SA/S8.16.2.2, slot: 3}`; device con
    `boardId`/`moduleSlot`/`channel` → devueltos. Artefactos borrados de la BD dev.
  - Migración aplicada en :8002 con `php artisan migrate` (no-op: ya estaba). Sin seeders.
  - Suite completa: **275 passed / 1 skipped / 0 failed**. Ticket compartido con Veld: **comentado, no Done**.

- **R10 (parcial).** Migración `2026_10_08_170000_generalize_knx_notifications`; `KnxNotification`
  con `KINDS`; `FieldNotificationResource` con el bloque tipado + legacy; filtros en el controller;
  `kind` en SSE; `kind`/`title`/`summary`/`target` en device, board, module, issue, conflict y visit;
  lang `notifications`; seeder y factory actualizados.
  - Suite completa: **278 passed / 1 skipped / 0 failed** (1758 aserciones).
  - HTTP real `:8002`: `GET /notifications?kind=device_registered` → 6 (con `kind`/`actor`); `kind=nope`
    → `422`. Migración aplicada con `php artisan migrate` (sin seeders).
  - **Pendiente de KNX-54:** `/me/notification-preferences` + correo inmediato/digest; y los
    escritores de `module_changed`, `intent_changed`, `malfunction_*`.

- **R10 (parte 2/2).** Migración `180000` (`knx_notification_preferences` + `knx_notifications.emailed_at`);
  `KnxNotificationPreference`; controlador/resource/rutas de preferencias; `KnxNoticeMailer`;
  `KnxNoticeMail` (encolada, `tries`/`backoff`); observador `KnxNotificationObserver` (inmediato en
  `created`, el resto al digest); comando `knx:notification-digest` (horario + al cerrar visita);
  `config('knx.office_mailbox')`. 4 tests (`KnxNotificationMailTest`).
  - Suite completa: **282 passed / 1 skipped / 0 failed** (1791 aserciones).
  - HTTP real `:8002`: `GET /me/notification-preferences` → `200 {immediate:true, digest:"per_visit",
    mutedKinds:[]}`; `PUT` → `200` con `digest:"hourly"`. Migración aplicada con `php artisan migrate`
    (sin seeders).
  - **Pendiente:** escritores de `module_changed`, `intent_changed` y `malfunction_*` (R11/R12).

- **R11.a (`e049918`).** Migración `2026_10_09_100000_add_malfunction_lifecycle_to_knx_conflicts`:
  `lifecycle` (default `ets`) + `symptom` en `knx_conflicts`, `by` + `note` en `knx_conflict_logs`.
  `KnxConflict` con `LIFECYCLES`, `MALFUNCTION_STATUSES`, `MALFUNCTION_TRANSITIONS` (ETS sigue libre).
  `ConflictService::update()` valida la transición de `malfunction`, sella el paso con autor y
  nota, y crea la nota `malfunction_status_changed`. `ConflictResource` expone `lifecycle`,
  `symptom` y el `by`/`note` del histórico; `GET /conflicts` filtra por `?lifecycle=`.
  - Suite de M1+M2 completa: **282 → en R11.a los 4 tests nuevos de `KnxConflictsTest`**; ver
    el cierre conjunto abajo.
  - Migración aplicada en `:8002` con `php artisan migrate` (sin seeders).

- **R11.b (`1ca942d`).** `FieldIssueService` pasa a `IssueService` con dos entradas
  (`reportFromField`, idempotente por `clientId`, y `reportFromOffice`, por ids del plano).
  Ruta nueva de oficina `POST /projects/{code}/issues`; `POST /field/projects/{code}/issues`
  acepta `kind: malfunction` + `symptom` y los ids opcionales `roomId`/`deviceId`/`boardId`/
  `moduleSlot`. `kind: malfunction` nace con `lifecycle: malfunction`, `type: malfunction`,
  severidad `warning`, y crea la nota inmediata `malfunction_reported` (KNX-54).
  - Contrato: `/projects/{code}/issues`, `POST /field/projects/{code}/issues`, `GET /conflicts`,
    `GET`/`PATCH /conflicts/{id}` movidos a `docs/Knx/openapi.yaml`; los esquemas
    `IssueInput`/`FieldIssueInput`/`FieldIssueResult` retirados de `openapi-next.yaml`.
  - Tests: **KnxOfficeIssueTest 7/7**, **KnxFieldIssueTest 18/18**, `OpenApiContractTest` 4/4.
  - Suite completa del módulo: **295 passed / 1 skipped / 0 failed** (1884 aserciones). Pint limpio.
  - HTTP real `:8002` (salida cruda en el comentario de Linear y en el resumen de sesión):
    oficina `POST /projects/C1618/issues {kind: malfunction, symptom, deviceId, roomId, boardId,
    moduleSlot, channel}` → `201` con `lifecycle: malfunction`, `symptom`, `deviceField`
    `Gang gelijkvloers · 1.1.101 · E11 · slot 3 · kanaal 2` e histórico `reported/by: L. Smet`;
    `PATCH {status: resolved}` desde `open` → `422` con el mensaje neerlandés de transición;
    `diagnosing → resolved → verified` → `200`, histórico con `by`/`note`; `?lifecycle=malfunction`
    → 1 (y `ets` → 6); bandeja con 3 `malfunction_status_changed`. Campo: `malfunction` sin
    `symptom` → `422`; con `symptom` → `201`; reintento del mismo `clientId` → `200` mismo id;
    conflicto con `lifecycle: malfunction` y nota `malfunction_reported` con actor y target.
  - Artefactos de verificación (conflictos 89/90 y sus notas) borrados de la BD dev. Nota de
    entorno: `mailpit` estaba parado en `electrobertels-login_sail`; los avisos inmediatos
    respondían `500` al no resolver el SMTP, así que se levantó el contenedor para verificar.
    En producción la cola no es `sync`, así que el envío no bloquea la escritura.

### Entorno de verificación (hallazgo, no obvio)

`php artisan test` desde el host falla con `getaddrinfo for mysql failed`: el `.env` apunta al host
`mysql`, que sólo resuelve dentro de la red Sail. La suite se corre **dentro del contenedor**
`knx-api-tmp` (puerto 8002), con la base aislada:

```bash
docker exec -e DB_DATABASE=electrobertels_knx_testing knx-api-tmp \
  sh -lc 'php artisan test Modules/Knx/tests/Feature/OpenApiContractTest.php'
```

`phpunit.xml` fija `claesen_analytics_web_testing`; la BD `electrobertels_knx_testing` ya existe en el
MySQL del stack y es la que mantiene la suite aislada de otros checkouts.

## Avisos

- `package-lock.json` tiene drift preexistente (`name` del paquete + bump `source-map-js` 1.2.1→1.2.2).
  No es de KNX: queda FUERA de los commits y NO se revierte.
