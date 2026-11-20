---
name: feature-documentation
description: "Trigger: a feature or ticket that changes what users see or do is about to close, or someone asks to document a module. Write or update the in-app user guide (en + nl), the technical module doc and the production inventory, then prove coverage."
---

# Feature documentation

Every feature that reaches users ships with its documentation in the same pull request.
Epic: CLA-717. Conventions: `docs/ai/module-documentation.md` (read it first).

## When it applies

Run this skill before you close a ticket that changes any of these:

- a Filament screen, button, field, column, filter, tab or notification;
- a PWA or public-website flow, or an email users receive;
- an API, Artisan command or scheduled job that someone operates.

It does not apply to refactors, tests or internal tooling with no visible effect. Say so in
the closure ("Docs: not applicable, no visible change") instead of skipping silently.

## Steps

1. **Find what changed.** Run `git diff --stat <base>...HEAD`. List the modules and the screens
   or actions the diff touches.
2. **Check the module's guide status** in `docs/ai/production-inventory.md`.
3. **Module with a guide** (`Modules/<Module>/docs/user-guide/{en,nl}.md`):
   - Add or update one section per new or changed action, anchor `action-<scope>-<name>`.
   - Add or update the process or concept sections the feature changes, with stable anchors.
   - Use placeholders for every UI name (`[[action:…]]`, `[[label:…]]`, `[[nav:…]]`,
     `[[enum:…]]`). Never type a button or field name by hand.
   - Write `en` and `nl` with the same anchors in the same order.
   - Describe only behaviour you checked in the code or in the browser.
4. **Module without a guide yet.** Do not write a partial guide: the coverage test requires every
   action of the module, so a partial guide fails CI. Comment on the module's guide ticket in the
   epic (see the inventory) with the actions and behaviour the feature adds, so the guide covers
   them. Continue with step 5.
5. **Technical documentation.** Update the module document listed in `docs/ai/README.md` when a
   contract, API, command, job or configuration changed. New Artisan commands go to
   `docs/ai/commands-runbook.md`.
6. **Production inventory.** Update `docs/ai/production-inventory.md` when a module ships, is
   switched on or off, or gets its guide.
7. **RAG.** Every guide section you add carries an anchor: that anchor is its chunk. Once the RAG
   evaluation set exists (`docs/rag/eval/`, CLA-726), add one to three real user questions with
   the anchor that answers them.
8. **Dutch review.** Management reviews the Dutch text. Mark it in the pull request description:
   `NL review: pending (management)`.
9. **Prove it.** Run `bin/wt-test tests/Feature/Documentation` and the module's documentation page
   test. When the guide changed, open `/admin/documentation/<module>` in the browser in `en` and
   `nl`.

## Closure line

Add this line to the ticket closure and the pull request:

```
Docs: <guide files | ticket comment | not applicable: reason> · coverage <passed N/N> · NL review pending (management)
```

## Never

- Type a UI name by hand, or describe behaviour you did not check.
- Document a feature that is not merged into the branch you are documenting.
- Ship `en` without `nl`, or the reverse.
