# Feature documentation — Always On

Since CLA-718 (epic CLA-717).

Before you close a ticket that changes what users see or do (screens, buttons, fields, emails,
PWA or website flows, operated APIs or commands), run the skill
`.agents/skills/feature-documentation/SKILL.md`.

- The in-app user guide (`Modules/<Module>/docs/user-guide/{en,nl}.md`) changes in the same pull
  request as the feature. Conventions: `docs/ai/module-documentation.md`.
- A module without a guide yet gets a comment on its guide ticket instead of a partial guide.
- `docs/ai/production-inventory.md` changes when a module ships, is switched on or off, or gets
  its guide.
- Management reviews the Dutch text.
- The closure carries the `Docs:` line defined in the skill. No `Docs:` line, no Done.
