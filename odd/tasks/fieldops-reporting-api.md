# CLA-578 — FieldOps: Reporting & scheduling API (F6a)

**Rama:** `cla-578-fieldops-reporting-api` (desde `origin/main`, commit base `90a8962`)
**Repo:** `claesen_api_web_oficial`
**Ticket padre del rediseño:** fase F6a del rediseño UI/UX de FieldOps. Sub-issue pendiente: CLA-579 (checklist de werkorder, F6b).

## Objetivo

Dar soporte de API a tres pantallas del rediseño que hoy no tienen backend real: **Rapporten** (filtros, KPIs, export), **Kalender** (rango de fechas) y **Activa** (índice de armaturas). Sin romper los 540 tests existentes ni el contrato que consume el frontend hoy.

## Problema

- `/fieldops/maintenance-work-orders/history` y `/assigned` devuelven **todas** las filas, sin paginar y sin ningún filtro (fecha, complejo, técnico, tipo).
- No existe ningún endpoint de agregados: la pantalla Rapporten no puede mostrar KPIs reales.
- No existe export.
- `GET /fieldops/luminaires` **no existe** (405, la ruta solo acepta POST) — Activa no tiene de dónde leer.

## Alcance autorizado

Solo CLA-578. El checklist de werkorder (migración + modelo + endpoints) es CLA-579 y **no** se toca aquí.

## Restricciones

- **No editar `Modules/FieldOps/Services/FieldOpsTenantService.php`**: CLA-500 (sin mergear, en `origin`) lo reescribe (+109/−60). Este ticket solo lo consume.
- **No editar `MaintenanceRecordResource`**: CLA-561 lo está tocando.
- Rutas literales (`/stats`, `/export`) deben registrarse **antes** del wildcard `{workOrder}` (lección de CLA-405).
- Tests con `docker exec -u sail` (causa raíz de CLA-507).

## Decisión de scope de acceso (revisada durante la implementación)

El plan original decía aplicar `FieldOpsTenantService::scopeForUser()` a los agregados de work orders. **Se descartó tras leer el código**: `scopeForUser()` no tiene caso para `FoMaintenanceWorkOrder` (caería en `default => whereRaw('1=0')`), y añadirlo scopearía por `allowedClientIds()`, que está vacío para un técnico sin pivotes de cliente → **un técnico dejaría de ver sus propias órdenes asignadas**, una regresión real.

Decisión: extraer el scope **existente** (`super_admin`/`admin` ven todo; el resto solo `assigned_employee_id` propio) a un único método compartido por `assigned`, `history`, `stats` y `export`. Así los endpoints nuevos no pueden exponer nada que `history` no exponga ya. El índice nuevo de luminarias sí usa `scopeForUser(..., Luminaire::class)`, que sí está soportado.

## Tareas

- [x] T1 — `WorkOrderQueryRequest`: validación compartida de filtros (from, to, complex_id, client_id, employee_id, maintenance_type_id, status[], per_page).
- [x] T2 — `WorkOrderReportingService`: query base con scope de acceso + bucket (open/closed) + filtros; agregados de `stats`.
- [x] T3 — `MaintenanceWorkOrderController`: `assigned()`/`history()` con filtros (+ paginación en history), `stats()`, `export()` (CSV streamed).
- [x] T4 — `IndexLuminaireRequest` + `LuminaireController::index()` con `scopeForUser` y filtros.
- [x] T5 — Rutas nuevas (aditivas, antes del wildcard).
- [x] T6 — Tests de feature: filtros, forma paginada, scope de acceso, stats, export, índice de luminarias.
- [x] T7 — Suite FieldOps completa en verde + actualizar CLAUDE.md.

## Criterios de aceptación

- `history` sin parámetros sigue devolviendo `data` como array (el frontend actual no se rompe); con paginación añade `meta`/`links`.
- Un técnico sin permiso de broad access no ve órdenes de otro técnico en ningún endpoint nuevo.
- Un técnico sigue viendo sus propias órdenes asignadas aunque no tenga pivotes de cliente (no regresión).
- `GET /fieldops/luminaires` respeta el scope de tenant.
- 540 tests previos siguen en verde.

## Checks

- `docker exec -u sail claesen_api_web_oficial-laravel.test-1 php artisan test --testsuite=Modules --filter=FieldOps`

## Progreso

- Rama creada desde `origin/main` (checkout principal estaba en `cla-507`, árbol limpio y ya pusheada — nada que perder).
- T1–T6 implementados. `--filter=WorkOrderReportingTest`: **13/13 en verde, 51 assertions** (observado 2026-09-22).
- Gotcha encontrado al escribir los tests: `fo_maintenance_types.code` es único y `FoMaintenanceWorkOrderFactory` crea un tipo nuevo (`preventive`) por cada orden — cualquier test que cree 2+ órdenes revienta con 1062 salvo que se pase `fo_maintenance_type_id` explícito. Resuelto con un helper `typeId()` que reutiliza el tipo por código.
- T7 observado 2026-09-22: `--testsuite=Modules --filter=FieldOps` → **553 passed, 2269 assertions, 0 fallos** (baseline previa 540/2218; el delta son exactamente los 13 tests nuevos). CLAUDE.md actualizado con la fila de CLA-578.
- **Pendiente: GO técnico del usuario antes de commitear** (paso 7 del flujo de CLAUDE.md). Nada comiteado todavía.
