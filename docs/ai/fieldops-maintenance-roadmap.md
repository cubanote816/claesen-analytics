# Roadmap maestro de mantenimiento FieldOps

> Fuente canónica de continuidad para el programa de mantenimiento y el futuro portal Claesen-Client.
> Última actualización: 2026-07-24 — CLA-276 Done (Fase 5 cerrada en Linear); todo el programa de mantenimiento (Fases 0-5) tiene su código implementado y verificado. Queda como trabajo futuro, fuera de este programa: aplicar el runbook de infraestructura de producción en servidores reales y decidir el pipeline de CI/CD de Claesen-Client (ver `docs/ai/production-readiness.md`).

## Cómo usar este documento

Este roadmap conserva la arquitectura, las decisiones no negociables, el estado real, la evidencia y el siguiente paso del programa completo. Toda sesión que trabaje en mantenimiento FieldOps o Claesen-Client debe leer primero `CLAUDE.md`, `handoff.md` y este documento.

Claesen-Client no es una iniciativa independiente. Es la Fase 4 de este roadmap y no puede comenzar sin verificar el estado de CLA-267, CLA-266, CLA-271 y CLA-268.

Jerarquía de verdad para este programa:

1. Código actual y ticket Linear.
2. Este roadmap maestro.
3. `handoff.md` y `CLAUDE.md`.
4. `docs/ai/context-map.md` y `docs/ai/module-contracts.md`.

Si una sesión cambia el estado de una fase, debe actualizar este archivo y los punteros de memoria afectados en el mismo ticket. No se marca una fase como cerrada sin tests/checks, memoria actualizada, commit dedicado y cierre de Linear conforme al protocolo del proyecto.

## Arquitectura canónica

```text
Solicitud del cliente
        ↓ triage y conversión idempotente
Orden de trabajo
        ↓ asignación, ejecución, devolución y validación
Ejecución validada
        ↓ cierre transaccional
Histórico inmutable
```

| Capa | Entidad principal | Responsabilidad | Invariante |
|---|---|---|---|
| Solicitud | `FoMaintenanceRequest` | Intake del cliente, conversación, adjuntos y seguimiento público | No es una orden ni un registro histórico |
| Coordinación | `FoMaintenanceWorkOrder` | Asignación, planificación, ejecución y validación | Toda transición pasa por el servicio de dominio y deja auditoría |
| Auditoría operacional | `FoMaintenanceWorkOrderEvent` | Timeline append-only de actores y transiciones | No se actualiza ni elimina |
| Histórico | `FoMaintenanceRecord` | Evidencia validada del trabajo ejecutado | API y Filament son solo lectura |

La conversión `Solicitud → Orden` es idempotente mediante `work_order_id`. Cerrar o cancelar la orden sincroniza el estado público de la solicitud. La resolución validada crea o enlaza el histórico en la misma transacción; nunca se usa una solicitud abierta como histórico provisional.

## Decisiones funcionales y de seguridad

- `FoClient` y `Complex` proceden del bridge CAFCA; no se crean ni reasignan manualmente desde el portal.
- El backend deriva siempre `client_id`, instalación y posición física. Ningún payload del cliente se considera autoridad de tenancy.
- La autorización de clientes es fail-closed y pasa por membresías activas `fo_client_user`, `FieldOpsTenantService` y las policies del módulo.
- `can_report` habilita solicitudes, no escritura sobre infraestructura, órdenes internas ni históricos.
- Un activo sin cliente único o conectado a varios clientes no se expone a cuentas externas.
- Para luminarias, `luminaire_position_id` es el agregado físico estable a través de reemplazos.
- Solo empleados CAFCA vinculados a un `User` activo pueden recibir órdenes.
- Asignaciones, reasignaciones y transiciones son auditables y append-only.
- Las notificaciones operacionales se encolan after-commit, usan database/mail y respetan preferencias por canal.
- Conversación pública, notas internas y adjuntos deben ser superficies separadas con autorización explícita.
- La confirmación, reapertura y cancelación deben usar transiciones de dominio; no se permite editar estados arbitrariamente.
- El portal debe aislar sesión, caché y datos por usuario para evitar fugas BOLA entre clientes.
- SQL Server/Cafca permanece estrictamente ReadOnly.
- Un endpoint agregado/de listado sin parámetro de ruta ligado a un modelo (stats, resúmenes) **no** queda cubierto por `EnforceFieldOpsTenantAccess` — su autorización solo recorre parámetros de ruta que ya son una instancia Eloquent. Cualquier endpoint así debe aplicar `FieldOpsTenantService::scopeForUser()` explícitamente en el controller, sobre el `Builder` antes de materializar la colección, nunca filtrando la colección ya cargada (CLA-497).

