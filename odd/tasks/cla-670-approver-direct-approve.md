# CLA-670 — Approvers approve a draft campaign directly

Linear: CLA-670 (In Progress). Branch `fix/cla-670-approver-direct-approve`, stacked on `fix/cla-669-campaign-send-now` (5378b15e), worktree `.worktrees/cla-669`.
Approvers (`admin`, `campaign_manager`; `super_admin` via Gate::before) skip the review step; marketers keep it.

## Specs

- S1. On a draft campaign, an approver sees **Approve** (view page and list) and can approve it directly.
- S2. Approval from draft runs the same checks as from review (template linked, commercial preference category, snapshot recompute) and the same refusal notification.
- S3. A `marketer` cannot approve a draft (no action, policy refuses).
- S4. Review → approved and review → draft keep working as today.

## Tasks

- T1. [S1-S4] `CampaignStatus::allowedTransitions` (draft → approved), `CampaignPolicy::approve`, `Campaign::canBeApprovedBy`, tests in `CampaignWorkflowTest` + `CampaignApproveErrorTest`, docs. Route: inline. Commit: pending. Evidence: RED 4 failed (DomainException, approve hidden); Mailing suite 283 passed / 2 skipped.

## Log

- L1. User: "no comprendo el flujo actual de enviar a revision y luego aprovado y finalmente el envio si no es programado, esta pensado para el trabajo en equipo? por ejemplo el de marqueting que haga la propuesta y se lo mande al gerente? pero si lo esta creando un role super_admin no deberia tener este flujo se gun mi opinion es una friccion innecesaria"
- L2. User choice: "Aprobar directo desde borrador". GO on the presented plan: "go".
- L3. Two existing tests pin the old rule and are changed on purpose: `test_draft_cannot_transition_directly_to_approved`, `test_campaign_manager_cannot_approve_campaign_not_in_review` (used a draft).
