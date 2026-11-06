# CLA-655 — RBFA sync reconciles with existing clubs, fill-only

Branch `fix/cla-655-rbfa-reconcile` (worktree `.worktrees/cla-655`, from `origin/main` c19d692).
Deadline: before the scheduled run 2026-12-01 03:30 (`prospects:sync-rbfa-graphql`).

## Specs

- S1: When no prospect has the club's external_id, match an existing legacy row (same federation, `external_id` NULL, `is_tester` 0) by VAT digits (both present), or by exact normalized name (upper, trimmed, collapsed spaces) when either side has no VAT. Exactly one candidate required. The legacy row's `external_id` is NOT written (unsubscribe token = `hmac(id . external_id)` must stay stable).
- S2: Two or more legacy candidates: create nothing, record the ambiguity (log + sync event).
- S3: Existing rows (matched by external_id or by S1) are fill-only: only columns that are NULL or empty are set; values already present are never overwritten. Same rule for existing locations. User decision: "Solo completar campos vacíos". User requirement: "no se pisen los cambios".
- S4: Region. New rows: postal code → region; else discovery province (carried from `RbfaGraphqlSource` into `NormalizedClub` as an optional hint) → region; else "Overige". Existing rows: region changes only from "Overige" to a real region, never into "Overige".
- S5: A NEW location whose email already exists on a location of another prospect is skipped entirely and recorded; the prospect is still saved. For an EXISTING location, a colliding incoming email is not written (recorded as "email skipped", not "location skipped") while its other empty fields are still filled (fill-only). Collision with a location of the same prospect is silent. Prospect + locations are written in one DB transaction.
- S1b: VAT comparison canonicalizes Belgian enterprise numbers: keep digits only, then drop leading zeros, so `BE0421.753.822`, `0421753822` and `421753822` are the same VAT (PHP check and SQL narrowing alike).
- S6: `persistAll` logs every failure (external_id + message). The RBFA command records skipped locations and ambiguous matches as sync events.
- S7: Brussels (`SyncBrusselsClubsCommand`, `persistAll`) and AFT (`SyncAftClubsCommand`) persistence keep working.
- S8: Running the same sync twice creates zero new rows and changes nothing the second time. Requirement: "antes del 1 de diciembre hay que arreglar el emparejamiento del sync RBFA, o se vuelven a crear duplicados."

## Tasks

| ID | Specs | Route | Commit |
|----|-------|-------|--------|
| T1 | S1, S2, S3, S5, S6 (ClubPersister) | worker, test-first | implemented, uncommitted |
| T2 | S4 (province hint through source + persister) | worker, test-first | implemented, uncommitted |
| T3 | S7, S8 (command-level, idempotent re-run), docs | worker + parent check | implemented, uncommitted; pending parent check |
| T4 | S1b, S5 (verifier findings: VAT leading zero, existing-location collision wording) | worker, test-first | implemented, uncommitted |

## Log

- L1 (user, 2026-11-06): "vamos a implementarlo siempre que al sincronizarlos la proxima vez no se pisen los cambios CLA-654 (espera tu GO) y los bounces con 404. Y antes del 1 de diciembre hay que arreglar el emparejamiento del sync RBFA, o se vuelven a crear duplicados."
- L2 (user, 2026-11-06): chose "Solo completar campos vacíos" — existing clubs are never overwritten.
- L3: Do not adopt external_id onto legacy rows (unsubscribe token stability). Out of scope: 2026-27 series catalog, Excel review merges, `http://null` websites.
- L4 (worker, 2026-11-06): T1–T3 implemented, uncommitted. Prospects suite 145 passed. `ClubPersister::reconcile()` returns `{prospect, ambiguous, skipped_locations}`; `persist()` now returns `?Prospect` (null when ambiguous). Command-level tests were written after the implementation (no RED run for them).
- L5 (user decisions, 2026-11-06): (1) an email held by another location of the SAME prospect is the same contact: no skip, no record; only another prospect's email is skipped + recorded. (2) An ambiguous legacy match counts neither as persisted nor as failed in the RBFA command (warning event kept); `persistAll` unchanged. (3) Legacy candidate lookup is narrowed in SQL (REGEXP_REPLACE on VAT digits / normalized name), exact rule re-checked in PHP.
- L6 (worker, 2026-11-06): T4 implemented, uncommitted. S1b: VAT canonical form = digits without leading zeros in PHP and SQL (`TRIM(LEADING '0' FROM REGEXP_REPLACE(...))`); all-zero counts as no VAT. S5: `reconcile()` report gains `skipped_emails`; an existing location keeps a colliding email empty, fills its other fields, and the RBFA command logs "Email X skipped for location Y". New-location collisions still use `skipped_locations`.
