---
module: Mailing
title: Mailing user guide
navigation_label: Mailing
locale: en
summary: How to create, approve, send and follow up email campaigns and manage email templates.
---
## Overview {#overview}

The Mailing module sends email campaigns to prospects. You pick an [email template](#email-templates), choose who receives it ([audience](#audience)), get the campaign approved and send it. Afterwards you follow the results in the [metrics](#metrics) and the [recipient list](#recipient-pages).

You find the module in two places in the menu:

- Campaigns: [[nav:navigation.groups.mailing > mailing::resource.campaign.plural_model_label]]
- Email templates: [[nav:mailing::resource.navigation_group > mailing::resource.plural_model_label]]

The two items sit in different menu groups. This is how the menu is set up.

The short version: [create a template](#action-email-template-create), [create a campaign](#action-campaign-create), [submit it](#action-campaign-submit), [approve it](#action-campaign-approve) and [send it](#action-campaign-send_now). The full path is described in [the campaign lifecycle](#campaign-lifecycle).

## Who can do what {#access}

Everyone who can use the admin panel can open the Mailing module, except users with the technician role. This guide follows the same rule.

The campaign manager and marketer roles only add Mailing rights. To open the admin panel at all, a user also needs a panel role such as admin or viewer.

| Action | Who |
| --- | --- |
| View campaigns and their results | Everyone with access |
| [[action:filament-actions::create.single.label?label=mailing::resource.campaign.model_label]] and [[action:mailing::resource.actions.submit_review]] | Admin, campaign manager, marketer |
| [[action:mailing::resource.actions.approve]] and [[action:mailing::resource.actions.send_now]] | Admin, campaign manager |
| [[action:mailing::resource.actions.cancel]] | Admin only |
| Manage email templates | Everyone with access |

A super admin can do everything. A marketer can never approve a campaign: a campaign created by a marketer always needs a campaign manager or admin. A button you are not allowed to use is simply not shown.

## The campaign lifecycle {#campaign-lifecycle}

A campaign goes through these steps:

1. [Create the campaign](#action-campaign-create). It starts as [[enum:Modules\Mailing\Enums\CampaignStatus::draft]].
2. [Submit it for review](#action-campaign-submit). The status becomes [[enum:Modules\Mailing\Enums\CampaignStatus::review]].
3. A campaign manager or admin [approves it](#action-campaign-approve). The status becomes [[enum:Modules\Mailing\Enums\CampaignStatus::approved]]. Approvers can also approve a draft directly, without a review step.
4. The campaign is sent: either by [Send now](#action-campaign-send_now), or automatically at the [scheduled time](#scheduling). While sending, the status is [[enum:Modules\Mailing\Enums\CampaignStatus::sending]].
5. When all emails are processed, the campaign becomes [[enum:Modules\Mailing\Enums\CampaignStatus::completed]]. If nothing could be sent and at least one email failed, it becomes [[enum:Modules\Mailing\Enums\CampaignStatus::failed]].

A draft, a campaign under review and an approved campaign can still be [cancelled](#action-campaign-cancel) (admin only). All statuses are explained in [Campaign statuses](#statuses).

Good to know:

- There is no edit page for a campaign. Check the form carefully before you create it.
- There is no button to move a campaign back to draft.
- You cannot cancel a campaign while it is sending.
- Sending runs in the background; see [Background jobs](#background-jobs).

## Campaign statuses {#statuses}

| Status | Meaning |
| --- | --- |
| [[enum:Modules\Mailing\Enums\CampaignStatus::draft]] | Just created. Can be submitted, approved or cancelled. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::review]] | Waiting for approval. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::approved]] | Ready to send, manually or at the scheduled time. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::sending]] | Emails are being sent. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::completed]] | Finished. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::failed]] | Finished without a single email sent while at least one failed. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::cancelled]] | Cancelled. Final. |

The campaign list refreshes itself every 5 seconds, so you see the status change without reloading. You can filter the list on [[label:mailing::resource.fields.status]].

## Create a campaign {#action-campaign-create}

Open [[nav:navigation.groups.mailing > mailing::resource.campaign.plural_model_label]] and press [[action:filament-actions::create.single.label?label=mailing::resource.campaign.model_label]]. The form has these sections:

**[[label:mailing::resource.sections.campaign_details]]**

