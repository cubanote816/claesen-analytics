# Rúbrica de code review — CAFCA Intelligence Hub

> Cómo revisar un PR en este proyecto. Prioridades, severidades y reglas específicas por módulo.
> Última actualización: 2026-06-02 (DOCS-AI-001 / CLA-105)

---

## Cómo iniciar un review

1. Leer el ticket Linear asociado al PR para entender el objetivo.
2. Revisar el diff completo antes de hacer comentarios.
3. Ejecutar los tests del módulo afectado.
4. Seguir las prioridades en el orden indicado abajo.
5. Reportar cada hallazgo con severidad y ubicación exacta.

---

## Prioridades de review (orden de atención)

```
P1 — Seguridad          ← siempre primero
P2 — Correctitud        ← bugs, lógica incorrecta, race conditions
P3 — Autorización       ← permisos, políticas, exposición de datos
P4 — Idempotencia       ← operaciones que deben ser seguras de repetir
P5 — Duplicados         ← código duplicado, lógica ya existente
P6 — Tests faltantes    ← cobertura de ramas críticas
P7 — Calidad            ← legibilidad, naming, estructura
```

---

## Severidades

| Nivel | Descripción | Acción requerida |
|-------|-------------|-----------------|
| **BLOCKER** | Introduce bug, vulnerabilidad o viola contrato del módulo | No mergear hasta resolver |
| **CRITICAL** | Riesgo alto: race condition, autorización incorrecta, dato sensible expuesto | Resolver antes del GO |
| **MAJOR** | Lógica incorrecta, test faltante en ruta crítica | Resolver antes del GO |
| **MINOR** | Mejora de calidad, naming, refactor conveniente | Puede mergearse con nota |
| **INFO** | Observación o sugerencia sin impacto | No bloquea |

---

## Formato de hallazgo

```
[SEVERIDAD] archivo:línea — descripción del problema
Impacto: qué puede salir mal
Sugerencia: cómo corregirlo (si aplica)
```

Ejemplo:
```
[CRITICAL] Modules/Mailing/Jobs/ExecuteCampaignJob.php:45
— No se verifica supresión antes del envío
Impacto: puede enviar a contactos con hard_bounce o spam_complaint
Sugerencia: llamar SuppressionService::isSuppressed($prospectId) antes de procesar
```

---

## Checklist general de review

### Seguridad

- [ ] No hay secretos ni credenciales en el código (API keys, tokens, passwords)
- [ ] No hay SQL raw con inputs del usuario sin binding (`DB::statement("... $input")`)
- [ ] No hay inyección de comandos (funciones `exec`, `shell_exec`, `system`)
- [ ] No hay exposición de stack traces o mensajes de error internos al cliente
- [ ] Los inputs de usuario están validados en Form Requests o controllers

### Correctitud

- [ ] La lógica implementada coincide con la descripción del ticket
- [ ] Los casos edge están cubiertos (null, vacío, límite de valores)
- [ ] Los estados de error se manejan explícitamente
- [ ] Los jobs tienen `$tries` y `$backoff` definidos
- [ ] Las transacciones de DB se usan donde se necesita atomicidad

### Autorización

- [ ] Los endpoints protegidos tienen middleware `auth:sanctum`
- [ ] Los recursos privados usan `Gate::authorize()` con la policy correcta
- [ ] `project_manager` no puede acceder a recursos de otros usuarios
- [ ] Los endpoints públicos son intencionalmente públicos (no por omisión)

### Idempotencia

- [ ] Los commands y jobs que se ejecutan periódicamente son seguros de repetir
- [ ] Las inserciones con riesgo de duplicado usan `firstOrCreate` / `updateOrCreate` / `idempotency_key`
- [ ] El procesamiento de eventos (bounces, clicks) no duplica registros

### Tests

- [ ] Existe al menos un test para el happy path
- [ ] Existe al menos un test para el caso de error / rechazo más probable
- [ ] Los tests de autorización cubren `allow` y `deny` para los roles relevantes
- [ ] Los tests no dependen de orden de ejecución (cada test limpia su estado)

---

## Reglas específicas por módulo

### Mailing — señales de alerta en review

```
[BLOCKER] Llamada directa a MicrosoftGraphMailer sin pasar por MarketingCampaignInterface
[BLOCKER] Envío sin verificar SuppressionService
[BLOCKER] Envío sin verificar campaign->status === approved
[CRITICAL] Correo comercial sin header List-Unsubscribe
[CRITICAL] Correo sin header X-Mailing-Token
[CRITICAL] Modificación de registros en mailing_message_events (tabla append-only)
[CRITICAL] Uso de open rate como criterio de decisión (KPI inválido)
[CRITICAL] Ciclo de follow-up A→B→A creado sin advertencia
[MAJOR] A/B winner seleccionado por aperturas en lugar de CTR
[MAJOR] Falta --dry-run en nuevo command periódico
[MAJOR] Nueva supresión de tipo hard_bounce o spam_complaint que no es permanente
```

### Safety — señales de alerta en review

```
[BLOCKER] Acceso a archivo sin Gate::authorize()
[BLOCKER] Uso de disco 'public' para fotos o PDFs de Safety
[CRITICAL] project_manager con acceso a recursos de otros usuarios
[CRITICAL] Respuesta de API que incluye datos de inspecciones ajenas
[MAJOR] Test de Safety fuera de Modules/Safety/tests/
[MAJOR] Factory de Safety fuera de Modules/Safety/database/factories/
```

### Cafca / ERP — señales de alerta en review

```
[BLOCKER] save(), update(), create(), delete() sobre modelo con conexión sqlsrv
[BLOCKER] ID del ERP tratado como integer en vez de string
[CRITICAL] Falta trim() en asignación de ID de ERP
[CRITICAL] Relación Eloquent directa entre modelo sqlsrv y modelo MySQL
```

### Website — señales de alerta en review

```
[CRITICAL] API pública exponiendo proyectos con published = false
[CRITICAL] URL del media sin conversión WebP (usando imagen original)
[MAJOR] NotifyAstroFrontendJob con event_type incorrecto (no 'backend_update')
[MAJOR] Campos internos (user_id, created_by) expuestos en JSON de API pública
```

### General — señales de alerta en review

```
[BLOCKER] Clase Filament de V3/V4 en lugar de V5
[CRITICAL] Variable de entorno leída directamente con env() fuera de config files
[MAJOR] Lógica de negocio en controller (debe estar en service)
[MAJOR] Job sin $tries definido
[MINOR] Comment que explica "qué" en lugar de "por qué"
```

---

## Checklist de cierre de review

- [ ] Todos los BLOCKERs y CRITICALs resueltos
- [ ] Tests pasan: `php artisan test --testsuite=Modules --filter=<Modulo>`
- [ ] No hay secrets en el diff (verificar con `git diff | grep -i "key\|token\|password\|secret"`)
- [ ] El commit sigue el formato `TICKET-ID / CLA-YY: resumen`
- [ ] Pronto el GO técnico si todo pasa
