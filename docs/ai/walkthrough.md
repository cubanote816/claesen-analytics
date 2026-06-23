# Walkthrough: Corrección Final de Hallazgos (Adopción Safety)

Este documento detalla el estado definitivo de la Fase 1A tras resolver el bloqueante del `project_id`, ajustar el denominador exactamente a las reglas de control de acceso reales del módulo, y reemplazar las pruebas simuladas por Feature Tests funcionales.

## Fixes Finales Aplicados

> [!SUCCESS]
> **Fase 1A Estable:** El código abordó la última ronda de QA. Se corrigió el error de capa de persistencia `P0`, se ancló la lógica del denominador a `EnsureSafetyAccess` y se documentó el test real del endpoint.

### 1. Resolución de Integridad de Datos (P0 `project_id`)
*   **Fix de Null Constraint:** Se corrigió un posible bloqueo durante la agregación nocturna. Aunque la migración nueva forzó la columna `project_id` a `NOT NULL` con valor predeterminado `'GLOBAL'`, la firma de la función `storeRollup` pasaba explícitamente `null`. Se actualizó la firma y la llamada a `updateOrCreate` para inyectar explícitamente `'GLOBAL'` si el `$projectId` es `null`, garantizando que la agregación de `active_users_7d`, `active_users_30d` y `friction_events_count` funcione a la perfección con el índice único de MySQL.

### 2. Denominador "Usuario Habilitado" (P1)
*   **Fix de Lógica de Negocio:** La aproximación anterior que restaba roles de backoffice fue completamente reemplazada por una regla estricta: **el denominador usa exactamente los mismos permisos que el middleware `EnsureSafetyAccess`**.
*   **Código:** El Servicio ahora cuenta a los usuarios que contengan alguno de los roles `['project_manager', 'super_admin', 'admin']` (las únicas personas que físicamente podrían llegar a enviar una inspección). Esto brinda una cifra irrefutable para la gerencia sin suposiciones ni "parches".

### 3. Tests de Idempotencia Reales (P2)
*   **Fix de Pruebas Mockeadas:** El test de conflictos (409) ya no inserta datos directamente en base de datos de manera simulada. El caso de prueba `test_idempotency_conflict_creates_friction_event_and_does_not_double_count()` fue reescrito desde cero para:
    1. Ejecutar un request HTTP POST real contra el endpoint `/api/v1/safety/inspections` de la API.
    2. Comprobar la respuesta HTTP 201 y la grabación de `inspection_completed`.
    3. Simular un fallo de red: reenviar el mismo payload idéntico y comprobar el `HTTP 200` y asegurar que **no se duplique** la métrica de adopción (cero eventos nuevos de completion ni fricción).
    4. Simular manipulación / conflicto: reenviar la misma `idempotency_key` con un payload alterado, comprobando que retorne `HTTP 409` y ahora sí genere **exactamente 1** evento de `inspection_payload_conflict`.

Todos los archivos han sido actualizados en la ruta `claesen_api_web_oficial` local y el artefacto de validación está sincronizado en `docs/ai/`.
