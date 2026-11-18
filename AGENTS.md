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

Un ticket de Linear puede repartirse entre varios repos (backend, Kantoor, Veld). Cerrás solo TU parte:
- Si el ticket es solo de tu repo: pasalo a Done con el hash.
- Si lo comparte otro repo (título o descripción nombran Kantoor/Veld/oficina/campo, o otro repo
  todavía tiene trabajo): NO lo pases a Done. Dejá un comentario "Parte <repo> hecha: <hashes>"
  y que lo cierre el último repo que termine, tras la aceptación del ticket.

Guardá en Engram una observación con título `KNX cierre: <KNX-NN> [<repo>] <resumen corto>`
(`<repo>` = backend | kantoor | veld) y este cuerpo:
- **Qué**: lo implementado, en 1–3 líneas.
- **Dónde**: repo, rama y hashes de los commits.
- **Verificación**: resultado crudo de los checks, una línea por check (mock o :8002).
- **Decisiones**: decisiones tomadas o pedidas al dueño (o "ninguna").
- **Pendiente**: lo que queda abierto en tu repo o en otros (o "nada").
- **Linear**: "Done" o "comentado, sigue abierto por <repo>".

No edites nada dentro de `~/knx/orquestador/`: lo mantiene el orquestador con el dueño.
