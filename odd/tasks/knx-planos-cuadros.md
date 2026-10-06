# KNX-3 — Planos, marcadores, cuadros y empleados para Kantoor

- **Rama:** `electrobertels/knx-api`. Worktree: `/home/totti/claesen/electrobertels-knx-api`.
- **Linear:** KNX-3 (In Progress). Engram mirror: `odd/knx-planos-cuadros/tasks`.
- **Objetivo:** servir las rutas que el backoffice de oficina necesita para subir planos, marcar cuadros sobre el plano, listar cuadros y elegir responsable de proyecto, sin romper los contratos ya congelados.
- **Contrato (repo front, no se toca):** `electro-bertels-kantoor` rama `docs/backend-planos-contrato`:
  `docs/knx-pedidos-al-backend.md` (índice), `docs/knx-planos-subida-y-marcadores.md` (razón),
  `docs/BACKEND-API.md` §4.11. `GET /boards` propuesto en §4.3 (rama `feat/kantoor-plano-viewer`).
- **Doc del módulo:** `docs/Knx/knx-kantoor-backend.md`.
- **TDD:** no habilitado por configuración. Checks: `Modules/Knx` en verde, Pint limpio y
  **verificación HTTP real** en `:8002` (la suite no prueba que la ruta esté publicada).

## Decisiones tomadas (y su porqué)

1. **Límite de subida 50 MB, `413`.** Los planos son 1–3 MB y el `.knxproj` 4,8 MB; el zip de
   fotos de 38 MB entra. Un archivo mayor responde `413` con el envelope, nunca el `500` de PHP.
2. **Idempotencia por `clientId`: aceptada.** Un corte de red tras subir 3 MB y un reintento
   crean una revisión duplicada salvo que la clave decida. Mismo patrón que las escrituras de
   campo: `client_id` unique en `knx_documents`, `IdempotentWrite::run()` y relectura del ganador.
   Misma clave en otro proyecto → `422` (no se le devuelve a un proyecto el documento de otro).
3. **Identidad de cuadro = slug del worklist, como cadena externa.** `boardId` es
   `caja-1-alsb-leefgroep-gelijkvloers-glv` (columna `slug` en `knx_boards`, única por proyecto).
   `code` sigue siendo el código corto (`E10`/`E21`) que ya usan los aparatos. El slug mide 39
   chars y `code` es 32, por eso es columna aparte.
4. **`revision` de los marcadores: derivada del documento.** El conjunto ya cuelga de un
   documento —una revisión—, y ese documento ya tiene `revision`. Guardar una segunda copia sería
   una segunda fuente de verdad. El servidor no la valida; la UI compara.
5. **Módulos: identidad de instancia derivada del worklist.** Medido sobre el worklist real
   (160 filas, 5 cuadros, **13 módulos**): cada `(cuadro, número de pedido)` es un módulo y no hay
   reset de canal (A–H aparece una sola vez por tipo de módulo en este proyecto), así que el
   worklist **sí** está a granularidad de módulo. El importador asigna un `slot` = posición
   ordinal del módulo en el cuadro, en el orden del worklist (que sigue el plano de cuadros). Si
   en el futuro un mismo número de pedido se repite en un cuadro, el importador parte la serie
   cuando la letra de canal retrocede (A→A), que es la señal KNX de "otro módulo".
6. **El worklist se ingesta, no se sirve en caliente.** El backend no lee CSVs en cada request:
   un comando `knx:import-worklist <dir> <code>` puebla tablas propias. El endpoint lee la BD.
7. **Aislamiento de tenant en todas las lecturas.** Todo se resuelve por el código de proyecto del
   tenant; otro proyecto no ve nada.

## Tareas

- [x] **U1** `POST /documents` — subida, idempotencia `clientId`, `supersedes`, `mimeType`/`pages`. `c90e6a6`
- [x] **U2** `code` de `POST /projects` con la restricción de la ruta de lectura → `422`. `f6b3144`
- [x] **U3** `GET /employees?role=office` — `{id, name, shortName}` de los activos. `5af2b3c`
- [x] **U4** `GET`/`PUT /projects/{code}/plans/{documentId}/markers` (por documento, `carryOverFrom`). `694969e`
- [x] **U5** `GET /projects/{code}/boards` + importador del worklist. `b8232dd`
- [x] **U6** Documentos (módulo + handoff) y memoria.

## Criterios de aceptación (sobre HTTP real en `:8002`)

- `POST /documents` con un PDF de 3 páginas y ~2 MB → `201`; el listado lo muestra con tamaño,
  `pages: 3`, `mimeType` y el detalle con `url` firmada no nula.
- Subir con `revision` nuevo y `supersedes` → anterior `isCurrent: false`, nuevo `true`, en una
  transacción.
