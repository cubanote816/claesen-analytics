# KNX-4 — Contrato OpenAPI del módulo + test de contrato anti-drift

- **Rama:** `electrobertels/knx-api`. Worktree: `/home/totti/claesen/electrobertels-knx-api`.
- **Linear:** KNX-4 (In Progress). Engram mirror: `odd/knx-openapi-contract/tasks`.
- **Origen:** conversación sobre el relay local como canal agent-to-agent. El relay es un
  timbre (texto plano, sin acuse, sin versión, sin correlación); el contrato de forma debe
  vivir en un artefacto versionado.
- **Precedente:** `docs/api/website-v1-openapi.yaml` +
  `Modules/Website/tests/Feature/OpenApiContractTest.php` (patrón probado de la fase F3).

## Qué se hizo

- `docs/Knx/openapi.yaml` — OpenAPI 3.0.3 de las **52 rutas** `api/v1/knx/*`: auth, sesión,
  dashboard, clientes, proyectos, documentos (incluida la subida multipart y la descarga
  firmada), marcadores, cuadros, empleados, planificación, conflictos, zonas, notificaciones,
  informes, fichas/pruebas, SSE y el grupo `field` completo.
- `Modules/Knx/tests/Feature/OpenApiContractTest.php` — chequeo **bidireccional**: toda ruta
  documentada existe y toda ruta registrada `api/v1/knx/*` está documentada. Si se agrega una
  ruta sin documentar (o al revés), el test falla; un `.md` no puede hacer eso.
- `docs/Knx/handover-frontend.md` y `docs/Knx/knx-kantoor-backend.md` apuntan al spec.

## Decisiones

1. **Spec a mano, no generado.** No hay generador instalado (`dedoc/scramble` no está en
   composer). Generarlo es un follow-up; por ahora el test de contrato es lo que evita el drift.
2. **Describe lo que existe, no prescribe.** No se tocaron rutas ni payloads.
3. **El backend es el único escritor del spec**, igual que con las copias del handover: dos
   fuentes de verdad es cómo se pisan dos sesiones.

## Aceptación

- [x] El test de contrato pasa y falla si el spec y las rutas se separan.
- [x] `Modules/Knx/tests` en verde, Pint limpio en el test.
