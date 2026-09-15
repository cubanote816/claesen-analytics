# AI Harnesses — CAFCA Intelligence Hub

> Sistema de documentación operativa para sesiones de IA.
> Ticket: DOCS-AI-001 / CLA-105 | Rama: `main` | Creado: 2026-06-02

---

## Qué son los AI harnesses

Un AI harness es un documento operativo que proporciona a la IA contexto específico, reglas y protocolos para trabajar correctamente en este proyecto sin perder coherencia entre sesiones.

Este sistema reemplaza la necesidad de re-explicar el proyecto desde cero en cada conversación.

---

## Lectura obligatoria al iniciar una sesión

> Protocolo completo (orden de lectura, flujo ticket → plan → GO) → **`docs/ai/project-protocol.md`** — este README es solo el índice de qué documento consultar según la tarea. `AGENTS.md`/`.agents/rules/00-project-startup.md` referencian el mismo protocolo, no lo duplican.

Orden mínimo:

```
1. CLAUDE.md                          ← reglas del proyecto, estado macro (ahora ~150 líneas)
2. handoff.md                         ← estado global vivo (sprint activo, último ticket; ahora compacto)
3. docs/ai/README.md                  ← este archivo, orienta la sesión
4. Documento específico del módulo    ← según la tarea activa
```

Lectura opcional, solo a demanda (no cargar por defecto): `docs/ai/decisions-log.md` (historial de cierres técnicos Laravel 13) y `docs/ai/handoff-archive.md` (histórico completo de `handoff.md` previo a CLA-536).

### Documentos específicos por módulo

| Tarea / Módulo | Documento a leer |
|----------------|-----------------|
| Mailing (cualquier ticket MAI) | `docs/Mailing/mailing-platform-master.md` |
| Website (cualquier ticket WEB) | `docs/website-sprint-handoff.md` |
| Safety (cualquier ticket SAF) | `docs/safety-sprint-linear-tickets.md` |
| Contratos y reglas por módulo | `docs/ai/module-contracts.md` |
| **Tests y obligatoriedad de testing** | **`docs/ai/test-gate-harness.md` ← leer antes de Plan, Review y Done** |
| Tests técnicos por tipo de cambio | `docs/ai/testing-checklists.md` |
| Revisar código de un PR | `docs/ai/code-review-rubric.md` |
| Preparar deploy / staging | `docs/ai/production-readiness.md` |
| Migración a Laravel 13 (programa cerrado, consulta puntual) | `docs/ai/archive/laravel-13-readiness.md` |
| Historial de cierres técnicos Laravel 13 (CLA-515→532) | `docs/ai/decisions-log.md` |
| Riesgos y deuda técnica | `docs/ai/known-risks.md` |
| Comandos Artisan | `docs/ai/commands-runbook.md` |
| Prompts reutilizables | `docs/ai/prompt-templates.md` |
| Protocolo de trabajo | `docs/ai/project-protocol.md` |
| Estrategia de handoff | `docs/ai/handoff-strategy.md` |
| Cuándo delegar a subagentes y con qué modelo | `docs/ai/model-routing.md` |

---

## Índice de harnesses

| Archivo | Propósito |
|---------|-----------|
| `project-protocol.md` | Flujo obligatorio: ticket → plan → aprobación → implementar → commit → GO |
| `context-map.md` | Mapa real del proyecto: stack, módulos, rutas, jobs, providers, dependencias |
| `module-contracts.md` | Reglas no negociables por módulo (Mailing, Safety, Website, Cafca, etc.) |
| **`test-gate-harness.md`** | **Arnés obligatorio de testing: matriz, waiver, plantilla de cierre — leer antes de Plan/Review/Done** |
| `testing-checklists.md` | Checklists técnicos por tipo de cambio y módulo (referenciados desde el test-gate) |
| `production-readiness.md` | Checklist de staging y producción; migraciones, scheduler, smoke tests |
| `decisions-log.md` | Historial detallado de cierres técnicos por ticket (Laravel 13, CLA-515→532) — no es lectura obligatoria |
| `archive/laravel-13-readiness.md`, `archive/laravel-13-compatibility-matrix.md`, `archive/laravel-13-staging-certification.md` | Programa Laravel 13 (CLA-514) — núcleo cerrado; consultar solo si el ticket activo es CLA-523/525/530/531 |
| `code-review-rubric.md` | Cómo revisar un PR: prioridades, severidades, Testing Gate, reglas por módulo |
| `known-risks.md` | Riesgos abiertos, deuda técnica, bloqueantes y decisiones pendientes |
| `prompt-templates.md` | Prompts reutilizables con sección "Tests requeridos" obligatoria |
| `commands-runbook.md` | Todos los comandos Artisan con descripción, opciones y notas operativas |
| `handoff-strategy.md` | Cómo mantener y usar `handoff.md` y los documentos de módulo |
| `model-routing.md` | Cuándo delegar una subtarea vía `Agent` y con qué modelo (Sonnet/Opus/Haiku) — no requiere ticket |

---

## Relación con otros documentos de control

```
CLAUDE.md              ← reglas permanentes del proyecto y estado macro de sprints
handoff.md             ← estado global vivo (actualizar en cada cierre de ticket)
docs/ai/               ← harnesses operativos (este directorio)
docs/Mailing/          ← documento maestro de Mailing
docs/website-sprint-handoff.md  ← handoff activo de Website
docs/safety-sprint-linear-tickets.md ← mapa de tickets Safety
```

**Si hay conflicto entre documentos:** el código fuente + ticket Linear es la fuente de verdad. Ver `handoff-strategy.md` para el protocolo de resolución.

**Regla de tamaño (CLA-536):** `CLAUDE.md` y `handoff.md` deben mantenerse como reglas permanentes + estado vivo compacto — el historial detallado va a `docs/ai/decisions-log.md` / `docs/ai/handoff-archive.md`, que no son lectura obligatoria.

---

## Cuándo actualizar los harnesses

| Evento | Qué actualizar |
|--------|---------------|
| Cierre de ticket | `handoff.md` (estado global) + doc del módulo si cambió contexto técnico |
| Cambio de regla permanente | `CLAUDE.md` + `module-contracts.md` si afecta a un módulo |
| Nuevo riesgo descubierto | `known-risks.md` |
| Nuevo comando Artisan | `commands-runbook.md` |
| Nueva decisión arquitectónica | `context-map.md` + doc del módulo |
| Cambio de proceso | `project-protocol.md` |
