# CLA-700 — Complete fr and de static translations (lang files) with a key-parity guard

- **Ticket:** CLA-700 (In Progress). **Branch:** `feat/cla-700-lang-fr-de`. **Worktree:** `.worktrees/cla-700` (from `origin/main` 06cb20ca).
- **Source of the gap:** `docs/ai/translation-system-audit.md` (worktree `translation-engine`), static i18n section. Tests run with `bin/wt-test`.

## Specs

- **S1** — Scope (user, verbatim): "la respuesta a tu duda es los lang/ que faltan intentarlo reslover ahora desde aqui si usar gemini pienso que gemini lo debemos usar solo para las traducciones de las entrada de los user que se almacenan en la bd o analisis de AI que almacenamos en la bd tambien."
  - Static lang files are translated by hand in the repo. No Gemini, no runtime change to the translation engine.
- **S2** — Register and variant (user choice, verbatim): "Formal, francés de Bélgica" — French uses *vous* with Belgian conventions; German uses *Sie*.
- **S3** — Work location (user choice, verbatim): "Worktree nuevo desde main".
- **S4** — Guard: every lang directory (`lang/` and `Modules/*/lang/`) has the same PHP files and the same keys in `en`, `nl`, `fr` and `de`; every translation keeps the same `:placeholder` set and the same number of plural segments (`|`) as `en`.
- **S5** — `en` must not lack keys that `nl` has (6 found: Mailing `placeholders.content_description`, Prospects `unsubscribe.*` ×5).
- **S6** — A terminology glossary (fr-BE / de) is shown to the user before the large FieldOps file is translated.

## Tasks

| ID | Specs | Route | Status | Commit |
|---|---|---|---|---|
| T1 | S4 | inline — guard test `tests/Feature/LangFileParityTest.php`, observe RED | done (RED: 31 key-parity cases fail, placeholder cases pass) | — |
| T2 | S5 | inline — Prospects `unsubscribe.*` added to en; Mailing nl `placeholders.content_description` removed (no caller, L4) | done | — |
| T3 | S6, S2 | inline — glossary in this document; user checkpoint before T5/T6 | done (approved, L6) | — |
| T4 | S1, S2, S4 | worker — root `lang/` (website, navigation, employees, users, roles, permissions, activity_log) + Core | done (parity test green for these sets) | — |
| T5 | S1, S2, S4 | worker — FieldOps `lang/fr/resource.php` (whole file) | done (568 keys; parent fixed 2 untranslated `cafca_material_id` labels) | — |
| T6 | S1, S2, S4 | worker — FieldOps `lang/de/resource.php` (whole file) | done (568 keys) | — |
| T7 | S1, S2, S4 | worker — Mailing + Prospects | done (parity test green for these sets) | — |
| T8 | S1, S2, S4 | worker — Intelligence, Knx, Performance, Safety | done (parity test green for these sets) | — |
| T9 | all | inline — full suite, handoff row, present diff for technical GO | done (suite 2904 passed / 3 skipped; awaiting technical GO) | — |

Commit policy (`docs/ai/project-protocol.md`): one commit per ticket, created only after the technical GO. User confirmed a single CLA-700 commit (L6).

## Glossary

Rules for every translator:

- French: formal *vous*, Belgian usage (`GSM` for mobile phone, `e-mail`, dates `dd/mm/yyyy`). German: formal *Sie*. Short UI labels use the infinitive in fr (`Enregistrer`) and the infinitive in de (`Speichern`).
- Keep every `:placeholder`, `{0}|[1,*]` plural marker, HTML tag and `{{ variable }}` exactly as in en. Same number of `|` segments as en.
- Never translate brand and product names: Claesen, Claesen Verlichting, Electro Bertels, CAFCA, KNX, Gemini, Filament.
- Keep the en key order and the en file structure. Same quote style as the en file.