- `PUT .../markers` con 5 marcadores → `200`; `GET` devuelve lo mismo; otro proyecto no los ve.
- `GET /projects/000026/boards` con los cuadros, módulos, canales y habitaciones, con `id` de
  cuadro estable e igual al que espera el marcador.
- `GET /employees` lista los empleados de oficina activos.
- `code` con espacio en `POST /projects` → `422`.
- `Modules/Knx` en verde, Pint limpio, sin regresiones.

## Progreso / evidencia

- Suite completa del módulo: `Modules/Knx/tests` **229 passed / 1 skipped / 0 failed** (1290
  aserciones), corrida contra una base aislada `electrobertels_knx_testing` (`phpunit.xml` fija
  `claesen_analytics_web_testing`, que la comparte el stack de Claesen y quedó sin tabla
  `migrations` a mitad de sesión).
- Pint: los 34 ficheros de la unidad están limpios. El módulo **no** era Pint-limpio antes
  (`KnxProject.php`, `KnxZone.php`, `DashboardService.php`, `IdempotentWrite.php`, `KnxTenant.php`,
  `KnxZonesTest.php`, `KnxPlanningAssignment.php`) y no se tocaron.
- HTTP real en `:8002` (detalle en `handoff.md`): subida, supersesión, marcadores, cuadros
  (5/13/52/160, los números medidos), empleados y `code` inválido.
- El importador se corrió contra el worklist real de `000026`; quedó en la BD local (es dato real
  del proyecto). Los artefactos de prueba de la verificación (documentos 85/86 y el proyecto
  `HTTP-VERIFY`) se borraron.
- Hallazgo propio corregido antes de comitear U5: el detector de “módulo nuevo” contaba `A, A, B`
  (dos objetos en el mismo canal) como un módulo nuevo; ahora el reinicio es un canal que
  **retrocede** (A después de D), que es la señal KNX real. Los 13 módulos medidos se mantienen.
- Error de lectura propio corregido al empezar U5: había contado las **filas** por número de pedido
  como si fueran módulos (5× `JRA/S8.230.5.1`); en realidad son 13 módulos = 13 pares (cuadro,
  aparato). El worklist sí está a granularidad de módulo.

## Revisión nativa (RDD) — estado

Los commits se revisaron **por unidad de trabajo**, con un worktree ligado por commit (el proveedor
proyecta contra el `HEAD` del worktree, así que un commit intermedio no se puede aislar sin uno).

| Unidad | Linaje | Resultado |
|---|---|---|
| U2 (`f6b3144`) | `review-447bf002c82d5cd4` | **approved** + acknowledged, autoridad quemada. 2 hallazgos no bloqueantes. |
| U3 (`5af2b3c`) | `review-3198a2f6a558bbcd` | **approved** + acknowledged, autoridad quemada. 1 hallazgo no bloqueante. |
| U1 (`c90e6a6`) | `review-e0ad99591cefaf39` | reviewer + **refuter**: 3 hallazgos **CRITICAL** reales, corregidos y commitados en `da7ed94`. |
| U4/U5 | — | sin veredicto: el flujo del proveedor quedó inconsistente (binding de consentimiento expirado; proyección de repo completo; `lineage_created: false`). |

**Los tres hallazgos de U1 (todos `introduced` por `c90e6a6`) y su arreglo:**

1. `storeFile` casteaba a `string` el `false` que `putFileAs` devuelve sin lanzar cuando la escritura
   falla, y la transacción podía commitear un documento con `path` vacío. Ahora lanza.
2. Un `clientId` vacío se guardaba como valor real pero la relectura lo trataba como “sin clave”,
   así que un reintento con `""` esquivaba el replay y chocaba con el índice único → 500. Ahora
   `""` se normaliza a `null`.
3. El índice único de `client_id` era global mientras la relectura es por organización; ahora es
   `unique(organization_id, client_id)` y la relectura filtra explícitamente por tenant.

Tres tests de regresión nuevos; `Modules/Knx/tests` **232 passed / 1 skipped / 0 failed** (1299
aserciones) tras `da7ed94`.

**Bloqueante de la revisión:** el proveedor emitió la ruta `correction_plan_required` para U1, pero
`gentle_review_capture` rechazó el binding vigente como *“collectBinding is missing or stale for
current STATUS”* en los cuatro estados probados (sin commitear, corregido commiteado, sin corregir,
con y sin `workspaceRoot`), y `gentle_review advance` responde *“transition is unsupported:
capture-correction-plan”*. La corrección quedó aplicada y verificada fuera del linaje; el linaje
quedó además desanclado al limpiarse `/tmp` (recrearlo dio candidato vacío). No se usó RESET ni
RECOVER: son destructivos y requieren decisión explícita.

