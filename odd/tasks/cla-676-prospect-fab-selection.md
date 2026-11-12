# CLA-676 — Prospect "Start Mailing Campaign" button: real selection count, no selection overwrite

Linear: CLA-676 (In Progress, urgent). Branch `fix/cla-676-prospect-fab-selection` from `origin/main` (2e543e88), worktree `.worktrees/cla-676`.
The floating button counts visible checkboxes and, on click, replaces Filament's selection with the visible page; it must use the table's own Alpine selection state instead.

## Specs

- S1. The badge always shows Filament's selected count: 10 → select all (20) → deselect one (19) → deselect all (hidden), also across pages, filters and tabs.
- S2. Clicking the button opens "Start Mailing Campaign" with the exact table selection (including "select all" minus exclusions); it never replaces it with the visible page.
- S3. Existing guards and the CLA-673 role split keep working (server side unchanged).

## Tasks

- T1. [S1-S3] `floating-mailing-button.blade.php`: count via the `filamentTable` Alpine component `getSelectedRecordsCount()` (reactive effect + existing triggers as fallback); open via its `mountAction('execute_campaign', {}, {table: true, bulk: true})`; remove the override block. JS harness test (node, RED first) + Livewire server test for select-all minus exclusions. Route: worker (context backstop). Commit: pending. Evidence: node harness RED 1 pass / 5 fail, GREEN 7/7; `StartMailingCampaignTest` 12 passed; Prospects suite 186 passed.

## Log

- L1. User: "tenemos un problema en la pagina de prospecto que debemos solucionar ahora ya que gerencia espera por ello" (prior diagnosis: "investiga porque al seleccionar todos los prospectos no se actualiza el valor de btn start mailing campaigne y proponme la solucion").
- L2. Plan presented (Alpine component count + mountAction, remove override, node harness + Livewire test, manual check on 8001). User GO: "go".
- L3. Separate follow-up (not here): with "select all" over thousands of prospects the action loads every record and queries locations per record.
- L4. Independent verify (P2): on SPA navigation the script runs before Alpine initializes the table; `Alpine.$data()` returns an empty proxy and `getSelectedRecordsCount` threw, blocking the bind. Fixed: the table counts as ready only when its selection API exists, and binding waits for it. Regression test added (RED observed, then GREEN).
