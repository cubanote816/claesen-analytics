# CLA-673 — "Start Mailing Campaign": approvers send now, others send to review

Linear: CLA-673 (In Progress). Branch `feat/cla-673-start-campaign-review`, stacked on `feat/cla-672-audience-preview-list` (40b5d96a), worktree `.worktrees/cla-669`.
The Prospects bulk action keeps sending immediately for approvers; marketers get a campaign in review; roles that cannot create campaigns do not see it.

## Specs

- S1. Approvers (`super_admin`, `admin`, `campaign_manager`) keep today's behavior: the selected prospects are mailed immediately (inline `ExecuteCampaignJob`).
- S2. Other users allowed to create campaigns (`marketer`) get a campaign in `review` instead: manual audience with the selected subscribed prospect ids, the chosen template snapshot, the description, `created_by` = them. Nothing is sent; the notification links to the campaign ("View campaign").
- S3. Users who cannot create campaigns do not see the action.
- S4. The existing guards (no prospects, all unsubscribed, no emails, partial unsubscribed warning) keep working for both paths.
- S5. A commercial template without a valid preference category on the review path shows a danger notification and creates nothing.
- S6. Who may send immediately is one policy ability (`CampaignPolicy::sendImmediately`), same roles as approval.

## Tasks

- T1. [S1-S6] `CampaignPolicy::sendImmediately`, ProspectResource bulk action split, EN/NL texts, `StartMailingCampaignTest`, docs. Route: worker (context backstop). Commit: pending. Evidence: RED against the old code 3 failed / 8 passed (the 8 pin existing behavior); GREEN `StartMailingCampaignTest` 11 passed, Prospects suite 185 passed, `CampaignWorkflowTest` 23 passed.

## Log

- L1. User: "si vamos a ir por esto Si querés, los siguientes pueden ser tickets aparte: los user con los roles permitio para el envio inmediato pueden seguir enviando campanas inmediatamente "Start Mailing Campaign" pero los de mas no, la envian a revision"
- L2. Plan: roles without campaign create permission no longer see the action. User GO: "go".
- L3. Worker could not run tests: Docker Desktop was down (no Sail). Code reviewed statically by the parent; FR translations added by the parent (Modules/Prospects/lang/fr/resource.php). RED was not observed before the code existed; run order after Docker is back: focused test, Prospects suite, CampaignWorkflowTest.