## Estado de fases

| Fase | Ticket | Estado real | Salida |
|---|---|---|---|
| Fase 0 — Hardening del mantenimiento | CLA-267 | ✅ Done | Plan, orden, ejecución validada e histórico separados; cutover de Claesen-Sport |
| Fase 1 — Identidad y aislamiento | CLA-266 | ✅ Done | Membresías cliente, autorización fail-closed, BOLA y OAuth endurecidos |
| Fase 2 — Asignaciones y notificaciones | CLA-271 | ✅ Done | Asignación ejecutable, timeline append-only y notificaciones operacionales |
| Fase 3 — Solicitudes de clientes | CLA-268 | ✅ Done | Implementación `d0436df`, memoria `545b42e`, cierre Linear registrado |
| Fase 4 — Portal React Claesen-Client | CLA-275 | ✅ Done | Portal read-only, traducciones NL/EN/FR/DE y aislamiento validado |
| Fase 5 — E2E y producción | CLA-276 | ✅ Done | Validación BOLA/E2E, cancelación, WCAG 2.2 AA, alertas/métricas; infraestructura real de producción preparada pero sin aplicar (ver Cierre abajo) |

## Fases cerradas y evidencia

### Fase 0 — CLA-267

Entregado:

- histórico de mantenimiento de solo lectura;
- Claesen-Sport migrado de CRUD de históricos a órdenes asignadas;
- separación de planes, órdenes, validación e histórico;
- cierre excepcional auditado mediante `override_reason`.

Commits:

- Backend: `7758583` — hardening del flujo de órdenes.
- Claesen-Sport: `d7606bc` — cutover de la aplicación de terreno.

Validación registrada:

- focal: 22 pruebas, 102 aserciones;
- regresión integrada: 42 pruebas, 301 aserciones;
- FieldOps amplia: 209 pruebas pasan, 649 aserciones; 93 fallos preexistentes del harness;
- Claesen-Sport: build y 2 pruebas Vitest pasan.

### Fase 1 — CLA-266

Entregado:

- relación usuario–cliente y capacidades por membresía;
- autorización tenant fail-closed;
- protección BOLA en listados y acceso directo;
- hardening OAuth, CORS y Sanctum para el futuro portal.

Commit backend: `2f093b3`.

Validación registrada:

- aislamiento tenant: 5 pruebas, 40 aserciones;
- FieldOps relacionado: 38 pruebas, 117 aserciones;
- Core/Auth/provisioning: 28 pruebas, 55 aserciones;
- total focal: 71 pruebas, 212 aserciones.

### Fase 2 — CLA-271

Entregado:

- asignador y fecha de asignación vigentes;
- eventos append-only para lifecycle y reasignaciones;
- devolución para corrección con actor, fecha y motivo;
- notificaciones database/mail;
- centro de notificaciones en Claesen-Sport.

Commits:

- Backend: `91380dd`.
- Claesen-Sport: `2ce9dcf`.

Validación registrada:

- auditoría/notificaciones: 6 pruebas, 27 aserciones;
- órdenes de trabajo: 10 pruebas, 50 aserciones;
- build, Vitest y lint focal de Claesen-Sport en verde.

## Fase 3 — CLA-268 técnicamente completado

### Entregado

- `FoMaintenanceRequest`, estados y migración `2026_07_22_000007`.
- Snapshot inmutable de instalación y posición física estable para luminarias, además de contexto de cuadros eléctricos.
- API tenant-safe y BOLA fail-closed; `can_report` exige membresía activa con `can_view`.
- Conversación pública append-only, notas internas aisladas y adjuntos privados con streaming autorizado.
- Recurso Filament de triage con conversación, notas, adjuntos y conversión a orden.
- Conversión idempotente mientras existe orden vigente; pivot histórico para conservar múltiples órdenes tras reapertura.
- Sincronización de inicio, resolución y cancelación desde la orden.
- Confirmación de resolución y reapertura auditada por el cliente.
- Invitación de contactos con capacidades, activación one-time y código almacenado únicamente como hash.
- Intake guiado con IA, salida acotada, fallback seguro y sin autoridad sobre tenant, activo o workflow.
- Notificaciones database/mail encoladas after-commit para backoffice y reportante.

