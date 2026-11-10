# CLA-668 — Prospect filters: consistent sport/federation, apply immediately

- **Rama:** `fix/cla-668-prospect-filters` (base `origin/main` 417c501). Worktree: `.worktrees/cla-668`
- **Superficie:** `Modules/Prospects/Filament/Resources/Prospects/ProspectResource.php`, `Modules/Prospects/lang/**`, `Modules/Prospects/tests/Feature/ProspectFiltersTest.php`

## Specs

- **S1:** Sport type and federation become ONE filter (`sport_federation`, two Selects). Sport options = the distinct `type` values present in `prospects_prospects` (non-null), labelled via translations (keys added for every type the syncs write, e.g. `table_tennis_club`, `sports_infrastructure`, in every lang file that has `sport_types`); unknown values fall back to a readable version of the raw value.
- **S2:** Federation options = the distinct `federation` values present for the selected sport (query, not a hardcoded list). With no sport selected, all federations present in the data are offered. Labels: a single map code → readable label (e.g. `VL-VV` => `Voetbal Vlaanderen (VL-VV)`); unknown codes show the raw code.
- **S3:** Changing the sport clears a selected federation that does not exist for the new sport. Selecting a federation while no sport is selected fills in the sport when that federation belongs to exactly one sport in the data.
- **S4:** The query applies both conditions (type and federation) when set; indicators show the readable sport and federation labels; the old `type_filter` / `federation_filter` are removed.
- **S5:** Filters are no longer deferred: every filter applies as soon as its value changes (`->deferFilters(false)`, Apply Filters action removed), so closing the dropdown by clicking outside never discards a choice. Reset still clears all filters. Other filters (`has_email`, `unsubscribed`, `region`) keep their behaviour.

## Tasks

- [x] T1 RED: `ProspectFiltersTest` (one test per S#, plus has_email/region regression)
- [x] T2 GREEN: unified filter, option helpers, lang keys, non-deferred table
- [x] T3 Prospects suite + `git diff --check`
- [x] T4 handoff row + commit

## Log

- **L1 (2026-11-09, usuario):** "creo que la logica de este filtro debemos revisarla especificamente en sport type y federation ya que se puede seleccionar un deporte y y una federacion distinta a este y eso creo que no es logico ademas si se da un clic fuera se cierra el popup o lo que es y creo que no deberia cerrarce o si se cierra lanzar el filto aplica lo que normalmente hace la industria en estos casos"
- **L2 (2026-11-10):** RED observado antes de implementar: `ProspectFiltersTest` 6 failed / 1 passed (el test de regresión has_email/region pasa a propósito). Tras una interrupción por cuota se reescribió una aserción del test (`assertTableFilterDoesNotExist` no existe en esta versión de Filament; ahora `getFilter(...)` es null). GREEN: `ProspectFiltersTest` 7 passed (41 assertions); `Modules/Prospects/tests` 174 passed (641 assertions); `git diff --check` limpio.
- **L3:** el `vendor/` del worktree tenía ficheros rellenos de NUL (copia previa corrupta); se sobrescribió con `cp -a .worktrees/cla-665/vendor/. .worktrees/cla-668/vendor/` (`composer.lock` idéntico entre c19d692 y origin/main). `vendor/` está ignorado por git.
