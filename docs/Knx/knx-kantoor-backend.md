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
| **K0** | Módulo, 16 tablas, modelos, personas, envelope de errores, seed del mock | 🚧 en curso |
| K1 | Sesión: `POST /auth/login\|refresh\|logout`, `GET /me/session` | ⏳ |
| K2 | Clientes y proyectos: `/clients`, `/clients/{id}`, `/projects`, `/projects/{code}`, `/stats` | ⏳ |
| K3 | Dossier: `/projects/{code}/devices`, `/projects/{code}/activity` | ⏳ |
| K4 | Entrada de campo: `/notifications`, `ack`, `ack-all` | ⏳ |
| K5 | Planificación: `/technicians`, `GET/PUT/DELETE /planning` | ⏳ |
| K6 | Conflictos: lista/detalle/`PATCH` con histórico y `409 address_in_use` | ⏳ |
| K7 | Zonas: estado **derivado en servidor** + `zonesNotReady` | ⏳ |
| K8 | Documentos (URLs firmadas) y `POST /reports` en cola + `/reports/exports` | ⏳ |
| K9 | Fichas funcionales y pruebas de aceptación | ⏳ |
| K10 | (Opcional) `GET /events` SSE | ⏳ |

## Entorno local

- BD propia: `electrobertels_knx` (aislada de los demás stacks).
- App servida en `http://localhost:8002` cuando se levante su stack.
- Mail a Mailpit (el `.env` de un checkout que apunta al tenant real trae `MAIL_MAILER=microsoft-graph` y entonces no llega ningún correo — ver `docs/qa-test-users.md`).

## Decisiones abiertas

1. **Alta de personas** desde Kantoor (arriba).
2. ¿El aviso de zona no preparada bloquea `PUT /planning` o solo advierte? (§1.6 recomienda warning estructurado; el front ya avisa en cliente).
3. ¿Fichas funcionales y pruebas se validan con negocio antes de exponerlas? (`ROADMAP.md` §9.2; el front ya las tiene hechas).
