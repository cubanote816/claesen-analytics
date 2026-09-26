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
| K2 | Clientes y proyectos: `/clients`, `/clients/{id}`, `/projects`, `/projects/{code}`, `/stats` | ⏳ |
| K3 | Dossier: `/projects/{code}/devices`, `/projects/{code}/activity` | ⏳ |
| K4 | Entrada de campo: `/notifications`, `ack`, `ack-all` | ⏳ |
| K5 | Planificación: `/technicians`, `GET/PUT/DELETE /planning` | ⏳ |
| K6 | Conflictos: lista/detalle/`PATCH` con histórico y `409 address_in_use` | ⏳ |
| K7 | Zonas: estado **derivado en servidor** + `zonesNotReady` | ⏳ |
| K8 | Documentos (URLs firmadas) y `POST /reports` en cola + `/reports/exports` | ⏳ |
| K9 | Fichas funcionales y pruebas de aceptación | ⏳ |
| K10 | (Opcional) `GET /events` SSE | ⏳ |

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

## Entorno local

- BD propia: `electrobertels_knx` (aislada de los demás stacks).
- App servida en `http://localhost:8002` cuando se levante su stack.
- Mail a Mailpit (el `.env` de un checkout que apunta al tenant real trae `MAIL_MAILER=microsoft-graph` y entonces no llega ningún correo — ver `docs/qa-test-users.md`).

## Decisiones abiertas

1. **Alta de personas** desde Kantoor (arriba).
2. ¿El aviso de zona no preparada bloquea `PUT /planning` o solo advierte? (§1.6 recomienda warning estructurado; el front ya avisa en cliente).
3. ¿Fichas funcionales y pruebas se validan con negocio antes de exponerlas? (`ROADMAP.md` §9.2; el front ya las tiene hechas).
