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
(action scan), `app/Filament/Documentation/ModuleDocumentationPage.php` (base page),
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

2. **Every Filament action gets a section** with the anchor `action-<scope>-<name>`:

   | Declared in | Scope |
   | --- | --- |
   | `Resources/CampaignResource.php` and everything under `Resources/CampaignResource/` | `campaign` |
   | `Resources/EmailTemplates/**` | `email-template` |
   | `Resources/<X>/RelationManagers/MessagesRelationManager.php` | `<x>-messages` |
   | `Resources/Catalogs/AccessTypeResource.php` and `Resources/Catalogs/AccessTypes/**` | `access-type` |
   | `Pages/<Page>.php` | `<page>` in kebab case |

   Grouping folders such as FieldOps' `Resources/Catalogs` are skipped: the resource is the
   `*Resource` file or folder, or the folder that holds `Pages`, `Schemas`, `Tables`, ...

   An action declared in a trait belongs to every screen that uses the trait, also through
   another trait; traits match by qualified name, so aliases, grouped imports and same-named
   traits in other namespaces resolve correctly.

   Known limit: an action factory (a plain `*Action` class with a static `make()`, such as
   Website's `PublishSiteNowAction`) is named after its class where it is called
   (`publish_site_now`), and the action it builds inside is listed under the factory file
   (`action-publish-site-now-action-publish_now`). Document both until the inventory reads the
   panel at runtime.

   The name is the one passed to `Action::make('send_now')`. An unnamed Filament action uses
   its default name: `CreateAction` becomes `create`, `EditAction` becomes `edit`, and
   `DeleteBulkAction` becomes `delete_bulk`. The same button on a list row and on the record
   page shares one section.

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
that must also show on the Bertels panel (Website) is registered there explicitly, with the
Documentation group; CLA-720 adds the first one.

## Future: per-module RAG

Each `##` or `###` section with an anchor is one chunk. Chunk metadata comes from the front
matter (`module`, `locale`) and the anchor. A chatbot answer can then deep-link to
`/admin/documentation/<module>#<anchor>`. Render the guide with `ModuleGuide::render()`
before you index it, so the chunks carry the real UI names rather than placeholder keys.
