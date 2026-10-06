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

- [ ] **U1** `POST /documents` — subida, idempotencia `clientId`, `supersedes`, `mimeType`/`pages`.
- [ ] **U2** `code` de `POST /projects` con la restricción de la ruta de lectura → `422`.
- [ ] **U3** `GET /employees?role=office` — `{id, name, shortName}` de los activos.
- [ ] **U4** `GET`/`PUT /projects/{code}/plans/{documentId}/markers` (por documento, `carryOverFrom`).
- [ ] **U5** `GET /projects/{code}/boards` + importador del worklist.
- [ ] **U6** Documentos (módulo + handoff) y memoria.

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

_(se completa por unidad de trabajo)_