Commit de aplicación: `d0436df` (`CLA-268: complete client maintenance requests`). Se preparó con paths explícitos y excluyó `.gitignore`, `tmp/*` y cambios concurrentes ajenos.

### Validación ya realizada

| Suite focal | Resultado |
|---|---|
| `MaintenanceRequest` | 8 pruebas, 69 aserciones |
| `ClientReportedMaintenance` | 2 pruebas, 11 aserciones |
| `MaintenanceWorkOrderAuditNotification` | 6 pruebas, 27 aserciones |
| `MaintenanceWorkOrder` | 10 pruebas, 50 aserciones |
| Regresión relacionada FieldOps/Core | 53 pruebas, 243 aserciones |
| Regresión FieldOps completa | 237 pruebas pasan, 815 aserciones; 81 fallos preexistentes por contaminación `RoleAlreadyExists` |

La suite amplia debe ejecutarse en un único proceso o de forma serial. No lanzar varios paths contra la misma base MySQL `testing`, porque compiten durante `RefreshDatabase`.

### Cierre

- CLA-268 se movió a Done en Linear.
- La evidencia de commits y pruebas se registró al cierre.

## Fase 4 — Portal Claesen-Client

CLA-275 se creó después del cierre de CLA-268 y la aprobación del plan. El primer mockup interactivo existe en `/home/totti/Claesen-Client` con commit `9f2414b`. Tras la revisión funcional de Claesen-Sport y del backoffice, el segundo mockup evoluciona a un explorador de infraestructura de solo lectura: `cliente autorizado → complejo → terreno → estructura → marco → posición/luminaria`, con cuadros eléctricos en su contexto físico real y reporte contextual. La integración ya usa una proyección API exclusiva, sin campos internos ni mutaciones, y se valida con aislamiento BOLA. El portal soporta NL, EN, FR y DE; React Query separa caché por perfil autenticado.

### Arranque y repositorio

1. Crear un ticket/épica Linear del portal, enlazado a este roadmap.
2. Crear `/home/totti/Claesen-Client` como repositorio Git independiente.
3. Crear su propia memoria de proyecto y enlazar de vuelta a este roadmap maestro.
4. Construir primero un mockup interactivo de alta fidelidad.
5. Solicitar y recibir aprobación visual antes de integrar la API real.

### Stack y arquitectura frontend

- React + TypeScript + Vite.
- Tailwind CSS.
- PWA instalable.
- React Query para estado remoto.
- Modos de datos `mock` y `api`, intercambiables sin reescribir pantallas.
- Caché, persistencia y logout aislados por usuario/tenant.
- Identidad Claesen dark-first.
- Responsive para móvil, tablet y desktop.
- Idiomas NL, EN, FR y DE.

### Alcance funcional

- Invitación, activación y contraseña.
- Login/OAuth compatible con Sanctum y el origen configurado.
- Instalaciones, frames y posición exacta de luminarias/cuadros.
- Reporte guiado mediante asistente IA.
- Conversación con backoffice y adjuntos.
- Estado de solicitud y orden sin exponer datos internos.
- Confirmación de resolución o reapertura.
- Centro de notificaciones y preferencias.
- Estados offline/loading/error/empty accesibles.

### Dominio objetivo

El hostname objetivo del plan es `client.claesen-verlichting.be`. Antes de deploy se deben verificar DNS, TLS, reverse proxy, redirects OAuth, CORS, cookies y `SANCTUM_STATEFUL_DOMAINS`; el nombre documentado no equivale a infraestructura ya provisionada.

## Fase 5 — Validación integral y producción

- Pruebas BOLA cruzando al menos dos clientes y usuarios sin membresía.
- Pruebas backend de mensajes, adjuntos, notas internas y todas las transiciones.
- Pruebas React de componentes, integración y caché por usuario.
- Auditoría de accesibilidad WCAG 2.2 AA.
- E2E completo: `cliente → backoffice → trabajador → validación → cliente`.
- Validación de confirmación, cancelación y reapertura.
- DNS, TLS, CSP, CORS, Sanctum, cookies, colas y almacenamiento privado.
- Monitorización, alertas y runbooks operacionales.
- Métricas de recepción, asignación, primera respuesta, resolución, confirmación y reapertura.

