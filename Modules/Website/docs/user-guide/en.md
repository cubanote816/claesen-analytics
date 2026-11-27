---
module: Website
title: Website
navigation_label: Website
locale: en
summary: How to edit, review and publish the public website — settings, projects, media, announcements, page and translation reviews — and how to handle consultation requests.
filament: app/Filament/Clusters/Website
---
## Overview {#overview}

The Website screens manage the public website: its settings, the project gallery, images, announcements, which pages search engines may list, which translations go live, and the consultation requests that arrive through the site.

Each admin panel manages its own website. The Claesen panel manages the Claesen Verlichting site, the Electro Bertels panel manages the Electro Bertels site. You never see or change the other company's site.

Where to find the screens:

- **Claesen panel:** one menu item, [[nav:navigation.groups.content_website > website.cluster_label]], with every screen below it.
- **Electro Bertels panel:** four menu groups: [[label:website.nav_groups.publishing]], [[label:website.nav_groups.content]], [[label:website.nav_groups.site_settings]] and [[label:website.nav_groups.inbox]].

The short version: change a [setting](#site-settings) or a [project](#projects), save it, then [publish](#publishing). Visitors only see a change after it is published.

## Who can do what {#access}

On the Claesen panel every user of the panel can open the Website screens, except the technician role. On the Electro Bertels panel the members of Electro Bertels can. This guide follows the same rule.

Two actions need an extra right on top of that: [[action:website.consultation_requests.actions.export]] and [[action:website.consultation_requests.actions.erase]]. A button you are not allowed to use is not shown.

## Publishing {#publishing}

The website is a separate copy that is rebuilt from the platform. Changing something here does not change the website by itself:

1. **Save.** Your change is stored as a draft. Visitors do not see it yet.
2. **Publish.** You ask for the website to be rebuilt with the saved changes:
   - [[action:website.publication.actions.publish_section]] on a settings page publishes only that page's settings. If they would not change anything on the site, you get a message that there is nothing to publish.
   - [[action:website.publication.actions.publish_now]] rebuilds the whole site. The current version stays online until the new one is ready.
3. **Check.** A publication is a request: the rebuild takes a moment. Follow it under [[label:website.publication_history.label]], which lists every request, who made it, and whether the website accepted it or it failed.

If publishing is switched off on the server, both buttons show a warning and nothing is sent.

The screens show how many saved changes are not published yet.

## Site settings {#site-settings}

Six pages hold the site's general information: [[label:website.settings_sections.general.label]], [[label:website.settings_sections.contact.label]], [[label:website.settings_sections.address.label]], [[label:website.settings_sections.hours.label]], [[label:website.settings_sections.social.label]] and [[label:website.settings_sections.privacy.label]].

They all work the same way. When you change a field, a bar appears at the bottom with [[action:website.settings_bar.discard]] and [[action:website.settings_bar.save_draft]]. Saving stores only that page's fields, as a draft. Then publish the page or the whole site, as described in [Publishing](#publishing).

### [[label:website.settings_sections.general.label]]: save {#action-general-settings-page-save}

[[action:website.settings_bar.save_draft]] in the bar at the bottom stores the general settings as a draft. [[action:website.settings_bar.discard]] puts back the last saved values.

### [[label:website.settings_sections.general.label]]: [[action:website.publication.actions.publish_section]] {#action-general-settings-page-publish_section}

Publishes the saved general settings. A confirmation shows how many changes are waiting.

### [[label:website.settings_sections.general.label]]: [[action:website.publication.actions.publish_now]] {#action-general-settings-page-publish_now}

Rebuilds the whole site. See [Publishing](#publishing).

### [[label:website.settings_sections.contact.label]]: save {#action-contact-settings-page-save}

[[action:website.settings_bar.save_draft]] stores the contact settings as a draft; [[action:website.settings_bar.discard]] puts back the last saved values.

### [[label:website.settings_sections.contact.label]]: [[action:website.publication.actions.publish_section]] {#action-contact-settings-page-publish_section}

Publishes the saved contact settings.

### [[label:website.settings_sections.contact.label]]: [[action:website.publication.actions.publish_now]] {#action-contact-settings-page-publish_now}

Rebuilds the whole site.

### [[label:website.settings_sections.address.label]]: save {#action-address-settings-page-save}

[[action:website.settings_bar.save_draft]] stores the address and map settings as a draft; [[action:website.settings_bar.discard]] puts back the last saved values.

### [[label:website.settings_sections.address.label]]: [[action:website.publication.actions.publish_section]] {#action-address-settings-page-publish_section}

Publishes the saved address and map settings.

### [[label:website.settings_sections.address.label]]: [[action:website.publication.actions.publish_now]] {#action-address-settings-page-publish_now}

Rebuilds the whole site.

### [[label:website.settings_sections.hours.label]]: save {#action-opening-hours-settings-page-save}

[[action:website.settings_bar.save_draft]] stores the opening hours as a draft; [[action:website.settings_bar.discard]] puts back the last saved values.

### [[label:website.settings_sections.hours.label]]: [[action:website.publication.actions.publish_section]] {#action-opening-hours-settings-page-publish_section}

Publishes the saved opening hours.

### [[label:website.settings_sections.hours.label]]: [[action:website.publication.actions.publish_now]] {#action-opening-hours-settings-page-publish_now}

Rebuilds the whole site.

### [[label:website.settings_sections.social.label]]: save {#action-social-links-settings-page-save}

[[action:website.settings_bar.save_draft]] stores the social links as a draft; [[action:website.settings_bar.discard]] puts back the last saved values.

### [[label:website.settings_sections.social.label]]: [[action:website.publication.actions.publish_section]] {#action-social-links-settings-page-publish_section}

Publishes the saved social links.

### [[label:website.settings_sections.social.label]]: [[action:website.publication.actions.publish_now]] {#action-social-links-settings-page-publish_now}

Rebuilds the whole site.

### [[label:website.settings_sections.privacy.label]]: save {#action-privacy-consent-settings-page-save}

[[action:website.settings_bar.save_draft]] stores the privacy and consent settings as a draft; [[action:website.settings_bar.discard]] puts back the last saved values.

### [[label:website.settings_sections.privacy.label]]: [[action:website.publication.actions.publish_section]] {#action-privacy-consent-settings-page-publish_section}

Publishes the saved privacy and consent settings.

### [[label:website.settings_sections.privacy.label]]: [[action:website.publication.actions.publish_now]] {#action-privacy-consent-settings-page-publish_now}

Rebuilds the whole site.

## Projects {#projects}

The project gallery on the website. Each project has a title (the web address is made from it), a category, the client, location and year, a description, switches for published and featured, and a number for its place in the order. You can also drag rows in the list to change the order.

Images: at most 10 MB and 8000 pixels per image. One featured image, plus a gallery and a detail gallery that you can reorder.

### [[action:filament-actions::create.single.label?label=website.projects.label]] {#action-project-create}

Fill in the project and save. It reaches the website after you [publish](#publishing).

### [[action:filament-actions::edit.single.label]] {#action-project-edit}

Change a project's text, switches or images, then save and publish.

### [[action:filament-actions::delete.single.label]] {#action-project-delete}

On the project's edit page. It removes the project; publish to take it off the website.

### [[action:filament-actions::delete.multiple.label]] {#action-project-delete_bulk}

Select projects in the list and delete them together.

### [[action:website.publication.actions.publish_now]] {#action-project-publish_now}

On the list and on the edit page. Rebuilds the whole site, see [Publishing](#publishing).

## Media slots {#media-slots}

A media slot is a fixed place on the website, such as a header image. The slot name must be the one the website uses; ask the web developer when you need a new one. Each slot shows one image, picked from this site's project images.

### [[action:filament-actions::create.single.label?label=website.media_slots.label]] {#action-media-slot-create}

Enter the slot name and pick the image. A name can be used once per site.

### [[action:filament-actions::edit.single.label]] {#action-media-slot-edit}

Pick another image for the slot.

### [[action:filament-actions::delete.single.label]] {#action-media-slot-delete}

Removes the slot.

### [[action:website.publication.actions.publish_now]] {#action-media-slot-publish_now}

Rebuilds the whole site, see [Publishing](#publishing).

## Announcements {#announcements}

Short messages for the website, for example a holiday closure. An announcement is draft, published or archived, and can have an optional start and end date.

### [[action:filament-actions::create.single.label?label=website.announcements.label]] {#action-announcement-create}

Write the announcement, choose its status and, if needed, the dates.

### [[action:filament-actions::edit.single.label]] {#action-announcement-edit}

Change the text, status or dates.

### [[action:filament-actions::delete.single.label]] {#action-announcement-delete}

Removes the announcement. To only hide it, archive it instead.

## Page publications {#page-publications}

[[label:website.page_publication_review.label]] decides which website pages search engines may list, in which language. The text of the pages is written by the web developers, so there is nothing to edit here: you review and switch pages on or off.

A page goes from draft to reviewed to published. Every step records who did it and when.

### [[action:website.page_publication_review.actions.approve]] {#action-page-publication-review-page-approve}

Marks a page as reviewed, after you checked it. Shown while the page is neither reviewed nor published.

### [[action:website.page_publication_review.actions.publish]] {#action-page-publication-review-page-publish}

Marks a reviewed page as one search engines may list in that language. Shown for reviewed pages.

### [[action:website.page_publication_review.actions.retire]] {#action-page-publication-review-page-retire}

Takes a published page back to reviewed, so search engines no longer list it. Shown for published pages.

### [[action:website.publication.actions.publish_now]] {#action-page-publication-review-page-publish_now}

Rebuilds the whole site so the page changes reach visitors, see [Publishing](#publishing).

## Translation review {#translation-review}

Texts written in one language are translated automatically. [[label:website.translation_review.label]] lists those translations so someone can check them before they go live.

### [[action:website.translation_review.actions.edit]] {#action-translation-review-page-edit}

Correct a translation. Your correction is saved as a draft and still needs approval.

### [[action:website.translation_review.actions.approve]] {#action-translation-review-page-approve}

Marks a translation as checked. Shown while it is neither reviewed nor published.

### [[action:website.translation_review.actions.publish]] {#action-translation-review-page-publish}

Lets a reviewed translation go live. Shown for reviewed translations.

### [[action:website.translation_review.actions.retranslate]] {#action-translation-review-page-retranslate}

Asks for a new automatic translation. It replaces the current one, even an approved one, so it needs approval again.

### [[action:website.publication.actions.publish_now]] {#action-translation-review-page-publish_now}

Rebuilds the whole site, see [Publishing](#publishing).

## Consultation requests {#consultation-requests}

[[label:website.consultation_requests.plural_label]] are the requests visitors send through the website form. Each request has a status (new, assigned, in progress, waiting for the client, closed or spam), a priority and the colleague it is assigned to.

### [[action:filament-actions::create.single.label?label=website.consultation_requests.label]] {#action-consultation-request-create}

Enter a request that came in another way, for example by phone.

### [[action:filament-actions::edit.single.label]] {#action-consultation-request-edit}

Change the status, priority or assignee. You can also correct the name, email, phone and company, for example when the person asks for it. The type, project type and message stay as they were sent.

### [[action:website.consultation_requests.actions.export]] {#action-consultation-request-export}

Downloads every request of this site as a CSV file. It needs the export right and can ask you to confirm with your authenticator app or email code. The admins are notified that an export was made.

### [[action:website.consultation_requests.actions.erase]] {#action-consultation-request-erase}

Removes the personal data of a request, when the person asks for it under the GDPR. It needs the erase right and can ask you to confirm with your authenticator app or email code. It cannot be undone; after that the button is no longer shown for that request.

### [[action:filament-actions::view.single.label]] {#action-consultation-request-activities-view}

In the request's history: opens one entry to read it. The history cannot be changed.
