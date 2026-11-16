---
module: Fixture
title: Fixture guide
navigation_label: Fixture
---
## Start here {#start}

Open [[nav:navigation.groups.mailing > mailing::resource.campaign.plural_model_label]] and press
[[action:filament-actions::create.single.label?label=mailing::resource.campaign.model_label]].
Fill in [[label:mailing::resource.fields.subject]], then [jump to sending](#action-send).

### Send it {#action-send}

| Button | Effect |
| --- | --- |
| [[action:mailing::resource.actions.send_now]] | Sends now |

A new campaign starts as [[enum:Modules\Mailing\Enums\CampaignStatus::draft]].

Broken: [[action:mailing::resource.actions.does_not_exist]] [[enum:Modules\Mailing\Enums\CampaignStatus::nope]]

## No anchor here

<script>alert(1)</script>
