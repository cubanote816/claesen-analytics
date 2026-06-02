# Estrategia de handoff — CAFCA Intelligence Hub

> Cómo mantener la memoria del proyecto entre sesiones de IA.
> Última actualización: 2026-06-02 (DOCS-AI-001 / CLA-105)

---

## Principio

El handoff no es documentación estática — es el estado vivo del proyecto. Debe reflejar lo que es verdad ahora, no lo que fue verdad hace tres semanas.

---

## Jerarquía de documentos

```
CLAUDE.md                              ← REGLAS PERMANENTES + estado macro de sprints
handoff.md  (raíz)                     ← ESTADO GLOBAL VIVO del proyecto
docs/ai/README.md                      ← índice de harnesses, lectura recomendada
docs/Mailing/mailing-platform-master.md   ← estado técnico completo de Mailing
docs/website-sprint-handoff.md            ← estado del sprint Website
docs/safety-sprint-linear-tickets.md      ← mapa de tickets Safety (cerrado)
docs/ai/                               ← harnesses operativos
```

**Jerarquía de verdad** (en caso de conflicto):
```
Código fuente + ticket Linear  >  handoff.md  >  CLAUDE.md  >  docs específicos
```

---

## `handoff.md` raíz — rol y estructura

`handoff.md` es el único documento de estado global. Existe uno solo en la raíz del repositorio.

**Contenido obligatorio:**

```markdown
# Handoff — CAFCA Intelligence Hub

## Estado actual
- Sprint activo: [nombre del sprint]
- Rama actual: [nombre de rama]
- Último ticket cerrado: [TICKET-ID] / CLA-[N] — [título] — commit [hash]
- Próximo ticket: [TICKET-ID] — [título]

## Módulos activos
| Módulo | Estado | Documento específico |
|--------|--------|---------------------|
| Mailing | [estado] | docs/Mailing/mailing-platform-master.md |
| Website | [estado] | docs/website-sprint-handoff.md |
| Safety  | Completado | docs/safety-sprint-linear-tickets.md |

## Bloqueantes actuales
- [descripción del bloqueante] — ver docs/ai/known-risks.md

## Próximo paso recomendado
[descripción del próximo paso]

## Cambios recientes
| Fecha | Ticket | Acción |
|-------|--------|--------|
| [fecha] | [TICKET] / CLA-[N] | [descripción] |
```

---

## Documentos específicos de módulo — rol

Los documentos de módulo contienen el contexto técnico profundo de cada sprint:

| Documento | Qué contiene |
|-----------|-------------|
| `docs/Mailing/mailing-platform-master.md` | Decisiones arquitectónicas, mapa completo de tickets MAI, reglas no negociables, migraciones, configuración |
| `docs/website-sprint-handoff.md` | Bugs corregidos, estado del sprint WEB, backfills pendientes |
| `docs/safety-sprint-linear-tickets.md` | Mapa de tickets SAF cerrados, reglas Safety, estructura del módulo |

**Estos documentos no se reemplazan.** Se actualizan cuando cambia contexto técnico relevante del módulo.

---

## Cuándo actualizar cada documento

### Al cerrar un ticket

**Siempre:**
1. Actualizar `handoff.md` — cambiar el "último ticket cerrado" y el "próximo ticket"

**Si cambió contexto técnico del módulo:**
2. Actualizar el documento específico del módulo (ej: `mailing-platform-master.md`)

**Si cambió el estado macro del módulo (sprint completo, fase cerrada):**
3. Actualizar `CLAUDE.md` — cambiar estado en la tabla de módulos

**Si cambió una regla permanente:**
4. Actualizar `CLAUDE.md` + `docs/ai/module-contracts.md`

### Al iniciar un sprint nuevo

1. Crear o actualizar el documento específico del sprint/módulo en `docs/`
2. Actualizar `handoff.md` — nuevo sprint activo, nueva rama
3. Actualizar `CLAUDE.md` — estado del módulo a "🚧 In Progress"

### Al cerrar un sprint

1. Marcar todos los tickets como Done en Linear
2. Actualizar `handoff.md` — sprint cerrado, próximo sprint
3. Actualizar `CLAUDE.md` — estado del módulo a "✅ Done"
4. Actualizar el documento del módulo con el estado final

---

## Protocolo al inicio de sesión

```
1. Leer CLAUDE.md        → reglas permanentes, estado macro
2. Leer handoff.md       → estado global vivo, próximo paso
3. Leer docs/ai/README.md → qué documento consultar según la tarea
4. Leer doc del módulo   → contexto técnico profundo del sprint activo
```

No asumir que la sesión anterior terminó limpiamente. Verificar siempre `handoff.md` para el estado real.

---

## Resolución de conflictos entre documentos

Si hay información contradictoria entre documentos:

1. **No inventar.** Reportar el conflicto al usuario.
2. Verificar el código fuente actual (`git log`, lectura de archivos).
3. Verificar el ticket Linear para la decisión tomada.
4. Proponer qué documento debe actualizarse para que sea consistente.
5. No actualizar nada hasta recibir confirmación.

Ejemplo de reporte de conflicto:
```
CONFLICTO DOCUMENTAL DETECTADO:
- CLAUDE.md dice: "Mailing: ✅ ~98%"
- handoff.md dice: "Sprint activo: Mailing Fase 3"
- Código: no existe ningún archivo de Fase 3

Probable causa: handoff.md no fue actualizado al cerrar Fase 2.
Propuesta: actualizar handoff.md para reflejar que Mailing está completado en Fase 0+1+2
y Fase 3 está en Backlog pendiente de datos reales.

¿Confirmas esta corrección?
```

---

## Lo que NO debe estar en handoff.md

- Código fuente o fragmentos de código
- Valores reales de variables de entorno
- Secretos, tokens o credenciales
- Descripción detallada de implementación (eso va en el documento del módulo)
- Historial de más de los últimos 5–10 tickets (usar `git log` para historia larga)

---

## Regla de unicidad

**Un solo `handoff.md` en la raíz.** No crear `handoff-mailing.md`, `handoff-website.md` ni otros archivos similares en la raíz. El estado de cada módulo/sprint está en sus documentos respectivos bajo `docs/`. `handoff.md` es solo el puntero global al estado actual.
