# AGENTS.md — Project Rules

This repository uses persistent AI harnesses.

Before any task, read:

1. `CLAUDE.md`
2. `handoff.md`
3. `docs/ai/README.md`
4. The active module document

The detailed Antigravity startup rule lives at:

`.agents/rules/00-project-startup.md`

Follow:

- `docs/ai/project-protocol.md`
- `docs/ai/context-map.md`
- `docs/ai/module-contracts.md`
- `docs/ai/testing-checklists.md`
- `docs/ai/production-readiness.md`
- `docs/ai/code-review-rubric.md`
- `docs/ai/known-risks.md`
- `docs/ai/prompt-templates.md`
- `docs/ai/commands-runbook.md`
- `docs/ai/handoff-strategy.md`

No ticket, no edits.
No plan, no edits.
No approval, no edits.
No tests/checks, no closure.
No memory update, no closure.
No commit, no Done.

Reglas:
- No modificar código de aplicación.
- No leer/copiar secretos.
- No incluir `.env`.
- No incluir `.agent/skills` ni cambios no relacionados.
- Archivos en UTF-8 limpio.
- Presenta plan antes de editar.
- Espera GO antes de implementar.

---

## Cierre de cada ticket KNX (obligatorio)

> **Ámbito:** este repositorio es un monorepo (CAFCA Intelligence Hub) con varios módulos. Esta
> regla aplica **solo al trabajo del módulo Knx** (equipo Linear `KNX`); los demás módulos no la
> siguen.

Cuando cierres un ticket KNX (commit hecho, checks en verde, Linear actualizado con el hash),
guardá en Engram una observación con título `KNX cierre: <KNX-NN> <resumen corto>` y este cuerpo:

- **Qué**: lo implementado, en 1–3 líneas.
- **Dónde**: repo, rama y hashes de los commits.
- **Verificación**: resultado crudo de los checks, una línea por check (y si fue contra el mock o
  contra :8002).
- **Decisiones**: decisiones tomadas o pedidas al dueño (o "ninguna").
- **Pendiente**: lo que queda abierto o bloqueado (o "nada").

No edites nada dentro de `~/knx/orquestador/`: lo mantiene el orquestador con el dueño.
