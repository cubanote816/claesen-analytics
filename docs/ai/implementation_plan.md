# Plan de Resolución de Hallazgos - Fase 1A (Adopción Safety)

Este documento detalla cómo se resolverán los 6 hallazgos detectados en la re-auditoría del repositorio `claesen_api_web_oficial`, asegurando la exactitud de los datos y la integración correcta de la infraestructura.

## User Review Required

> [!IMPORTANT]
> **GO Requerido:** Revisa las soluciones propuestas para cada hallazgo y responde a las dos "Open Questions" planteadas al final del documento. No modificaré código hasta que confirmes la lógica de negocio.

---

## 1. Plan de Resolución por Hallazgo

### [P1] Denominador de Adopción (User::count)
*   **Problema:** `User::count()` es un parche temporal que deforma la métrica.
*   **Solución:** A la espera de la definición de negocio (ver Open Questions), modificaremos `SafetyAdoptionMetricsService::getEnabledUsersCount()` para que aplique el filtro real (ej. usuarios con rol X, o usuarios que pertenezcan a ciertos departamentos). Si aún no hay regla, lo dejaremos bloqueado devolviendo solo usuarios con roles operativos confirmados, en lugar de toda la base de datos.

### [P1] Comando no registrado en el módulo
*   **Problema:** El scheduler fallará porque `AggregateSafetyAdoptionMetricsCommand` no está en el `SafetyServiceProvider`.
*   **Solución:**
    *   **[MODIFY]** `Modules/Safety/Providers/SafetyServiceProvider.php`: Se añadirá la clase del comando al array de `$commands` en el método `register()`.

### [P1] Desfase del Widget (Hoy vs Ayer)
*   **Problema:** El cron corre a la 1:00 AM procesando "ayer", pero el widget consulta "hoy" (resultando en 0 casi siempre).
*   **Solución:**
    *   **[MODIFY]** `Modules/Safety/Filament/Widgets/SafetyAdoptionOverviewWidget.php`: Se ajustará la lógica para que las tarjetas consulten y muestren explícitamente los datos de `$yesterday` (ej. "Inspecciones Ayer", "MAU al cierre de Ayer"). Las métricas diarias deben leerse a día vencido.

### [P2] Mezcla de Inspecciones e Incidentes
*   **Problema:** El rollup junta todo bajo `inspections_completed`, inflando el KPI.
*   **Solución:**
    *   **[MODIFY]** `InspectionController.php`: El payload ya inyecta `'type' => $validated['type']` en los metadatos del evento (`inspection` o `incident`).
    *   **[MODIFY]** `SafetyAdoptionMetricsService.php`: Se separará el conteo en dos rollups distintos: `inspections_completed` y `incidents_reported`.
    *   **[MODIFY]** `SafetyAdoptionOverviewWidget.php`: Se mostrarán en tarjetas separadas.

### [P2] Fallo de Unicidad en MySQL por `NULL`
*   **Problema:** El índice único en `safety_adoption_daily_rollups` permite duplicados porque `project_id` permite `NULL`.
*   **Solución:**
    *   **[MODIFY]** Migración `2026_06_22_100001_create_safety_adoption_daily_rollups_table.php`: Cambiaremos `$table->string('project_id')->nullable()` a `$table->string('project_id')->default('GLOBAL')`. Así el índice compuesto de MySQL sí prevendrá estrictamente los duplicados globales.

### [P2] Ausencia de Tests (Red de Seguridad)
*   **Problema:** No hay pruebas automáticas para la agregación, purga y eventos.
*   **Solución:**
    *   **[NEW]** `Modules/Safety/tests/Feature/SafetyAdoptionMetricsTest.php`: Se programarán pruebas que simulen el guardado de una inspección, la generación del evento, la corrida del comando agregador (verificando que no cuente doble y separe incidentes), y la purga de eventos > 90 días.

---

## 2. Open Questions (Respuestas Necesarias)

> [!WARNING]
> 1. **Inspecciones vs Incidentes:** ¿Deseas que los **incidentes** cuenten como "acción relevante" para definir si un usuario es "Activo" (MAU/DAU), o el MAU debe calcularse única y exclusivamente sobre las inspecciones de trabajo enviadas?
>
> 2. **Usuario Habilitado:** Confirmo que `User::count()` fue solo un parche técnico. ¿Cuál es la regla de negocio que define hoy a un usuario habilitado para Safety? (Ej. *"Todos los usuarios con rol project_manager y admin"*, o *"Usuarios vinculados a un empleado activo"*).