- [[label:mailing::resource.fields.template]]: the email template to use. The campaign takes a copy of the subject and body at this moment. Later changes to the template's text do not affect this campaign. The template's category and preference category are read again when the campaign is [approved](#action-campaign-approve).
- [[label:mailing::resource.fields.description]]: the name of the campaign, as you will see it in the list.
- [[label:mailing::resource.fields.subject]]: filled in from the template. You can change it for this campaign only.

**[[label:mailing::resource.sections.ab_test]]**: collapsed by default, see [A/B subject test](#ab-test).

**[[label:mailing::resource.sections.audience]]**: see [Audience](#audience) and [Scheduling](#scheduling).

**[[label:mailing::resource.sections.followup]]**: collapsed by default, see [Follow-up campaigns](#follow-up).

After you save, the campaign is created as [[enum:Modules\Mailing\Enums\CampaignStatus::draft]] and you land on its [detail page](#action-campaign-view). The next step is [Submit for review](#action-campaign-submit) or, if you are allowed to, [Approve](#action-campaign-approve).

## Audience {#audience}

In [[label:mailing::resource.sections.audience]] you choose [[label:mailing::resource.fields.audience_type]]:

- [[enum:Modules\Mailing\Enums\AudienceType::all_subscribed]]: every subscribed prospect. Prospects without an email address are skipped when the campaign is sent.
- [[enum:Modules\Mailing\Enums\AudienceType::segment]]: prospects that match rules you define. Choose in [[label:mailing::resource.fields.segment_operator]] whether all rules or any rule must match, then press [[action:mailing::resource.actions.add_rule]] for every rule. A rule is one of:
  - [[label:mailing::resource.fields.rule_has_event]]: for example prospects who clicked in a certain campaign, optionally within the last N days.
  - [[label:mailing::resource.fields.rule_has_no_event]]: the opposite.
  - [[label:mailing::resource.fields.rule_prospect_field]]: language, federation or region matches a value. Note: [[label:mailing::resource.fields.rule_operator_in]] currently treats the whole text as one value, so `RBFA,LBFA` does not match RBFA or LBFA. To target several values, add one rule per value and choose [[label:mailing::resource.fields.segment_operator_or]].
- [[enum:Modules\Mailing\Enums\AudienceType::manual]]: you pick the contacts yourself in [[label:mailing::resource.fields.manual_prospects]]. Only subscribed contacts with an email address can be chosen.

Press [[action:mailing::resource.actions.preview_audience]] to check the audience before you create the campaign. See [Preview audience](#action-campaign-preview_audience).

Prospects who unsubscribed, who opted out of the template's preference category or whose address is suppressed (for example after a hard bounce) never receive the email, even if they match the audience. Unsubscribed and suppressed prospects are left out before sending, so they usually do not appear in the [recipient list](#recipient-pages). Contacts skipped during sending (for example without an email address) appear there with the status [[label:prospects::resource.options.status.skipped]].

## [[action:mailing::resource.actions.preview_audience]] {#action-campaign-preview_audience}

In the audience section of the create form. It opens a window with the number of recipients and the first 100 of them (club, sport, federation, region, language, contact person and email addresses). If there are more, you see how many more.

The window also reminds you that the real send can still skip a few contacts: category opt-outs and suppressed addresses. Use [[action:mailing::resource.actions.close]] to go back to the form.

## Scheduling {#scheduling}

Fill in [[label:mailing::resource.fields.scheduled_at]] to send the campaign automatically at a chosen moment. The time is in the Europe/Brussels timezone (the [[label:mailing::resource.fields.timezone]] field is fixed). The date cannot be in the past.

- With a date: once the campaign is [approved](#action-campaign-approve), it is sent at that time without further action. The [[action:mailing::resource.actions.send_now]] button is not available.
- Without a date: you send it yourself with [Send now](#action-campaign-send_now).

The scheduler checks every minute; see [Background jobs](#background-jobs).

## A/B subject test {#ab-test}

With an A/B test you try two subject lines on a small part of the audience and send the best one to the rest. Open [[label:mailing::resource.sections.ab_test]] and fill in [[label:mailing::resource.fields.ab_subject_b]]. The subject of the campaign becomes variant A. Leave variant B empty if you do not want a test.

- [[label:mailing::resource.fields.ab_split_percent]]: default 10, maximum 50. With 10, 10% receives A and 10% receives B. The remaining 80% receives the winner.
- [[label:mailing::resource.fields.ab_winner_after_hours]]: default 4. After this time the system picks the winner.

The winner is the variant with the highest click rate (unique clicks). With a tie, A wins. This check runs every 5 minutes; see [Background jobs](#background-jobs).

## Follow-up campaigns {#follow-up}

A follow-up automatically sends a second campaign to part of the audience of the first one. Open [[label:mailing::resource.sections.followup]] in the form of the first campaign:

- [[label:mailing::resource.fields.followup_campaign]]: choose an already approved campaign. It is sent to the audience of this campaign.
- [[label:mailing::resource.fields.followup_trigger]]: who gets the follow-up: [[enum:Modules\Mailing\Enums\FollowUpTrigger::clicked]], [[enum:Modules\Mailing\Enums\FollowUpTrigger::not_clicked]], [[enum:Modules\Mailing\Enums\FollowUpTrigger::opened]] or [[enum:Modules\Mailing\Enums\FollowUpTrigger::not_opened]]. Opens are a weak signal, so prefer clicks.
- [[label:mailing::resource.fields.followup_delay_hours]]: default 24. For example 72 is three days after the first campaign completes.

A follow-up campaign is sent by the system, so [Send now](#action-campaign-send_now) is not available for it. The check runs every 5 minutes; see [Background jobs](#background-jobs).

## The campaign page {#action-campaign-view}

Press [[action:filament-actions::view.single.label]] on a row in the list to open a campaign. The page has:

- Action buttons at the top: [[action:mailing::resource.actions.submit_review]], [[action:mailing::resource.actions.approve]], [[action:mailing::resource.actions.send_now]] and [[action:mailing::resource.actions.cancel]]. Only the buttons that fit the current status and your role are shown. Every button asks for confirmation.
- [[label:mailing::resource.sections.campaign_summary]]: description, status, who started it, audience type and size, and the totals ([[label:mailing::resource.fields.total_count]], [[label:mailing::resource.fields.success_count]], [[label:mailing::resource.fields.failed_count]]).
- [[label:mailing::resource.sections.approval]]: who approved and when. Shown once the campaign is approved.
- [[label:mailing::resource.sections.snapshot]]: the subject and body as used in this campaign, with [[action:mailing::resource.actions.view_full_content]].
- The [metrics](#metrics) below.
- The [[label:prospects::resource.sections.recipients]] tab, see [Recipient pages](#recipient-pages).

The same submit, approve, send and cancel buttons also appear on the rows of the campaign list.

## [[action:mailing::resource.actions.submit_review]] {#action-campaign-submit}

Available for a [[enum:Modules\Mailing\Enums\CampaignStatus::draft]] campaign to admins, campaign managers and marketers. After you confirm, the status becomes [[enum:Modules\Mailing\Enums\CampaignStatus::review]] and you get the message "[[label:mailing::resource.notifications.submitted_review]]". Next, a campaign manager or admin [approves](#action-campaign-approve) it.

## [[action:mailing::resource.actions.approve]] {#action-campaign-approve}

Available for campaigns in [[enum:Modules\Mailing\Enums\CampaignStatus::draft]] or [[enum:Modules\Mailing\Enums\CampaignStatus::review]] to admins and campaign managers. Approvers can skip the review step. After you confirm, the status becomes [[enum:Modules\Mailing\Enums\CampaignStatus::approved]] and the approver and time are recorded in [[label:mailing::resource.sections.approval]].

On approval the template is checked again. The campaign needs a template, and a commercial template needs a valid preference category. If the check fails you see "[[label:mailing::resource.notifications.approve_failed]]" with the reason, and the campaign stays as it was. Fix the template (see [Email templates](#email-templates)) and create a new campaign if necessary.

After approval: [send now](#action-campaign-send_now) or wait for the [scheduled time](#scheduling).

## [[action:mailing::resource.actions.send_now]] {#action-campaign-send_now}

Available for an [[enum:Modules\Mailing\Enums\CampaignStatus::approved]] campaign without a scheduled date, to admins and campaign managers. Follow-up campaigns cannot be sent this way. The confirmation says: "[[label:mailing::resource.actions.send_now_confirm]]"

After you confirm:

1. A message "[[label:mailing::resource.notifications.sent_now]]" stays on screen. You can keep working.
2. The status becomes [[enum:Modules\Mailing\Enums\CampaignStatus::sending]].
3. When the campaign finishes, you get a message with the number sent, failed and skipped, and a notification in the bell with a [[action:mailing::resource.actions.view_campaign]] button. If the campaign failed, you get a failure message in the same way.

If the campaign cannot be queued, you see "[[label:mailing::resource.notifications.send_now_failed]]". The campaign stays approved and you can try again later. If someone else already started it, you see "[[label:mailing::resource.notifications.send_now_unavailable]]".

## [[action:mailing::resource.actions.cancel]] {#action-campaign-cancel}

Admin only. Available for campaigns in [[enum:Modules\Mailing\Enums\CampaignStatus::draft]], [[enum:Modules\Mailing\Enums\CampaignStatus::review]] or [[enum:Modules\Mailing\Enums\CampaignStatus::approved]]. The confirmation warns: "[[label:mailing::resource.actions.cancel_confirm]]" The campaign becomes [[enum:Modules\Mailing\Enums\CampaignStatus::cancelled]]. You cannot cancel a campaign that is already sending or finished.

## [[action:mailing::resource.actions.view_full_content]] {#action-campaign-view_full_content}

In [[label:mailing::resource.sections.snapshot]] on the [campaign page](#action-campaign-view). It opens the complete email as this campaign sends it, in the window "[[label:mailing::resource.actions.full_content_modal_title]]". This is the copy taken when the campaign was created, not the current template. Use [[action:mailing::resource.actions.close]] to close it.

## Metrics {#metrics}

At the bottom of the [campaign page](#action-campaign-view) you see the results:

- [[label:mailing::resource.metrics.sent]] and [[label:mailing::resource.metrics.delivered]]
- [[label:mailing::resource.metrics.opens]]: indicative only. Apple Mail Privacy Protection and security proxies can inflate or hide opens.
- [[label:mailing::resource.metrics.clicks]]: the most reliable signal of interest.
- [[label:mailing::resource.metrics.hard_bounces]] and [[label:mailing::resource.metrics.soft_bounces]]
- [[label:mailing::resource.metrics.complained]] and [[label:mailing::resource.metrics.unsubscribed]]

A warning appears when hard bounces go above 5% or spam complaints above 0.08%. Too many bounces or complaints damage the reputation of our sending domain. Stop and check your audience when you see this. The system also warns admins and campaign managers in the bell; see [Background jobs](#background-jobs).

## Recipient pages {#recipient-pages}

The [[label:prospects::resource.sections.recipients]] tab on the [campaign page](#action-campaign-view) lists every email of the campaign: prospect, email, status ([[label:prospects::resource.options.status.sent]], [[label:prospects::resource.options.status.failed]], [[label:prospects::resource.options.status.skipped]] or [[label:prospects::resource.options.status.unsubscribed]]), when it was sent, whether it was opened or clicked, and the last activity.

## [[action:filament-actions::view.single.label]] (recipient) {#action-campaign-messages-view}

Press [[action:filament-actions::view.single.label]] on a row of the recipient tab to open the details of that email: prospect, address, status, send time and an error message when something went wrong. Below that you see the [[label:mailing::resource.sections.engagement]] (for example [[enum:Modules\Mailing\Enums\MessageEventType::delivered]], [[enum:Modules\Mailing\Enums\MessageEventType::opened]] and [[enum:Modules\Mailing\Enums\MessageEventType::clicked]]) with time and clicked link, and the subject and body that were sent.

Remember: "[[label:mailing::resource.sections.engagement_note]]"

## Email templates {#email-templates}

A template is the reusable content of an email: name, category, subject and body. Open [[nav:mailing::resource.navigation_group > mailing::resource.plural_model_label]]. The list shows [[label:mailing::resource.fields.name]], [[label:mailing::resource.fields.category]], [[label:mailing::resource.fields.version]], [[label:mailing::resource.fields.subject]] and [[label:mailing::resource.fields.updated_at]]. You can filter on [[label:mailing::resource.fields.category]].

Changing a template's subject or body does not change campaigns that already exist: those keep their own copy. A changed category or preference category does apply to an existing campaign that has not been approved yet, because approval reads it again.

## [[action:filament-actions::create.single.label?label=mailing::resource.model_label]] {#action-email-template-create}

Press [[action:filament-actions::create.single.label?label=mailing::resource.model_label]] on the template list. Fill in:

- [[label:mailing::resource.sections.template_details]]: [[label:mailing::resource.fields.name]] (must be unique), [[label:mailing::resource.fields.category]] ([[enum:Modules\Mailing\Enums\TemplateCategory::commercial]] or [[enum:Modules\Mailing\Enums\TemplateCategory::transactional]]), [[label:mailing::resource.fields.preference_category]] and [[label:mailing::resource.fields.subject]].
- [[label:mailing::resource.sections.content]]: the body of the email, in the text editor.

The [[label:mailing::resource.fields.preference_category]] is required for commercial templates only. Prospects who opted out of that category are skipped. See also [Template tokens](#template-tokens).

## [[action:filament-actions::edit.single.label]] {#action-email-template-edit}

Press [[action:filament-actions::edit.single.label]] on a row of the template list to change a template. Existing campaigns are not affected, see [Email templates](#email-templates). To remove the template, use [Delete](#action-email-template-delete).

## [[action:filament-actions::delete.single.label]] {#action-email-template-delete}

On the edit page of a template, press [[action:filament-actions::delete.single.label]] and confirm. To remove several templates at once, use [Delete selected](#action-email-template-delete_bulk).

## [[action:filament-actions::delete.multiple.label]] {#action-email-template-delete_bulk}

On the template list, tick one or more rows and choose [[action:filament-actions::delete.multiple.label]] in the bulk menu. Confirm to remove them.

## Template tokens {#template-tokens}

In the body and subject of a template you can use tokens that are replaced for every recipient:

| Token | Replaced by |
| --- | --- |
| `{{ name }}` | [[label:mailing::resource.system_vars.name_label]], for example "[[label:mailing::resource.system_vars.name_example]]" |
| `{{ regio }}` | [[label:mailing::resource.system_vars.regio_label]], for example "[[label:mailing::resource.system_vars.regio_example]]" |

The unsubscribe link is added automatically in the footer of every email. You do not have to add it.

The panel next to the template form also has [[label:mailing::resource.fields.variables]]. Use [[action:mailing::resource.actions.add_variable]] to note down extra variables. They are notes for your colleagues only. They do not create new tokens.

## Start a campaign from Prospects {#start-from-prospects}

You can also start a campaign straight from the prospect list: open [[nav:prospects::resource.navigation_group > prospects::resource.plural_model_label]], select the prospects and choose [[action:prospects::resource.actions.execute_campaign.label]] in the bulk menu. Choose a template and give the campaign a description.

- Admins and campaign managers: the campaign is approved and sent immediately. You get a message and, when it finishes, a notification with a [[action:prospects::resource.actions.execute_campaign.view_campaign]] button.
- Marketers: a campaign with the selected prospects as manual audience is created in [[enum:Modules\Mailing\Enums\CampaignStatus::review]]. A campaign manager or admin still has to [approve](#action-campaign-approve) it.

Unsubscribed prospects in your selection are skipped, and you get a message about it. If everyone is unsubscribed or nobody has an email address, no campaign is created.

## Background jobs {#background-jobs}

Several things happen automatically, without you doing anything:

- Every minute: campaigns with a [scheduled time](#scheduling) that has come are sent.
- Every 5 minutes: the [A/B winner](#ab-test) is picked and [follow-up campaigns](#follow-up) are started when they are due.
- Every 30 minutes: bounces are processed. A hard bounce puts the address on the suppression list so it never receives mail again. A soft bounce does so after 3 times.
- Every 30 minutes: the system checks bounce and complaint percentages. If they are too high, admins, campaign managers and super admins get a notification in the bell.

Because of this, results on the [campaign page](#action-campaign-view) can take a few minutes to show up.

## Frequently asked questions {#faq}

**I cannot see the Approve or Send now button.**
The button is only shown for the right status and role. See [Who can do what](#access) and [the lifecycle](#campaign-lifecycle). A marketer cannot approve, and [Send now](#action-campaign-send_now) is not available for campaigns with a scheduled date or for follow-ups.

**I made a mistake in a campaign.**
There is no edit page. As long as the campaign is not sending, an admin can [cancel](#action-campaign-cancel) it. Then create a new one.

**I changed a template but the campaign still shows the old text.**
The campaign keeps the copy it took when it was created. See [Email templates](#email-templates).

**Why are some recipients skipped?**
They unsubscribed, opted out of the template category or have a suppressed address. See [Audience](#audience).

**Do recipients get a link to manage their preferences?**
No. Every email has an unsubscribe link in the footer. That link unsubscribes the recipient from all emails.
