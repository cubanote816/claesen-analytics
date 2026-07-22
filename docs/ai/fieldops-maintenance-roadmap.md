# Roadmap maestro de mantenimiento FieldOps

> Fuente canónica de continuidad para el programa de mantenimiento y el futuro portal Claesen-Client.
> Última actualización: 2026-07-22 — CLA-268 en progreso.

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
| Fase 3 — Solicitudes de clientes | CLA-268 | 🚧 En progreso | Dominio inicial implementado; conversación y ciclo cliente aún incompletos |
| Fase 4 — Portal React Claesen-Client | Ticket/épica por crear | ⬜ Plan aprobado, no iniciado | PWA cliente completa en repositorio independiente |
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

## Fase 3 — CLA-268 en progreso

### Implementado actualmente, todavía sin commit dedicado

- Modelo `FoMaintenanceRequest`.
- Migración `2026_07_22_000007_create_maintenance_requests_table.php`.
- Estados de solicitud.
- Snapshot de instalación y posición estable.
- API inicial tenant-safe.
- Capacidad `can_report`.
- Recurso Filament inicial.
- Conversión idempotente a orden.
- Sincronización de resolución y cancelación desde la orden.
- Notificaciones iniciales al reportante.

Los cambios viven en un worktree compartido con modificaciones concurrentes. Para cerrar CLA-268 se debe revisar, testear y preparar el commit usando paths explícitos; no incluir `.gitignore`, `tmp/*` ni cambios ajenos al ticket.

### Validación ya realizada

| Suite focal | Resultado |
|---|---|
| `ClientReportedMaintenance` | 2 pruebas, 11 aserciones |
| `MaintenanceWorkOrderAuditNotification` | 6 pruebas, 27 aserciones |
| `MaintenanceWorkOrder` | 10 pruebas, 50 aserciones |
| Regresión FieldOps | 227 pruebas pasan; 78 fallos preexistentes por contaminación `RoleAlreadyExists` |

La suite amplia debe ejecutarse en un único proceso o de forma serial. No lanzar varios paths contra la misma base MySQL `testing`, porque compiten durante `RefreshDatabase`.

### Pendiente para cerrar CLA-268

- Conversación tipo ticket entre cliente y backoffice.
- Adjuntos reales con almacenamiento privado y streaming autorizado.
- Notas internas separadas de la conversación pública.
- Selección y contexto de cuadros eléctricos además de luminarias.
- Confirmación de resolución por el cliente.
- Reapertura controlada con auditoría.
- Invitación y activación de contactos cliente.
- Intake guiado con IA, siempre mockeado en tests y sin autoridad sobre tenancy.
- Pruebas específicas de mensajes, adjuntos, notas, estados, autorización y BOLA.
- Revisión técnica, commit dedicado y cierre de CLA-268 en Linear.

## Fase 4 — Portal Claesen-Client

Esta fase empieza únicamente después de cerrar CLA-268.

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
| CLA-268 no tiene commit dedicado | No iniciar Fase 4; aislar paths y cerrar el ticket primero |
| Conversación, adjuntos y reapertura incompletos | Completar en Fase 3 con pruebas de dominio y BOLA |
| Suite FieldOps contaminada por `RoleAlreadyExists` | Ejecutar focales en limpio y suite amplia serial; no mezclar el arreglo del harness con CLA-268 |
| Worktree compartido con cambios concurrentes | No stagear por glob; revisar `git diff --name-only` y usar paths explícitos |
| Infraestructura del hostname no provisionada | Verificar `client.claesen-verlichting.be` durante Fase 5 |
| IA de intake puede producir datos incorrectos | Tratarla como asistencia; validar entradas y nunca usarla como autoridad de tenancy |

## Orden de ejecución obligatorio

1. Mantener este roadmap y sus enlaces canónicos actualizados.
2. Separar los cambios de CLA-268 de modificaciones concurrentes.
3. Completar conversación, adjuntos, notas internas, cuadros, confirmación, reapertura, contactos e intake IA.
4. Añadir y ejecutar las pruebas específicas de CLA-268.
5. Obtener GO técnico, crear el commit dedicado y cerrar CLA-268 en Linear.
6. Crear el ticket/épica del portal.
7. Crear el repositorio Claesen-Client y su memoria enlazada.
8. Construir el mockup completo y solicitar aprobación visual.
9. Integrar el portal aprobado con la API.
10. Ejecutar Fase 5 y preparar producción.

## Próximo paso exacto

Continuar exclusivamente con CLA-268 en este backend. Antes de editar más código, inventariar los paths del ticket frente al worktree compartido; después implementar el primer bloque pendiente como una unidad testeable: conversación pública + notas internas separadas + adjuntos privados y autorizados. Añadir pruebas tenant/BOLA y de visibilidad interna/pública, ejecutarlas de forma serial y actualizar este roadmap con el resultado. No crear todavía el ticket ni el repositorio Claesen-Client.

## Registro de continuidad

| Fecha | Ticket | Cambio | Commit |
|---|---|---|---|
| 2026-07-22 | CLA-267 | Fase 0 cerrada | Backend `7758583`; Claesen-Sport `d7606bc` |
| 2026-07-22 | CLA-266 | Fase 1 cerrada | `2f093b3` |
| 2026-07-22 | CLA-271 | Fase 2 cerrada | Backend `91380dd`; Claesen-Sport `2ce9dcf` |
| 2026-07-22 | CLA-268 | Dominio inicial en progreso; memoria maestra creada | Pendiente |