### Entregado

- BOLA cruzando ≥2 clientes y usuario sin membresía, mensajes/adjuntos/notas/transiciones — cubierto por `MaintenanceRequestTest`/`ClientPortalInfrastructureTest` (backend, ya existente desde CLA-268 + extendido este ciclo).
- Cancelación de solicitudes (cliente o backoffice, pre-conversión), con `EnforceFieldOpsTenantAccess` corregido (le faltaba la ruta de cancelación en su allowlist fail-closed) — backend `cb33822`, UI `3e9893e`.
- Refactor de Claesen-Client en componentes aislados y testeables + toolchain Vitest/Testing Library/Playwright (no existía ninguno) — `c60e06b`/`f75aa80`, 52+ tests, caché por usuario verificada.
- E2E cliente → backoffice → validación → cliente: `luminaire_id` real extraído del endpoint de infraestructura (no asumido de un fixture) en el flujo completo de reporte — backend `3680602`, frontend `79b11f7`. Nota: es un E2E de integración a nivel backend/Playwright sobre el portal, no una prueba cruzando literalmente los tres repos (Claesen-Client + backoffice + Claesen-Sport) en un solo test de navegador.
- Auditoría WCAG 2.2 AA con `@axe-core/playwright`: contraste, `aria-label` faltante, `<html lang>` sin sincronizar y foco de diálogos sin gestionar (hallazgo manual, no detectable por axe) — `5fde0c0`.
- Alertas operacionales (`no_first_response`, `awaiting_confirmation`) + widget de métricas de ciclo de vida (recepción, primera respuesta, asignación, resolución, confirmación, tasa de reapertura) — `26110b5`.
- Artefactos de infraestructura de producción para `client.claesen-verlichting.be` (vhost nginx, `cors-map.conf`, runbook completo en `docs/ai/production-readiness.md`) — `03660ae`.

### Cierre

- CLA-276 se movió a Done en Linear el 2026-07-24, por decisión explícita del usuario, con comentario listando los 9 commits (backend + frontend).
- **Pendiente real, fuera de este ticket:** el runbook de infraestructura está preparado pero no aplicado — requiere acceso SSH a `sbapu03`/`prod-priv-01` y al proveedor de DNS, no disponibles durante el desarrollo. El pipeline de CI/CD de Claesen-Client tampoco existe todavía (a diferencia de Website). Ninguno de los dos bloquea el cierre de CLA-276 porque son ejecución operativa, no código; quedan documentados en `docs/ai/production-readiness.md` para cuando alguien con el acceso correspondiente los resuelva.

## Bloqueos y riesgos vigentes

| Riesgo o bloqueo | Estado / mitigación |
|---|---|
| Suite FieldOps contaminada por `RoleAlreadyExists` | Ejecutar focales en limpio y suite amplia serial; no mezclar el arreglo del harness con ningún ticket de dominio |
| Worktree compartido con cambios concurrentes | No stagear por glob; revisar `git diff --name-only` y usar paths explícitos |
| Infraestructura de `client.claesen-verlichting.be` preparada pero no aplicada | Requiere acceso SSH real a `sbapu03`/`prod-priv-01` y al DNS; runbook completo en `docs/ai/production-readiness.md`, artefactos en `infrastructure/nginx/sbapu03/` |
| Sin pipeline de CI/CD para Claesen-Client | Hoy el build se sincroniza a mano (`rsync`); decidir si se construye uno equivalente al de Website (GitHub Actions + webhook) |
| IA de intake puede producir datos incorrectos | Tratarla como asistencia; validar entradas y nunca usarla como autoridad de tenancy |
| ✅ Fix implementado, pendiente de commit (CLA-497, 2026-08-30): `MaintenanceRecordController::correctiveStats()`/`pendingClientReported()`/`clientReportedStatistics()` sin tenant-scope | Un `technician` (único rol interno sin `fieldops.view-all-clients`) veía agregados y PII (`contact_person`/`contact_phone`/`location_details`) de todos los clientes. Fix: `scopeForUser()` aplicado en la query de los 3 métodos; `hasBroadAccess()` (project_manager/admin/super_admin/etc.) sigue viendo todo por decisión vigente (CLA-377); `client` sigue bloqueado sin cambios. Tests dedicados en `MaintenanceRecordTenantScopeTest.php` (2 clientes reales, PII exacta con `assertDontSee` cruzado, shape exacto de respuesta vacía; para el actor amplio: `assertExactJson` sobre las dos respuestas agregadas globales de `correctiveStats()`/`clientReportedStatistics()`, y conteo + IDs + PII exacta por registro para `pendingClientReported()`). Confirmado además con QA real contra dev (tokens Sanctum reales, fixtures borrados al terminar) |

