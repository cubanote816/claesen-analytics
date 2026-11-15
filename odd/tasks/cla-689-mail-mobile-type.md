# CLA-689 — Mail: smaller type on mobile and no mid-word break in the button

Linear: CLA-689. Branch `fix/cla-689-mail-mobile-type` from `origin/main` (2fcb03cb). Follow-up to CLA-686.

## Specs

- S1. The button label never breaks inside a word (seen as "Wachtwoord aanm / aken" in the Gmail app); the fallback URL in the subcopy still wraps anywhere.
- S2. Type scale: body 15px (14px on screens ≤ 600px), heading 18px on screens ≤ 600px, subcopy 13px, footer 12px, button 15px with less padding on phones.
- S3. No text, color or brand changes.

## Tasks

- T1. [S1-S3] `themes/default.css` + mobile media query in `layout.blade.php` + 2 tests in `BrandedMailTemplateTest`. Route: inline. Commit: pending. Evidence: RED 2 failed (8 assertions) on an isolated test DB; GREEN BrandedMailTemplateTest + AccountActivationEmailTest 27 passed with APP_LOCALE=en and nl.

## Log

- L1. User (Gmail Android screenshots, 2026-11-15): "como puedes ver con las capturas la fuente creo que esta un poquito grande"
- L2. User: "go" on the proposed scale (body 15/14px, heading 18px on phones, button never breaks a word).
- L3. The shared test DB was busy with another session's suites; tests ran on `testing_cla_689` via `DB_DATABASE` (sail may create `testing%` databases).
