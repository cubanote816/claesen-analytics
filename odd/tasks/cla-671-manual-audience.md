# CLA-671 — Manual audience selector for campaigns

Linear: CLA-671 (In Progress). Branch `feat/cla-671-manual-audience`, stacked on `fix/cla-670-approver-direct-approve`, worktree `.worktrees/cla-669`.
Audience type **Manual selection** had no prospect picker and `Campaign::resolveAudience()` threw for it; build the picker and resolve the stored selection.

## Specs

- S1. With audience type **manual**, the campaign form lets the user pick prospects (searchable by club name or email; label «name · sport · federation · region»; only subscribed prospects with an email; max 50 results per search) and the selection is required (at least one).
- S2. The selection is stored with the campaign in `audience_filters` as `{"prospect_ids": [...]}` (no migration) and is what the send uses (scheduled, Send now, A/B).
- S3. Unsubscribed and suppressed prospects are never mailed, as for the other audience types (resolver filter shared with `SegmentResolverService`).
- S4. "Preview audience" also works for manual and reports how many will actually receive it.
- S5. The campaign view shows the audience type and the recipient count.
- S6. Existing audience types (`all_subscribed`, `segment`) behave as before.

## Tasks

- T1. [S1-S6] `SegmentResolverService` manual resolution, `Campaign::resolveAudience()` MANUAL branch, `CampaignForm` selector + preview, `CampaignResource::infolist` audience entry, EN/NL texts, `ManualAudienceTest`, docs. Route: worker (context backstop). Commit: pending. Evidence: RED 13 failed / 2 passed (S6 regressions); GREEN `ManualAudienceTest` 15 passed; Mailing suite 299 passed / 2 skipped; after verify fixes `ManualAudienceTest` 19, Mailing suite 303 passed / 2 skipped.

## Log

- L1. User: "no veo el boton de enviar ahora y tampoco dodne seleccionar los destinatarios"
- L2. User choice on the broken manual audience: "Construir el selector ahora". GO on the presented plan: "go".
- L3. Out of scope: the Prospects bulk action "Start Mailing Campaign" keeps sending immediately outside the approval workflow (possible follow-up ticket).
- L4. Deviations accepted: infolist audience label uses the current locale; search escapes `%`, `_`, `\`; the count entry is labelled "Current audience size" / "Huidige omvang doelgroep" because it is the audience today, not what was sent (`total_count`). The Preview action itself has no UI test; its count path is tested through `SegmentResolverService::count()`.
- L5. Independent verify found: P1 `intval()` coerced malformed ids (`true`, `[2]`, `"2oops"`) into real prospect ids; P2 SEGMENT forwarded `prospect_ids` and could be narrowed; P2 the view count could 500 on malformed segment filters. Fixed: `SegmentResolverService::strictIds()` keeps only positive ints / digit strings; SEGMENT resolves `Arr::except(..., ['prospect_ids'])`; the infolist count catches InvalidArgumentException/DomainException and shows a placeholder. Four regression tests added (written after the fix, so no separate RED observed).
