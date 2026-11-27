# Module documentation (in-app user guides)

> Since CLA-693. Read this before you add or change a button, field or screen in a module
> that ships a user guide, or before you add a guide to a new module.

## What it is

The admin panel has a **Documentation** menu group with one page per module. Each page
renders a Markdown guide that lives next to the module code:

```
Modules/<Module>/docs/user-guide/en.md
Modules/<Module>/docs/user-guide/nl.md
Modules/<Module>/Filament/Pages/<Module>Documentation.php
```

- **Languages.** Every guide ships `en` and `nl` (user decision). Readers with another UI
  locale (fr, de) get the English text.
- **Access.** Each page follows the module resource named in `accessFollows()`. Whoever can
  open the module can read its guide.
- **Modules.** Mailing was the pilot (`Modules/Mailing/docs/user-guide`). The rollout to every
  production module is epic CLA-717; status per module: `docs/ai/production-inventory.md`.
- **Closing a feature.** The rule `.agents/rules/10-feature-documentation.md` and the skill
  `.agents/skills/feature-documentation/SKILL.md` (CLA-718) say what to update before Done.

Code: `app/Support/Documentation/ModuleGuide.php` (renderer), `ModuleActionInventory.php`
(reads the actions from the panels, CLA-728), `app/Filament/Documentation/ModuleDocumentationPage.php` (base page),
`resources/views/filament/documentation/module-guide.blade.php` (view).

## Writing rules

1. **Never type a UI name by hand.** Use a placeholder that points at the key the screen uses:

   | Placeholder | Use for | Renders |
   | --- | --- | --- |
   | `[[action:mailing::resource.actions.send_now]]` | buttons | button chip |
   | `[[label:mailing::resource.fields.subject]]` | fields, columns, sections, tabs, messages | bold text |
   | `[[nav:navigation.groups.mailing > mailing::resource.campaign.plural_model_label]]` | menu paths | `Mailing → Campaigns` |
   | `[[enum:Modules\Mailing\Enums\CampaignStatus::draft]]` | enum options (`label($locale)`) | bold text |

   Parameters go after `?`, and each value is translated when it is a key:
   `[[action:filament-actions::create.single.label?label=mailing::resource.campaign.model_label]]`.

   Names always follow the reader's UI locale, even when the guide text falls back to English.
   When someone renames a button, the guide renames itself.

2. **Every Filament action gets a section** with the anchor `action-<scope>-<name>`.

   The inventory reads the panels, not the source text (CLA-728). It takes every resource and
   page registered on a panel whose class file lives in the module (or in a `filament` folder
   of the front matter), and collects what the screen shows: header actions, the table's row,
   toolbar and bulk actions, the form's and infolist's action rows and hint, prefix and suffix
   actions (hidden ones too), relation manager tables (also those registered with
   `RelationManager::make()`), a page's own form or infolist, and a custom page's footer actions. Only methods
   the module declares are read, so Filament's defaults (the edit page's save and cancel) need
   no section.

   | Screen | Scope |
   | --- | --- |
   | `CampaignResource`: its pages, table, form and infolist | `campaign` |
   | `CampaignResource` relation `MessagesRelationManager` | `campaign-messages` |
   | Page `BiConfigPage` | `bi-config-page` |

   The scope comes from the class name: `EmailTemplateResource` becomes `email-template`, wherever
   the file lives. The name is the action's real name: `Action::make('send_now')` is `send_now`,
   `CreateAction` is `create`, and a factory such as Website's `PublishSiteNowAction::make()` is
   the name of the action it builds (`publish_now`). Filament also names `DeleteBulkAction`
   `delete`, so a bulk action gets `_bulk` (`delete_bulk`) and keeps its own section. The same
   button on a list row and on the record page shares one section.

   A screen that cannot be built without a record (for example a relation manager that reads its
   owner record while building the table) fails the coverage test with the screen's name; make
   that code tolerate a missing record before you add the module's guide. Screens that resolve
   their panel's site while they are built (Website: `Site::forPanelOrFail()`) need that site in
   the test database: the coverage test creates every panel's site first
   (`tests/Feature/Documentation/SeedsPanelSites.php`, CLA-720).

   Example: `### [[action:mailing::resource.actions.send_now]] {#action-campaign-send_now}`.

3. **Processes and concepts get stable anchors too**, for example `{#campaign-lifecycle}`. Link
   them with `[text](#anchor)`. The table of contents lists the h2 and h3 headings that have
   an anchor.

4. **Same sections in every language.** `en.md` and `nl.md` have the same anchors in the same
   order.

5. **Front matter is required:** `module`, `title`, `navigation_label`, `locale` and `summary`.
   Optional: `filament`, in `en.md`, lists screens that live outside the module as
   repository-relative folders, comma separated. Website's screens live in
   `app/Filament/Clusters/Website`, so its guide declares
   `filament: app/Filament/Clusters/Website`. A folder that does not exist fails the coverage
   test.

6. **Write for end users.** Describe what the screen does and who may use it. Describe only
   behaviour you have checked in the code.

## How it stays current

`tests/Feature/Documentation/ModuleGuideCoverageTest.php` runs in CI on every pull request.
It fails in four cases:

- an action has no section in some language;
- a placeholder key does not resolve;
- the `en` and `nl` anchors differ;
- a required locale is missing.

A new button therefore cannot merge without its documentation. The Definition of Done
(`docs/ai/project-protocol.md`) also asks you to update the guide whenever a module's UI
changes. The prose is still written by hand. Only the UI names and the coverage check are
automatic.

Run it locally with `bin/wt-test tests/Feature/Documentation`.

## Adding a guide to a module

1. Write `Modules/<Module>/docs/user-guide/{en,nl}.md` following the rules above.
2. Add `Modules/<Module>/Filament/Pages/<Module>Documentation.php`. It extends
   `ModuleDocumentationPage` and implements `module()` and `accessFollows()`. The admin
   panel discovers it automatically.
3. Add a page test in the style of `Modules/Mailing/tests/Feature/MailingDocumentationPageTest.php`.
   It covers who can open the page, that the technician role is denied, and that both
   languages render.
4. The coverage test picks up the new module automatically.
5. Update the module's row in `docs/ai/production-inventory.md`.

Guide pages live in `Modules/*/Filament/Pages`, which only the admin panel discovers. A guide
that must also show on the Bertels panel is registered there by hand in
`BertelsPanelProvider::pages()`; the panel already has the Documentation group (CLA-720, first
one: Website). Registering a page changes two baseline snapshots on purpose: regenerate them with
`UPDATE_BASELINE_SNAPSHOTS=1` and update the Bertels page list in
`tests/Feature/ClaesenBaseline/PanelRegistrySnapshotTest.php`. A new admin guide page changes the
snapshots too.

## Future: per-module RAG

Each `##` or `###` section with an anchor is one chunk. Chunk metadata comes from the front
matter (`module`, `locale`) and the anchor. A chatbot answer can then deep-link to
`/admin/documentation/<module>#<anchor>`. Render the guide with `ModuleGuide::render()`
before you index it, so the chunks carry the real UI names rather than placeholder keys.