| en | nl (reference; the repo's nl files win when they differ) | fr (BE) | de |
|---|---|---|---|
| Luminaire | Armatuur | Luminaire | Leuchte |
| Luminaire frame | Verlichtingsframe | Cadre d'éclairage | Leuchtenrahmen |
| Structure | Structuur | Structure | Struktur |
| Terrain (sports field) | Terrein | Terrain | Spielfeld |
| Complex | Complex | Complexe | Anlage |
| Electrical board | Elektrisch bord | Tableau électrique | Schaltschrank |
| Pin (position marker) | Pin | Repère | Markierung |
| Work order | Werkorder | Bon de travail | Arbeitsauftrag |
| Maintenance plan | Onderhoudsplan | Plan de maintenance | Wartungsplan |
| Maintenance record | Onderhoudsregistratie | Rapport de maintenance | Wartungsprotokoll |
| Technician | Technieker | Technicien | Techniker |
| Client / customer | Klant | Client | Kunde |
| Prospect | Prospect | Prospect | Interessent |
| Club / federation | Club / federatie | Club / fédération | Verein / Verband |
| Campaign | Campagne | Campagne | Kampagne |
| Template | Sjabloon | Modèle | Vorlage |
| Segment / audience | Segment / doelgroep | Segment / audience | Segment / Zielgruppe |
| Unsubscribe | Uitschrijven | Se désabonner | Abmelden |
| Inspection / checklist | Inspectie / checklist | Inspection / liste de contrôle | Inspektion / Checkliste |
| Dashboard | Dashboard | Tableau de bord | Dashboard |
| Review / approve / publish | Nakijken / goedkeuren / publiceren | Relire / approuver / publier | Prüfen / genehmigen / veröffentlichen |
| Flanders / Wallonia / Brussels | Vlaanderen / Wallonië / Brussel | Flandre / Wallonie / Bruxelles | Flandern / Wallonien / Brüssel |
| Employee | Werknemer | Employé | Mitarbeiter |
| Role / permission | Rol / permissie | Rôle / autorisation | Rolle / Berechtigung |

## Log

- **L1** (2026-11-18, user, verbatim): "en la recoendacion 2. Mandar el source_locale es buena idea pero debemos manejar la posibilidad que el usuario este en EN y escriba en NL por lo que pienso que detectar el idioma es fundamental. la respuesta a tu duda es los lang/ que faltan intentarlo reslover ahora desde aqui si usar gemini pienso que gemini lo debemos usar solo para las traducciones de las entrada de los user que se almacenan en la bd o analisis de AI que almacenamos en la bd tambien. si tienes alguna recomendacion me dices"
- **L2** (2026-11-18, user choices): ticket — "Creá el ticket en Linear y arrancá (GO)"; register — "Formal, francés de Bélgica"; relocation — "Sí, reubicar si la detección es confiable" (applies to the translation engine, not this ticket); branch — "Worktree nuevo desde main".
- **L3** (2026-11-18): measured gap against en+nl — ~1,480 keys missing in de, ~1,340 in fr (~11k words total). Prospects already has a partial fr file (2 keys missing).
- **L5** (2026-11-18): T4/T7/T8 delegated to three parallel workers with disjoint surfaces; each reported `php -l` clean and its data sets green. The run exposed one more nl gap: `lang/nl/employees/resource.php` lacked `status.all` (en `All`); added `'all' => 'Alle'` in the parent. Only FieldOps remains red.
- **L6** (2026-11-18, user choices): glossary — "Sí, seguí con este glosario"; commits — "Un solo commit CLA-700". Placeholder guard proven: replacing `:name` with `:nom` in a fr file made the placeholder test fail; file restored.
- **L7** (2026-11-18): both FieldOps workers ended with a provider quota error, but had already written complete files (568 leaf keys each, `php -l` clean). Parent verified: `LangFileParityTest` 64/64 green; glossary terms present (de Spielfeld/Schaltschrank/Arbeitsauftrag/Leuchtenrahmen, fr bon de travail/tableau électrique/cadre d'éclairage/repère); only 2 fr strings were still English (`CAFCA Material ID` → `ID matériel CAFCA`).
- **L8** (2026-11-19): technical GO → commit ff30b2f4. Before push, rebased on origin/main (b540fb1f, CLA-699/704/705/707 merged meanwhile); only `handoff.md` conflicted (kept both sides). The guard immediately caught upstream's new en/nl keys without fr/de: `Modules/Core/lang/*/mail.php` (`automated_notice`, CLA-699) and `lang/*/users/resource.php` (`microsoft_account`, `local_account`, `bertels_email_hint`, CLA-704). Added fr/de for them; parity 68/68.
- **L4** (2026-11-18): Mailing nl `placeholders.content_description` has no caller in code, views or tests (grep). Prospects `unsubscribe.*` is used by the campaign email footer and the unsubscribe page, so en recipients currently get the nl fallback.
