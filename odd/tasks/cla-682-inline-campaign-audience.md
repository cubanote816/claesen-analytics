# CLA-682 — Campaigns created by "Start Mailing Campaign" record their manual audience

Linear: CLA-682 (In Progress). Branch `fix/cla-682-inline-campaign-audience` from `origin/main`, worktree `.worktrees/cla-682`.
Inline campaigns default to `all_subscribed`, so the view shows "All subscribed / 2,378" for a 4-prospect send.

## Specs

- S1. The inline path creates its campaign with `audience_type = manual` and `audience_filters.prospect_ids` = the prospect ids it was dispatched with (ints).
- S2. The campaign view shows "Manual" and the matching current audience size.
- S3. What is sent and to whom is unchanged (the inline path keeps sending to its own prospect list).
- S4. Existing campaigns are not migrated (display only; documented).

## Tasks

- T1. [S1-S4] ExecuteCampaignJob inline campaign creation + tests + docs. Route: worker. Commit: pending. Evidence: RED 3 failed / 1 passed (audience all_subscribed, 3 vs 2); GREEN `InlineCampaignAudienceTest` 4 passed; Mailing suite 359 passed / 2 skipped.

## Log

- L1. Seen on production campaign #32 view: "All subscribed 2,378 4" for a send to 4 prospects.
- L2. User decision: "Puntos 1+2 y 3, dos tickets (recomendado)".
