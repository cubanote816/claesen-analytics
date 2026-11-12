# CLA-669 — "Send now" for approved campaigns without a send date

Linear: CLA-669 (In Progress). Branch `fix/cla-669-campaign-send-now` from `origin/main`, worktree `.worktrees/cla-669`.
Approved campaigns with `scheduled_at` NULL are never sent; add an explicit, race-safe "Send now" action. Approval itself never sends.

## Specs

- S1. An approved campaign with no `scheduled_at` shows a "Send now" action, with a confirmation, on the campaign view page and in the campaign list.
- S2. Confirming it claims the campaign atomically (`approved` → `sending`) and dispatches `ExecuteCampaignJob` exactly once. A second click or a concurrent claim dispatches nothing.
- S3. The action is not available for: campaigns with a send date; campaigns in any status other than `approved`; campaigns that another, non-cancelled campaign uses as its follow-up (also after the parent queued it); users without approval rights (`admin`, `campaign_manager`; `super_admin` via Gate::before).
- S4. The send-date helper text (EN and NL) describes the real behavior. The current text is wrong: "Leave empty to send immediately after approval. Time is Europe/Brussels."
- S5. Existing behavior holds: the scheduler still ignores campaigns without a date, and follow-ups still dispatch their child.
- S6. Approve (view page and list) never returns a 500 when the workflow refuses the transition (`DomainException`, e.g. "Campaign #1: commercial campaigns require a valid preference category before approval. Edit template '1. Eerste Kennismaking (Audit)' to assign one."): it shows a danger notification with that message and the campaign keeps its status. Successful approvals behave as before.

## Tasks

- T1. [S1-S5] Model/policy guards, view + list actions, EN/NL texts, `SendNowCampaignTest`, docs (`module-contracts.md`, `handoff.md`). Route: RED inline, GREEN worker (context backstop). Commit: pending. Evidence: `SendNowCampaignTest` 18 passed; Mailing suite 274 passed / 2 skipped; native review approved (lineage review-b432781f44392477).
- T2. [S6] Catch workflow `DomainException` in the Approve action (view + list), EN/NL notification title, tests. Route: inline. Commit: pending (same CLA-669 commit). Evidence: RED 3 failed with DomainException; GREEN `CampaignApproveErrorTest` 4 passed; Mailing suite 278 passed / 2 skipped. RDD assess: medium, no escalation, no review due.

## Log

- L1. User: "me explica cual es la logica de funcionamiento de https://backoffice.claesen.local/campaigns/create" → diagnosis found approved unscheduled campaigns never send.
- L2. User: "si adelante con la opcion 2" (send on approval). Rejected after finding it would immediately send follow-up children, which must be approved without a date before a parent can select them.
- L3. User: "si ve con la 4" (separate "Send now" action). User GO on the presented plan: "go".
- L4. Lost-claim UI test rewritten: Livewire re-fetches the record, so a campaign claimed after render hides the action (no dispatch, status unchanged). The true race is covered by model tests (two stale copies: only one claim wins; a campaign that gained a date cannot be claimed).
- L6. Independent verify (gentle-ai-verify) found a P1: `mailing:dispatch-followups` sets the parent's `followup_dispatched_at` when it queues the child, but the child stays `approved` until the worker runs; the old `followup_dispatched_at IS NULL` filter re-exposed "Send now" and the job accepts `sending`, so it would double-send without the follow-up audience. Rule tightened: `isFollowUpChild()` ignores `followup_dispatched_at`; S3 reworded; test `test_hidden_while_the_follow_up_job_is_queued` replaces `test_visible_when_follow_up_was_already_dispatched`.
- L7. Native review (lineage review-b432781f44392477) found 2 CRITICAL, candidate-caused findings (R3, R4): a queue-publication failure after the claim stranded the campaign in `sending` with no job and no way to retry. Bounded correction: `Campaign::dispatchImmediateSend()` claims, dispatches via `Bus::dispatch`, and on any throwable releases the claim (`sending` → `approved`) and rethrows; both actions report the error and show `notifications.send_now_failed`. Test `test_queue_failure_releases_the_claim_so_the_send_can_be_retried`.
- L5. Worktree needed `public/build` (gitignored Vite manifest) copied from the main checkout for Filament page tests; not part of the change.
- L8. User reported a 500 when approving campaign #1 on localhost:8001 (main checkout, pre-existing Approve code): "DomainException - Internal Server Error / Campaign #1: commercial campaigns require a valid preference category before approval. Edit template '1. Eerste Kennismaking (Audit)' to assign one." Decision: "sumalo a CLA-669".
- L9. S6 narrowed to Approve: Submit and Cancel are only visible when `canTransitionTo()` allows the move, and Livewire re-fetches the record before running an action, so their `transitionTo()` cannot throw; only Approve adds checks (template linked, commercial preference category) beyond the state machine.
