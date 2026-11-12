# CLA-672 — Preview audience shows the recipient list

Linear: CLA-672 (In Progress). Branch `feat/cla-672-audience-preview-list`, stacked on `feat/cla-671-manual-audience` (ac696436), worktree `.worktrees/cla-669`.
"Preview audience" opens a modal listing who will receive the campaign instead of a count notification.

## Specs

- S1. "Preview audience" opens a modal with the total recipient count and a table with columns: club, sport, federation, region, language, contact person, email(s).
- S2. The list comes from the same resolution as the send: unsubscribed and suppressed prospects never appear.
- S3. At most 100 rows, ordered by name; when there are more, the modal says "… and N more".
- S4. A segment configuration error is shown inside the modal (no 500).
- S5. Available for all subscribed, segment and manual audiences.
- S6. A note explains that per-recipient checks at send time (category opt-out, suppressed addresses) can still skip a few.

## Tasks

- T1. [S1-S6] Preview action modal in `CampaignForm`, Blade view under `Modules/Mailing/resources/views/filament/`, EN/NL texts, `AudiencePreviewTest`, docs. Route: worker (context backstop). Commit: pending. Evidence: RED 16 failed (2 fixture errors fixed in tests, rest missing methods); GREEN `AudiencePreviewTest` 18 passed; Mailing suite 321 passed / 2 skipped.

## Log

- L1. User: "pienso que al presionar Preview audience size pienso que en lugar  de la notificacion deberiamos mostrar una lista con los detalles de los prospectos"
- L2. User decisions: "Ticket nuevo (recomendado)"; columns "Club, deporte, federación, región, Email(s), Idioma, Persona de contacto"; "Sí, para los tres tipos"; "GO, implementalo".
- L3. Deviations accepted: `previewFilters(callable $get)` (Get is invokable); the modal is tested through `previewContent()` render + filters helper + button visibility, not via a real Livewire `mountAction` (opening it in a browser is untested). New Tailwind classes `border-danger-300 bg-danger-50 text-danger-700 dark:border-danger-700 dark:text-danger-400` (covered by `@source Modules/**/*.blade.php`, need `npm run build` locally). `notifications.audience_preview` removed (unused).
