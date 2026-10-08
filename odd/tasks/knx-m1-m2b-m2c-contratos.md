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
- [ ] **R2** KNX-51 — dirección de la obra (`siteAddress`), `PATCH /projects/{code}`.
- [ ] **R3** KNX-44 — habitaciones, alias, zona automática con los 8 checks, `roomId` en enlaces de
  cuadro, migración una-zona-por-habitación.
- [ ] **R4** KNX-45 — modelo de plano por revisión: `GET`/`PUT …/plans/{documentId}/rooms`.
- [ ] **R5** KNX-46 — ficha de habitación oficina `GET /projects/{code}/rooms/{id}/sheet`.
- [ ] **R6** KNX-47 — campo: `GET /field/projects/{code}/rooms`, `GET …/plans/{documentId}/model`.
- [ ] **R7** KNX-52 — cuadros y módulos oficina (alta/edición/confirmación, `origin`/`reviewState`).
- [ ] **R8** KNX-25 — campo: `GET /field/projects/{code}/boards` (lectura).
- [ ] **R9** KNX-53 — campo: `POST …/boards`, `POST …/boards/{boardId}/modules`,
  `409 slot_taken`, `boardId`/`moduleSlot`/`channel` en el registro de aparatos.
- [ ] **R10** KNX-54 — avisos generalizados (`kind`), `/me/notification-preferences`, email
  inmediato/digest con el transporte de Mailing.
- [ ] **R11** KNX-56 — mal funcionamiento (`lifecycle: malfunction`, ciclo validado, `POST /projects/{code}/issues`).
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
