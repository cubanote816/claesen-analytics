# Model routing — cuándo delegar y a qué modelo

> Política de enrutamiento de modelos para sesiones de IA en este proyecto.
> No cambia el modelo de la sesión principal (eso solo lo hace el usuario con `/model`).
> Define cuándo delegar una subtarea vía `Agent` y con qué modelo.

---

## Regla general

La sesión principal corre en el modelo que eligió el usuario (por defecto, Sonnet) y así se queda para el trabajo rutinario de ticket: implementar, editar, correr tests, commitear. **No delegar por delegar** — delegar tiene costo de contexto (el subagente arranca frío) y solo se justifica cuando el tipo de tarea calza con uno de los casos de abajo.

## Cuándo delegar y a qué

| Situación | Acción | Modelo |
|-----------|--------|--------|
| Búsqueda/exploración de código ("dónde está X", "qué archivos referencian Y") de más de ~3 queries | `Agent` con `subagent_type: Explore` | el que trae el agente por defecto (no forzar) |
| Debugging con causa no obvia, ya se agotaron las hipótesis simples | `Agent` con `subagent_type: general-purpose` o `Plan` | `model: opus` |
| Decisión de arquitectura con trade-offs reales (ej. diseño de un sprint nuevo, elegir entre dos enfoques con impacto a largo plazo) | `Agent` con `subagent_type: Plan` | `model: opus` |
| Trabajo de ticket normal (implementar, fix acotado, tests, refactor dentro de alcance ya definido) | Sin delegar, sesión principal | el de la sesión (no forzar cambio) |
| Tarea mecánica y acotada dentro de un subagente ya delegado (ej. generar un test boilerplate a partir de un patrón dado) | Sub-delegar si el propio subagente lo decide | `model: haiku` |

## Por qué no hay subagentes custom todavía

Los tipos ya disponibles (`Explore`, `Plan`, `general-purpose`, `claude-code-guide`) cubren los casos reales vistos en este proyecto (Laravel/Filament, módulos ReadOnly, sprints por ticket Linear). Crear un subagente especializado (ej. uno atado al flujo de tickets Linear, o uno para migraciones SQL Server → mirror) es prematuro hasta que un patrón se repita lo suficiente como para justificar el mantenimiento de una definición propia en `.claude/agents/`.

**Señal para reconsiderar:** si una misma secuencia de pasos (mismo tipo de investigación, mismo checklist) se delega 3+ veces en sprints distintos con el mismo prompt reescrito a mano cada vez, vale la pena promoverla a un subagente dedicado. Hasta entonces, usar los tipos genéricos con el `model` override de la tabla de arriba.

## Notas

- El parámetro `model` en `Agent` acepta `sonnet`, `opus`, `haiku`, `fable` y tiene precedencia sobre lo que traiga definido el tipo de agente.
- Un subagente delegado no ve el contexto de la conversación — el prompt debe ser autocontenido (archivos, líneas, qué se descartó ya). Ver reglas generales de uso de `Agent` en las instrucciones de sistema.
- Esta política es orientativa, no un gate obligatorio de protocolo — no requiere ticket Linear para aplicarse ni para modificarse.