## Orden de ejecución obligatorio

1. Mantener este roadmap y sus enlaces canónicos actualizados.
2. ~~Revisar y aprobar visualmente el mockup de CLA-275~~ — hecho.
3. ~~Integrar el portal aprobado con la API tenant-safe, Sanctum y streaming privado~~ — hecho.
4. ~~Ejecutar pruebas React/accesibilidad, E2E e infraestructura de Fase 5~~ — hecho (código); infraestructura real sin aplicar, ver paso 5.
5. Aplicar el runbook de producción (`docs/ai/production-readiness.md`) en `sbapu03`/`prod-priv-01` y decidir el pipeline de CI/CD de Claesen-Client — el programa de mantenimiento (Fases 0-5) ya no bloquea esto; es trabajo operativo independiente.

## Próximo paso exacto

Todo el código del programa de mantenimiento (Fases 0-5, CLA-267/266/271/268/275/276) está implementado, testeado y cerrado en Linear. El único trabajo pendiente es operativo, no de desarrollo: alguien con acceso SSH a `sbapu03`/`prod-priv-01` y al DNS debe ejecutar el runbook de `docs/ai/production-readiness.md` (sección Claesen-Client) para provisionar `client.claesen-verlichting.be`, y decidir separadamente si Claesen-Client necesita un pipeline de CI/CD propio.

## Registro de continuidad

| Fecha | Ticket | Cambio | Commit |
|---|---|---|---|
| 2026-07-22 | CLA-267 | Fase 0 cerrada | Backend `7758583`; Claesen-Sport `d7606bc` |
| 2026-07-22 | CLA-266 | Fase 1 cerrada | `2f093b3` |
| 2026-07-22 | CLA-271 | Fase 2 cerrada | Backend `91380dd`; Claesen-Sport `2ce9dcf` |
| 2026-07-22 | CLA-268 | Memoria maestra creada | `49fde29` |
| 2026-07-22 | CLA-268 | Dominio completo implementado y validado | `d0436df`, `545b42e` |
| 2026-07-24 | CLA-276 | E2E de `luminaire_id` + refactor de Claesen-Client en componentes testeables | Backend `3680602`; frontend `79b11f7`, `c60e06b`, `f75aa80` |
| 2026-07-24 | CLA-276 | Cancelación de solicitudes cliente/backoffice | Backend `cb33822`; frontend `3e9893e` |
| 2026-07-24 | CLA-276 | Auditoría y fixes WCAG 2.2 AA | Frontend `5fde0c0` |
| 2026-07-24 | CLA-276 | Artefactos + runbook de infraestructura de producción (sin aplicar) | Backend `03660ae` |
| 2026-07-24 | CLA-276 | Alertas operacionales + widget de métricas de ciclo de vida | Backend `26110b5` |
| 2026-07-24 | CLA-276 | Fase 5 cerrada en Linear (Done) | — |
| 2026-07-22 | CLA-275 | Ticket creado; repositorio y mockup interactivo iniciados | `9f2414b` |
| 2026-08-30 | CLA-497 | Tenant-scope aplicado a `correctiveStats()`/`pendingClientReported()`/`clientReportedStatistics()`; `ClientReportedMaintenanceTest` corregido (actor sin `fieldops.view-all-clients` ya no asume acceso global); `MaintenanceRecordTenantScopeTest.php` nuevo (incluye `assertExactJson` agregado global para un actor amplio) + QA real contra dev con tokens Sanctum reales | Implementado y testeado (59/59, 347 assertions), pendiente de commit/push/deploy |
