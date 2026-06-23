# Tareas de Resolución - Hallazgos Auditoría (Safety)

- `[x]` **Ticket SAF-ADOPT-FIX-001: Denominador y Separación de Métricas**
  - `[x]` Ajustar `SafetyAdoptionMetricsService` para calcular MAU/DAU solo con inspecciones (ignorar incidentes en este KPI).
  - `[x]` Separar conteo de `inspections_completed` e `incidents_reported`.
  - `[x]` Implementar heurística transitoria justificada para "usuario habilitado" (excluir roles de backoffice), documentando la regla.

- `[x]` **Ticket SAF-ADOPT-FIX-002: Infraestructura y Registro**
  - `[x]` Registrar `AggregateSafetyAdoptionMetricsCommand` dentro de `registerCommands()` en `SafetyServiceProvider`.
  - `[x]` Crear nueva migración de ajuste (`_alter_project_id_in_daily_rollups`) para hacer backfill a `GLOBAL` y forzar unicidad sin alterar la migración original.

- `[x]` **Ticket SAF-ADOPT-FIX-003: Dashboard y Claridad**
  - `[x]` Modificar `SafetyAdoptionOverviewWidget` para consultar `$yesterday`.
  - `[x]` Renombrar etiquetas para hacer explícito que los datos son "Cierre de ayer".

- `[x]` **Ticket SAF-ADOPT-FIX-004: Tests de Red de Seguridad**
  - `[x]` Crear `SafetyAdoptionMetricsTest` con pruebas para guardado normal, purga, conflictos `409` e idempotencia sin doble conteo.
