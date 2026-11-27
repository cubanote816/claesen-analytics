# Production inventory

> Since CLA-718 (epic CLA-717). What runs in production, per module, and whether it has an
> in-app user guide. Update this file when a module ships, is switched on, or gets its guide.

## How production is deployed

- **Production is `main`.** Every push to `main` runs `.github/workflows/deploy.yml` on the
  self-hosted `prod` runner of `prod-priv-01`, which calls `infrastructure/scripts/deploy.sh main`
  (fresh release, `composer install`, `migrate --force`, PHP-FPM reload).
- **Read the `Deploy` step, not the run.** The `Verify deployment` step always fails (curl exit 7)
  and is a known false negative. A run counts as deployed when its `Deploy` step succeeded.
- `docs/pull-deployment.md` describes an older mechanism (`production-latest` release) that the
  workflow no longer uses.

**Last checked: 2026-11-20.** `origin/main` = `b540fb1f` (PR #87). `Deploy: success` on runs
44611632678 (PR #87), 44532932786 (PR #84) and 44292620336 (PR #78).

So "code deployed" below means "on `main`". A module can be deployed and still be switched off
by a flag, or be deployed and unused.

## Modules

Legend: **Live** = deployed, switched on, with evidence of real use. **Deployed** = on `main`,
no evidence of use found. **Off** = deployed but switched off by a flag.

| Module | Surfaces | Panel | Status | Evidence | Guide | Guide ticket |
| --- | --- | --- | --- | --- | --- | --- |
| Mailing | Campaigns, Email templates, messages, public tracking / unsubscribe / preferences | admin | Live | `handoff.md` (campaigns 21–23 sent in production; CLA-698 seen in production) | Yes (CLA-693) | — |
| Core | Users, Roles, Permissions, Activity log, Access analytics, login + Microsoft + MFA | admin, bertels | Live | `odd/tasks/release-trunk-to-main.md` L13, L26–L35 | Yes, admin panel, super admins only (CLA-719); NL review pending (management) | — |
| Website | Projects, Announcements, Consultation requests, Media slots, site settings cluster; public `v1/website/*` API | admin, bertels | Live | `release-trunk-to-main.md` L30, L37; `handoff.md` CLA-705 (real request → CR-000002) | Yes, both panels (CLA-720); NL review pending (management) | — |
| Safety | Checklists, Inspections; mobile API `v1/*` for the Safety PWA | admin + PWA | Live (indirect) | `docs/ai/handoff-archive.md` (production commands 2026-06); field photos to Anthropic (CLA-577) | No | CLA-721 |
| Intelligence / BI | Billing Guardian, Monthly billing control, Offer simulator, Mirror sync status, Project intelligence detail, BI config | admin | Deployed; Mirror live | `handoff-archive.md` (`intelligence:sync-mirror` in production); per-page use not proven | Loose: `docs/billing-guardian-user-guide.md` | CLA-722 |
| FieldOps | 20 resources (9 catalogs), My work orders (technician), FieldOps SPA + API | admin + SPA | Live (backoffice, SPA) | `handoff-archive.md` (`service.claesen-verlichting.be` in production; CLA-496/497 verified in production) | Loose: `docs/luminaire-vision-identification-user-guide.md` | CLA-723 |
| Performance | Project insights, Projects, Employee insight; `v1/performance/*` | admin | Deployed | No evidence of use found | No | CLA-724 |
| Employee | Employees resource and pages, hours widget; `v1/employees` | admin | Live | `release-trunk-to-main.md` L8, L10 (widget fixed in the release) | No | CLA-724 |
| Cafca | ERP read-only pages (employees, projects); `v1/cafcas` | admin | Live | `release-trunk-to-main.md` L16 | No | CLA-724 |
| Prospects | Prospects, Sync history, Sync dashboard | admin | Live | `handoff.md` CLA-660 (39 duplicates merged on production data) | No | CLA-725 (after the federation refactor) |
| Bertels users (`app/Filament`) | `/bertels` → Users | bertels | Deployed | On `main` (PR #84, #85); no evidence of use yet | No | CLA-720 (with the Bertels Documentation group) |
| Guides & procedures (`app/Filament`) | ETS6 project archive guide | admin, bertels | Deployed | On `main` | Is itself a guide | — |
| Analytics | `POST v1/events` ingest only | — | Deployed, unused | No frontend sends events (CLA-229) | Out of scope | — |
| Knx | `v1/knx/*`, `/bertels/knx` | bertels | Off | `KNX_ENABLED` absent in production → routes 404 (`release-trunk-to-main.md` L13, L27) | Out of scope | — |

## Flags that switch features off

Defaults read from config files, not from `.env`:

| Flag | Default | Effect |
| --- | --- | --- |
| `KNX_ENABLED` | `false` (`Modules/Knx/config/config.php`) | KNX routes and navigation |
| `ORGANIZATIONS_ENFORCE` | `false` (`config/organizations.php`) | Multi-company enforcement |
| `WEBSITE_RETENTION_ENABLED` | `false` (`Modules/Website/config/config.php`) | Website data retention |
| `STATIC_SITE_REBUILD_ENABLED` | `false` (`config/static_site.php`) | Static site rebuild trigger; production value unknown |

## Known gaps

- The FieldOps client portal (`client.claesen-verlichting.be`) is not provisioned (`docs/ai/known-risks.md`).
- `mailing:parse-bounces` fails in production: the `bounces@` mailbox returns 404.
