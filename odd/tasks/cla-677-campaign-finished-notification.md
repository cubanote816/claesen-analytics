# CLA-677 — Notify the user who launched a campaign when the send finishes

Linear: CLA-677 (In Progress). Branch `feat/cla-677-campaign-finished-notification` from `origin/main` (2e543e88), worktree `.worktrees/cla-677`.
Sends already run queued; the user only learns "Campaign started". Add one Filament database notification when the run reaches its final state.

## Specs

- S1. When a campaign run reaches its final state (completed or failed), the user who launched it receives a Filament database notification (bell) with the result: sent / failed / skipped counts and a link to the campaign.
- S2. Exactly one notification per campaign run, also when the send spans several chained job generations; none for intermediate generations.
- S3. Covers inline sends (Prospects "Start Mailing Campaign"), "Send now", scheduled sends (notify the creator/approver) and A/B (after the winner send).
- S4. No change to what is sent or to whom.

## Tasks

- T1. [S1-S4] Notification at the single terminal transition of `ExecuteCampaignJob` (all paths), recipient resolution, EN/NL texts, tests. Route: worker (context backstop). Commit: pending. Evidence: RED 10 failed / 1 passed; GREEN `CampaignFinishedNotificationTest` 14 passed; Mailing suite 342 passed / 2 skipped; `StartMailingCampaignTest` 11 passed.

## Log

- L1. User: "vi que se demoro un poco en el envio de la simulacion con solo 17 prospecto imagina con cientos. seria buena idea que se lance en segundo plano pero que se mantega la notificacion al user"
- L2. Explained: production already queues the send (local queue is `sync`); missing piece is the completion notice. Plan approved: "go".
- L3. Independent verify found: (P1) "Send now" did not pass the launcher, so the approver got the notice — fixed in `Campaign::dispatchImmediateSend()` (`userId: auth()->id()`); (P1) Filament's `sendToDatabase()` queues the notification, so a queue outage after the terminal transition lost it — now `notifyNow()`; (P2) the conditional final update kept `FAILED` when the failed() hook raced an active send, unlike before — the computed outcome now wins again. Three regression tests added (written after the fixes).
- L4. Native review (lineage review-fb163d4e1452a191) required one bounded correction: in that race the second notice broke S2 (exactly one). The race branch now only restores the computed status; the single notice is the one failed() already sent. Race test asserts exactly one notification.
