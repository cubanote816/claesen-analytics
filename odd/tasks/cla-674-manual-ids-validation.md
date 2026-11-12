# CLA-674 — Reject malformed prospect ids in the manual audience form

Linear: CLA-674 (In Progress). Branch `fix/cla-674-manual-ids-validation`, stacked on `feat/cla-673-start-campaign-review`, worktree `.worktrees/cla-669`.
The manual audience selector stops coercing values with `intval()`; invalid values are a validation error.

## Specs

- S1. The form only accepts positive integer ids of existing prospects; anything else (e.g. "2oops", true, nested arrays, unknown ids) is a validation error and no campaign is created.
- S2. Valid selections are stored as ints, exactly as today.

## Tasks

- T1. [S1-S2] `CampaignForm` select validation + dehydration, tests in `ManualAudienceTest`. Route: inline. Commit: pending. Evidence: RED 7 failed (all malformed cases created a campaign) / 19 passed; GREEN `ManualAudienceTest` 26 passed; Mailing suite 328 passed / 2 skipped.

## Log

- L1. Native review advisory on CLA-671 (`R3-form-id-coercion`, `CampaignForm.php:150`). User: "si vamos a ir por esto"; plan GO: "go".
