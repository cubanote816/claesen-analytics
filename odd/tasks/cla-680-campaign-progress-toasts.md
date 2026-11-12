# CLA-680 — Floating, closable toasts while a campaign is sending and when it finishes

Linear: CLA-680 (In Progress). Branch `feat/cla-680-campaign-progress-toasts`, stacked on `feat/cla-677-campaign-finished-notification` (9539e813), worktree `.worktrees/cla-680`.
Persistent "sending" toast at launch plus a session-scoped, panel-wide poller that shows a closable "finished" toast; polling, not broadcasting.

## Specs

- S1. Launching a send ("Start Mailing Campaign" immediate path, "Send now") shows a persistent, closable toast: "Sending N emails in the background. You can keep working; we will let you know when it finishes." (EN/NL, and FR in the Prospects module).
- S2. A small panel-wide Livewire component (render hook) remembers the campaigns this user launched in the session and, only while any of them is still sending, checks their status every 5 s; it stops polling when none is pending.
- S3. When a tracked campaign reaches its final state, the user sees one floating, closable toast with the result (sent / failed / skipped) and a "View campaign" link, on whatever panel page they are; then it is removed from tracking.
- S4. Never shows other users' campaigns; never polls when nothing is pending; the CLA-677 bell notification stays as the permanent record.
- S5. No change to what is sent or to whom.

## Tasks

- T1. [S1-S5] Tracker (session), Livewire poller component + BODY_END hook, launch toasts, texts, tests. Route: worker (context backstop). Commit: pending. Evidence: RED 12/12 failed; GREEN `CampaignProgressWatcherTest` 13 passed; Mailing 355 passed / 2 skipped; Prospects 186 passed.

## Log

- L1. User: "tendriamos desde mi entender una mejor ui/ux si al envia una campana cerramos el modal, no bloqueamos la app y mostramos una notificacion flotante que el user pueda cerrar que indique el proceso de envio de la campana y notificar caundo termine"
- L2. Recommendation accepted (polling, not Reverb): "tienes mi go".
- L3. Parent review found that a launch on the same page did not make the watcher start polling until a reload (separate Livewire component). Fixed: launch points dispatch `campaign-send-tracked`, the watcher listens with `#[On]` and re-renders; test added. "Send now" toast has no N (no cheap audience count); cancelled tracked campaigns are forgotten silently.
