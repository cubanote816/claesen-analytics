# Project Startup Protocol — Always On

Before answering, planning, editing files, running commands, creating tickets, reviewing code, or making commits, read in this order:

1. `CLAUDE.md`
2. `handoff.md`
3. `docs/ai/README.md`
4. The active module document indicated by `handoff.md` or `docs/ai/README.md`

After reading context, respond briefly with:

- current branch;
- active ticket, if any;
- active module;
- relevant risks;
- next recommended step;
- whether file edits are allowed yet.

Workflow:

1. Confirm or create a Linear ticket before any file edit.
2. Move the ticket to `In Progress`.
3. Present a plan before editing.
4. Wait for explicit user approval.
5. Implement only the active ticket.
6. Run relevant checks/tests.
7. Update project memory.
8. Create a dedicated commit.
9. Report the commit hash.
10. Wait for technical GO before marking Linear as `Done`.

Never print, copy, summarize, or commit secrets.

Do not read full contents of:

- `.env`
- `.env.*`
- credentials
- tokens
- dumps
- backups
- `storage/logs`
- `vendor`
- `node_modules`
- `bootstrap/cache`

If one environment variable is required, read only that variable safely and never print its value.

Project rules:

Mailing:
- Do not start Phase 3 until 4–6 weeks of production data exist.
- Do not touch MAI-026 unless management approved an external ESP.
- Use `MarketingCampaignInterface`, never direct `MicrosoftGraphMailer`.
- Use CTR/CTOR, not opens, as primary KPI.
- Never send without approval.
- Never send to suppressed contacts.
- `mailing_message_events` is append-only.
- Commercial email must include `List-Unsubscribe`.

Safety:
- SQL Server legacy data is read-only.
- Never mutate Cafca legacy models.
- Authorization must be resource-based.

Website:
- Respect Website handoff.
- Preserve WebP/media flow.
- Do not break GitHub Actions webhook assumptions.

If documents conflict:
1. Report the conflict.
2. Verify against current code.
3. Verify against Linear ticket.
4. Propose a documentation correction.
5. Do not invent state.

Do not edit files until explicit GO.
