# Roadmap maestro de mantenimiento FieldOps

> Fuente canónica de continuidad para el programa de mantenimiento y el futuro portal Claesen-Client.
> Última actualización: 2026-07-22 — CLA-268 técnicamente completado en `d0436df`; cierre Linear pendiente.

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

## Estado de fases

| Fase | Ticket | Estado real | Salida |
|---|---|---|---|
| Fase 0 — Hardening del mantenimiento | CLA-267 | ✅ Done | Plan, orden, ejecución validada e histórico separados; cutover de Claesen-Sport |
| Fase 1 — Identidad y aislamiento | CLA-266 | ✅ Done | Membresías cliente, autorización fail-closed, BOLA y OAuth endurecidos |
| Fase 2 — Asignaciones y notificaciones | CLA-271 | ✅ Done | Asignación ejecutable, timeline append-only y notificaciones operacionales |
| Fase 3 — Solicitudes de clientes | CLA-268 | 🟡 Cierre Linear pendiente | Implementación y validación completas en `d0436df` |
| Fase 4 — Portal React Claesen-Client | Ticket/épica por crear | ⏸ Plan pendiente de aprobación | PWA cliente completa en repositorio independiente |
| Fase 5 — E2E y producción | Ticket por crear | ⬜ Pendiente | Validación integral, infraestructura y observabilidad de producción |

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

### Pendiente administrativo

- Registrar esta evidencia y el commit en Linear.
- Mover CLA-268 a Done.
- Presentar el plan de Fase 4 al usuario y esperar aprobación antes de crear el ticket/épica.

## Fase 4 — Portal Claesen-Client

Esta fase empieza únicamente después de cerrar CLA-268 y aprobar explícitamente el plan siguiente. La aprobación previa registrada para el roadmap general no autoriza crear todavía el ticket: el usuario pidió revisar el plan de implementación justo antes de ese paso.

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

## Bloqueos y riesgos vigentes

| Riesgo o bloqueo | Estado / mitigación |
|---|---|
| CLA-268 pendiente de cierre en Linear | Registrar `d0436df` y la evidencia; no crear aún el ticket de Fase 4 |
| Plan de Claesen-Client pendiente de aprobación | Presentarlo al usuario y detenerse antes de crear ticket/repositorio |
| Suite FieldOps contaminada por `RoleAlreadyExists` | Ejecutar focales en limpio y suite amplia serial; no mezclar el arreglo del harness con CLA-268 |
| Worktree compartido con cambios concurrentes | No stagear por glob; revisar `git diff --name-only` y usar paths explícitos |
| Infraestructura del hostname no provisionada | Verificar `client.claesen-verlichting.be` durante Fase 5 |
| IA de intake puede producir datos incorrectos | Tratarla como asistencia; validar entradas y nunca usarla como autoridad de tenancy |

## Orden de ejecución obligatorio

1. Mantener este roadmap y sus enlaces canónicos actualizados.
2. Cerrar CLA-268 en Linear con commit y evidencia.
3. Presentar el plan completo de Fase 4 al usuario.
4. Esperar aprobación explícita del plan.
5. Crear el ticket/épica del portal solo después de esa aprobación.
6. Crear el repositorio Claesen-Client y su memoria enlazada.
7. Construir el mockup completo y solicitar aprobación visual.
8. Integrar el portal aprobado con la API.
9. Ejecutar Fase 5 y preparar producción.

## Próximo paso exacto

Cerrar CLA-268 en Linear usando `d0436df` y la evidencia registrada. Inmediatamente después, presentar al usuario el plan de implementación de Claesen-Client y detenerse. No crear el ticket/épica, el repositorio ni archivos del portal hasta recibir aprobación explícita de ese plan.

## Registro de continuidad

| Fecha | Ticket | Cambio | Commit |
|---|---|---|---|
| 2026-07-22 | CLA-267 | Fase 0 cerrada | Backend `7758583`; Claesen-Sport `d7606bc` |
| 2026-07-22 | CLA-266 | Fase 1 cerrada | `2f093b3` |
| 2026-07-22 | CLA-271 | Fase 2 cerrada | Backend `91380dd`; Claesen-Sport `2ce9dcf` |
| 2026-07-22 | CLA-268 | Memoria maestra creada | `49fde29` |
| 2026-07-22 | CLA-268 | Dominio completo implementado y validado; cierre Linear pendiente | `d0436df` |
