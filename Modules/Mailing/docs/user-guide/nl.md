---
module: Mailing
title: Handleiding Mailing
navigation_label: Mailing
locale: nl
summary: Hoe je e-mailcampagnes aanmaakt, goedkeurt, verstuurt en opvolgt, en e-mailsjablonen beheert.
---
## Overzicht {#overview}

De module Mailing verstuurt e-mailcampagnes naar prospects. Je kiest een [e-mailsjabloon](#email-templates), bepaalt wie de mail krijgt ([doelgroep](#audience)), laat de campagne goedkeuren en verstuurt ze. Daarna volg je de resultaten op in de [statistieken](#metrics) en de [lijst met ontvangers](#recipient-pages).

Je vindt de module op twee plaatsen in het menu:

- Campagnes: [[nav:navigation.groups.mailing > mailing::resource.campaign.plural_model_label]]
- E-mailsjablonen: [[nav:mailing::resource.navigation_group > mailing::resource.plural_model_label]]

Beide items staan in een andere menugroep. Zo is het menu nu opgebouwd.

In het kort: [maak een sjabloon](#action-email-template-create), [maak een campagne](#action-campaign-create), [dien ze in](#action-campaign-submit), [keur ze goed](#action-campaign-approve) en [verstuur ze](#action-campaign-send_now). De volledige weg staat in [de levenscyclus van een campagne](#campaign-lifecycle).

## Wie mag wat {#access}

Iedereen die het adminpaneel kan gebruiken, kan de module Mailing openen, behalve gebruikers met de rol technician. Deze handleiding volgt dezelfde regel.

De rollen campaign manager en marketer geven enkel rechten in Mailing. Om het adminpaneel te openen heeft een gebruiker daarnaast een paneelrol nodig, zoals admin of viewer.

| Actie | Wie |
| --- | --- |
| Campagnes en resultaten bekijken | Iedereen met toegang |
| [[action:filament-actions::create.single.label?label=mailing::resource.campaign.model_label]] en [[action:mailing::resource.actions.submit_review]] | Admin, campaign manager, marketer |
| [[action:mailing::resource.actions.approve]] en [[action:mailing::resource.actions.send_now]] | Admin, campaign manager |
| [[action:mailing::resource.actions.cancel]] | Enkel admin |
| E-mailsjablonen beheren | Iedereen met toegang |

Een super admin mag alles. Een marketer kan een campagne nooit goedkeuren: een campagne van een marketer heeft altijd een campaign manager of admin nodig. Een knop waarvoor je geen rechten hebt, wordt gewoon niet getoond.

## De levenscyclus van een campagne {#campaign-lifecycle}

Een campagne doorloopt deze stappen:

1. [Maak de campagne aan](#action-campaign-create). Ze start als [[enum:Modules\Mailing\Enums\CampaignStatus::draft]].
2. [Dien ze in voor beoordeling](#action-campaign-submit). De status wordt [[enum:Modules\Mailing\Enums\CampaignStatus::review]].
3. Een campaign manager of admin [keurt ze goed](#action-campaign-approve). De status wordt [[enum:Modules\Mailing\Enums\CampaignStatus::approved]]. Wie mag goedkeuren, kan een concept ook rechtstreeks goedkeuren, zonder beoordelingsstap.
4. De campagne wordt verstuurd: ofwel met [Nu versturen](#action-campaign-send_now), ofwel automatisch op het [ingeplande moment](#scheduling). Tijdens het versturen is de status [[enum:Modules\Mailing\Enums\CampaignStatus::sending]].
5. Als alle mails verwerkt zijn, wordt de campagne [[enum:Modules\Mailing\Enums\CampaignStatus::completed]]. Is er niets verstuurd en is minstens één mail mislukt, dan wordt ze [[enum:Modules\Mailing\Enums\CampaignStatus::failed]].

Een concept, een campagne in beoordeling en een goedgekeurde campagne kunnen nog [geannuleerd](#action-campaign-cancel) worden (enkel admin). Alle statussen staan in [Campagnestatussen](#statuses).

Goed om te weten:

- Een campagne heeft geen bewerkpagina. Controleer het formulier dus goed voor je het aanmaakt.
- Er is geen knop om een campagne terug naar concept te zetten.
- Je kan een campagne niet annuleren terwijl ze verstuurd wordt.
- Het versturen gebeurt op de achtergrond, zie [Achtergrondtaken](#background-jobs).

## Campagnestatussen {#statuses}

| Status | Betekenis |
| --- | --- |
| [[enum:Modules\Mailing\Enums\CampaignStatus::draft]] | Net aangemaakt. Kan ingediend, goedgekeurd of geannuleerd worden. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::review]] | Wacht op goedkeuring. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::approved]] | Klaar om te versturen, handmatig of op het ingeplande moment. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::sending]] | De mails worden verstuurd. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::completed]] | Afgerond. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::failed]] | Afgerond zonder dat één mail verstuurd is, terwijl minstens één mail mislukte. |
| [[enum:Modules\Mailing\Enums\CampaignStatus::cancelled]] | Geannuleerd. Definitief. |

De campagnelijst ververst zichzelf om de 5 seconden, zodat je de status ziet veranderen zonder de pagina te herladen. Je kan de lijst filteren op [[label:mailing::resource.fields.status]].

## Een campagne aanmaken {#action-campaign-create}

Open [[nav:navigation.groups.mailing > mailing::resource.campaign.plural_model_label]] en klik op [[action:filament-actions::create.single.label?label=mailing::resource.campaign.model_label]]. Het formulier heeft deze secties:

**[[label:mailing::resource.sections.campaign_details]]**

- [[label:mailing::resource.fields.template]]: het e-mailsjabloon dat je gebruikt. De campagne neemt op dit moment een kopie van het sjabloon. Latere wijzigingen aan het sjabloon hebben geen invloed op deze campagne.
- [[label:mailing::resource.fields.description]]: de naam van de campagne, zoals je ze in de lijst ziet.
- [[label:mailing::resource.fields.subject]]: ingevuld vanuit het sjabloon. Je kan het enkel voor deze campagne aanpassen.

**[[label:mailing::resource.sections.ab_test]]**: standaard ingeklapt, zie [A/B-test op onderwerp](#ab-test).

**[[label:mailing::resource.sections.audience]]**: zie [Doelgroep](#audience) en [Inplannen](#scheduling).

**[[label:mailing::resource.sections.followup]]**: standaard ingeklapt, zie [Opvolgcampagnes](#follow-up).

Na het opslaan staat de campagne als [[enum:Modules\Mailing\Enums\CampaignStatus::draft]] en kom je op de [detailpagina](#action-campaign-view). De volgende stap is [Indienen voor beoordeling](#action-campaign-submit) of, als je dat mag, [Goedkeuren](#action-campaign-approve).

## Doelgroep {#audience}

In [[label:mailing::resource.sections.audience]] kies je [[label:mailing::resource.fields.audience_type]]:

- [[enum:Modules\Mailing\Enums\AudienceType::all_subscribed]]: elke ingeschreven prospect met een e-mailadres.
- [[enum:Modules\Mailing\Enums\AudienceType::segment]]: prospects die aan de regels voldoen die je opstelt. Kies bij [[label:mailing::resource.fields.segment_operator]] of alle regels of minstens één regel moet kloppen, en klik voor elke regel op [[action:mailing::resource.actions.add_rule]]. Een regel is één van:
  - [[label:mailing::resource.fields.rule_has_event]]: bijvoorbeeld prospects die in een bepaalde campagne geklikt hebben, eventueel binnen de laatste N dagen.
  - [[label:mailing::resource.fields.rule_has_no_event]]: het omgekeerde.
  - [[label:mailing::resource.fields.rule_prospect_field]]: taal, federatie of regio is gelijk aan een waarde, of komt voor in een lijst gescheiden door komma's (bijvoorbeeld `RBFA,LBFA`).
- [[enum:Modules\Mailing\Enums\AudienceType::manual]]: je kiest zelf de contacten bij [[label:mailing::resource.fields.manual_prospects]]. Enkel ingeschreven contacten met een e-mailadres kunnen gekozen worden.

Klik op [[action:mailing::resource.actions.preview_audience]] om de doelgroep te controleren voor je de campagne aanmaakt. Zie [Voorbeeld van de doelgroep](#action-campaign-preview_audience).

Prospects die zich afgemeld hebben, die de voorkeurscategorie van het sjabloon uitgeschakeld hebben of waarvan het adres geblokkeerd is (bijvoorbeeld na een hard bounce), krijgen de mail nooit, ook al passen ze in de doelgroep. Je ziet ze als overgeslagen in de [lijst met ontvangers](#recipient-pages).

## [[action:mailing::resource.actions.preview_audience]] {#action-campaign-preview_audience}

In de sectie doelgroep van het aanmaakformulier. Er opent een venster met het aantal ontvangers en de eerste 100 daarvan (club, sport, federatie, regio, taal, contactpersoon en e-mailadressen). Zijn het er meer, dan zie je hoeveel er nog bijkomen.

Het venster herinnert je er ook aan dat bij het echte versturen nog een paar contacten kunnen wegvallen: afgemelde categorieën en geblokkeerde adressen. Met [[action:mailing::resource.actions.close]] ga je terug naar het formulier.

## Inplannen {#scheduling}

Vul [[label:mailing::resource.fields.scheduled_at]] in om de campagne automatisch op een gekozen moment te versturen. De tijd is in de tijdzone Europe/Brussels (het veld [[label:mailing::resource.fields.timezone]] ligt vast). De datum mag niet in het verleden liggen.

- Met een datum: zodra de campagne [goedgekeurd](#action-campaign-approve) is, wordt ze op dat moment verstuurd zonder verdere actie. De knop [[action:mailing::resource.actions.send_now]] is dan niet beschikbaar.
- Zonder datum: je verstuurt ze zelf met [Nu versturen](#action-campaign-send_now).

De planner controleert elke minuut, zie [Achtergrondtaken](#background-jobs).

## A/B-test op onderwerp {#ab-test}

Met een A/B-test probeer je twee onderwerpen uit op een klein deel van de doelgroep en verstuur je het beste naar de rest. Open [[label:mailing::resource.sections.ab_test]] en vul [[label:mailing::resource.fields.ab_subject_b]] in. Het onderwerp van de campagne wordt variant A. Laat variant B leeg als je geen test wil.

- [[label:mailing::resource.fields.ab_split_percent]]: standaard 10, maximum 50. Bij 10 krijgt 10% variant A en 10% variant B. De overige 80% krijgt de winnaar.
- [[label:mailing::resource.fields.ab_winner_after_hours]]: standaard 4. Na deze tijd kiest het systeem de winnaar.

De winnaar is de variant met het hoogste klikpercentage (unieke klikken). Bij een gelijkstand wint A. Deze controle loopt elke 5 minuten, zie [Achtergrondtaken](#background-jobs).

## Opvolgcampagnes {#follow-up}

Een opvolgcampagne stuurt automatisch een tweede campagne naar een deel van de doelgroep van de eerste. Open [[label:mailing::resource.sections.followup]] in het formulier van de eerste campagne:

- [[label:mailing::resource.fields.followup_campaign]]: kies een campagne die al goedgekeurd is. Ze wordt verstuurd naar de doelgroep van deze campagne.
- [[label:mailing::resource.fields.followup_trigger]]: wie de opvolging krijgt: [[enum:Modules\Mailing\Enums\FollowUpTrigger::clicked]], [[enum:Modules\Mailing\Enums\FollowUpTrigger::not_clicked]], [[enum:Modules\Mailing\Enums\FollowUpTrigger::opened]] of [[enum:Modules\Mailing\Enums\FollowUpTrigger::not_opened]]. Openingen zijn een zwak signaal, kies dus liever voor klikken.
- [[label:mailing::resource.fields.followup_delay_hours]]: standaard 24. Bijvoorbeeld 72 is drie dagen nadat de eerste campagne afgerond is.

Een opvolgcampagne wordt door het systeem verstuurd, dus [Nu versturen](#action-campaign-send_now) is daarvoor niet beschikbaar. De controle loopt elke 5 minuten, zie [Achtergrondtaken](#background-jobs).

## De campagnepagina {#action-campaign-view}

Klik op [[action:filament-actions::view.single.label]] bij een rij in de lijst om een campagne te openen. De pagina bevat:

- Actieknoppen bovenaan: [[action:mailing::resource.actions.submit_review]], [[action:mailing::resource.actions.approve]], [[action:mailing::resource.actions.send_now]] en [[action:mailing::resource.actions.cancel]]. Je ziet enkel de knoppen die passen bij de huidige status en je rol. Elke knop vraagt om bevestiging.
- [[label:mailing::resource.sections.campaign_summary]]: omschrijving, status, wie ze gestart heeft, type en grootte van de doelgroep, en de totalen ([[label:mailing::resource.fields.total_count]], [[label:mailing::resource.fields.success_count]], [[label:mailing::resource.fields.failed_count]]).
- [[label:mailing::resource.sections.approval]]: wie en wanneer goedgekeurd heeft. Zichtbaar zodra de campagne goedgekeurd is.
- [[label:mailing::resource.sections.snapshot]]: het onderwerp en de tekst zoals ze in deze campagne gebruikt worden, met [[action:mailing::resource.actions.view_full_content]].
- Onderaan de [statistieken](#metrics).
- Het tabblad [[label:prospects::resource.sections.recipients]], zie [Ontvangerspagina's](#recipient-pages).

Dezelfde knoppen voor indienen, goedkeuren, versturen en annuleren staan ook bij de rijen van de campagnelijst.

## [[action:mailing::resource.actions.submit_review]] {#action-campaign-submit}

Beschikbaar voor een campagne met status [[enum:Modules\Mailing\Enums\CampaignStatus::draft]] voor admins, campaign managers en marketers. Na bevestiging wordt de status [[enum:Modules\Mailing\Enums\CampaignStatus::review]] en zie je de melding "[[label:mailing::resource.notifications.submitted_review]]". Daarna [keurt](#action-campaign-approve) een campaign manager of admin ze goed.

## [[action:mailing::resource.actions.approve]] {#action-campaign-approve}

Beschikbaar voor campagnes met status [[enum:Modules\Mailing\Enums\CampaignStatus::draft]] of [[enum:Modules\Mailing\Enums\CampaignStatus::review]] voor admins en campaign managers. Wie mag goedkeuren, kan de beoordelingsstap overslaan. Na bevestiging wordt de status [[enum:Modules\Mailing\Enums\CampaignStatus::approved]] en worden goedkeurder en tijdstip bewaard in [[label:mailing::resource.sections.approval]].

Bij het goedkeuren wordt het sjabloon opnieuw gecontroleerd. De campagne heeft een sjabloon nodig, en een commercieel sjabloon heeft een geldige voorkeurscategorie nodig. Mislukt de controle, dan zie je "[[label:mailing::resource.notifications.approve_failed]]" met de reden, en blijft de campagne zoals ze was. Pas het sjabloon aan (zie [E-mailsjablonen](#email-templates)) en maak zo nodig een nieuwe campagne aan.

Na goedkeuring: [verstuur nu](#action-campaign-send_now) of wacht op het [ingeplande moment](#scheduling).

## [[action:mailing::resource.actions.send_now]] {#action-campaign-send_now}

Beschikbaar voor een campagne met status [[enum:Modules\Mailing\Enums\CampaignStatus::approved]] zonder ingeplande datum, voor admins en campaign managers. Opvolgcampagnes kunnen niet op deze manier verstuurd worden. De bevestiging zegt: "[[label:mailing::resource.actions.send_now_confirm]]"

Na bevestiging:

1. Een melding "[[label:mailing::resource.notifications.sent_now]]" blijft op het scherm staan. Je kan gewoon verder werken.
2. De status wordt [[enum:Modules\Mailing\Enums\CampaignStatus::sending]].
3. Wanneer de campagne klaar is, krijg je een melding met het aantal verstuurde, mislukte en overgeslagen mails, en een melding in het belletje met een knop [[action:mailing::resource.actions.view_campaign]]. Mislukt de campagne, dan krijg je op dezelfde manier een melding daarover.

Kan de campagne niet in de wachtrij gezet worden, dan zie je "[[label:mailing::resource.notifications.send_now_failed]]". De campagne blijft goedgekeurd en je kan het later opnieuw proberen. Heeft iemand anders ze al gestart, dan zie je "[[label:mailing::resource.notifications.send_now_unavailable]]".

## [[action:mailing::resource.actions.cancel]] {#action-campaign-cancel}

Enkel admin. Beschikbaar voor campagnes met status [[enum:Modules\Mailing\Enums\CampaignStatus::draft]], [[enum:Modules\Mailing\Enums\CampaignStatus::review]] of [[enum:Modules\Mailing\Enums\CampaignStatus::approved]]. De bevestiging waarschuwt: "[[label:mailing::resource.actions.cancel_confirm]]" De campagne wordt [[enum:Modules\Mailing\Enums\CampaignStatus::cancelled]]. Een campagne die al verstuurd wordt of afgerond is, kan je niet annuleren.

## [[action:mailing::resource.actions.view_full_content]] {#action-campaign-view_full_content}

In [[label:mailing::resource.sections.snapshot]] op de [campagnepagina](#action-campaign-view). Het opent de volledige e-mail zoals deze campagne ze verstuurt, in het venster "[[label:mailing::resource.actions.full_content_modal_title]]". Dit is de kopie van bij het aanmaken van de campagne, niet de huidige sjabloon. Sluit het venster met [[action:mailing::resource.actions.close]].

## Statistieken {#metrics}

Onderaan de [campagnepagina](#action-campaign-view) zie je de resultaten:

- [[label:mailing::resource.metrics.sent]] en [[label:mailing::resource.metrics.delivered]]
- [[label:mailing::resource.metrics.opens]]: enkel indicatief. Apple Mail Privacy Protection en beveiligingsproxy's kunnen openingen opdrijven of verbergen.
- [[label:mailing::resource.metrics.clicks]]: het betrouwbaarste signaal van interesse.
- [[label:mailing::resource.metrics.hard_bounces]] en [[label:mailing::resource.metrics.soft_bounces]]
- [[label:mailing::resource.metrics.complained]] en [[label:mailing::resource.metrics.unsubscribed]]

Er verschijnt een waarschuwing als de hard bounces boven 5% komen of de spamklachten boven 0,08%. Te veel bounces of klachten schaden de reputatie van ons verzenddomein. Stop en controleer je doelgroep als je dit ziet. Het systeem waarschuwt ook admins en campaign managers via het belletje, zie [Achtergrondtaken](#background-jobs).

## Ontvangerspagina's {#recipient-pages}

Het tabblad [[label:prospects::resource.sections.recipients]] op de [campagnepagina](#action-campaign-view) toont elke mail van de campagne: prospect, e-mailadres, status ([[label:prospects::resource.options.status.sent]], [[label:prospects::resource.options.status.failed]], [[label:prospects::resource.options.status.skipped]] of [[label:prospects::resource.options.status.unsubscribed]]), verzendmoment, of de mail geopend of aangeklikt is, en de laatste activiteit.

## [[action:filament-actions::view.single.label]] (ontvanger) {#action-campaign-messages-view}

Klik op [[action:filament-actions::view.single.label]] bij een rij van het tabblad met ontvangers om de details van die mail te openen: prospect, adres, status, verzendmoment en een foutmelding als er iets misliep. Daaronder zie je de [[label:mailing::resource.sections.engagement]] (bijvoorbeeld [[enum:Modules\Mailing\Enums\MessageEventType::delivered]], [[enum:Modules\Mailing\Enums\MessageEventType::opened]] en [[enum:Modules\Mailing\Enums\MessageEventType::clicked]]) met tijdstip en aangeklikte link, en het onderwerp en de tekst die verstuurd zijn.

Let op: "[[label:mailing::resource.sections.engagement_note]]"

## E-mailsjablonen {#email-templates}

Een sjabloon is de herbruikbare inhoud van een e-mail: naam, categorie, onderwerp en tekst. Open [[nav:mailing::resource.navigation_group > mailing::resource.plural_model_label]]. De lijst toont [[label:mailing::resource.fields.name]], [[label:mailing::resource.fields.category]], [[label:mailing::resource.fields.version]], [[label:mailing::resource.fields.subject]] en [[label:mailing::resource.fields.updated_at]]. Je kan filteren op [[label:mailing::resource.fields.category]].

Een wijziging aan een sjabloon verandert bestaande campagnes niet: die houden hun eigen kopie. Een wijziging geldt enkel voor campagnes die je nadien aanmaakt.

## [[action:filament-actions::create.single.label?label=mailing::resource.model_label]] {#action-email-template-create}

Klik op [[action:filament-actions::create.single.label?label=mailing::resource.model_label]] in de lijst met sjablonen. Vul in:

- [[label:mailing::resource.sections.template_details]]: [[label:mailing::resource.fields.name]] (moet uniek zijn), [[label:mailing::resource.fields.category]] ([[enum:Modules\Mailing\Enums\TemplateCategory::commercial]] of [[enum:Modules\Mailing\Enums\TemplateCategory::transactional]]), [[label:mailing::resource.fields.preference_category]] en [[label:mailing::resource.fields.subject]].
- [[label:mailing::resource.sections.content]]: de tekst van de e-mail, in de teksteditor.

De [[label:mailing::resource.fields.preference_category]] is enkel verplicht voor commerciële sjablonen. Prospects die die categorie uitgeschakeld hebben, worden overgeslagen. Zie ook [Sjabloonvariabelen](#template-tokens).

## [[action:filament-actions::edit.single.label]] {#action-email-template-edit}

Klik op [[action:filament-actions::edit.single.label]] bij een rij in de lijst met sjablonen om een sjabloon te wijzigen. Bestaande campagnes veranderen niet, zie [E-mailsjablonen](#email-templates). Om het sjabloon te verwijderen, gebruik je [Verwijderen](#action-email-template-delete).

## [[action:filament-actions::delete.single.label]] {#action-email-template-delete}

Klik op de bewerkpagina van een sjabloon op [[action:filament-actions::delete.single.label]] en bevestig. Wil je er meerdere tegelijk verwijderen, gebruik dan [Geselecteerde verwijderen](#action-email-template-delete_bulk).

## [[action:filament-actions::delete.multiple.label]] {#action-email-template-delete_bulk}

Vink in de lijst met sjablonen één of meer rijen aan en kies [[action:filament-actions::delete.multiple.label]] in het menu voor bulkacties. Bevestig om ze te verwijderen.

## Sjabloonvariabelen {#template-tokens}

In de tekst en het onderwerp van een sjabloon kan je variabelen gebruiken die voor elke ontvanger vervangen worden:

| Variabele | Wordt vervangen door |
| --- | --- |
| `{{ name }}` | [[label:mailing::resource.system_vars.name_label]], bijvoorbeeld "[[label:mailing::resource.system_vars.name_example]]" |
| `{{ regio }}` | [[label:mailing::resource.system_vars.regio_label]], bijvoorbeeld "[[label:mailing::resource.system_vars.regio_example]]" |

De afmeldlink wordt automatisch onderaan elke e-mail toegevoegd. Je moet hem niet zelf toevoegen.

Naast het sjabloonformulier staat ook [[label:mailing::resource.fields.variables]]. Met [[action:mailing::resource.actions.add_variable]] noteer je extra variabelen. Dat zijn enkel notities voor je collega's. Er worden geen nieuwe variabelen mee gemaakt.

## Een campagne starten vanuit Prospects {#start-from-prospects}

Je kan een campagne ook rechtstreeks starten vanuit de prospectlijst: open [[nav:prospects::resource.navigation_group > prospects::resource.plural_model_label]], selecteer de prospects en kies [[action:prospects::resource.actions.execute_campaign.label]] in het menu voor bulkacties. Kies een sjabloon en geef de campagne een omschrijving.

- Admins en campaign managers: de campagne wordt meteen goedgekeurd en verstuurd. Je krijgt een melding en, als ze klaar is, een melding met een knop [[action:prospects::resource.actions.execute_campaign.view_campaign]].
- Marketers: er wordt een campagne met de geselecteerde prospects als handmatige doelgroep aangemaakt met status [[enum:Modules\Mailing\Enums\CampaignStatus::review]]. Een campaign manager of admin moet ze nog [goedkeuren](#action-campaign-approve).

Afgemelde prospects in je selectie worden overgeslagen en je krijgt daarover een melding. Zijn alle prospects afgemeld of heeft niemand een e-mailadres, dan wordt er geen campagne aangemaakt.

## Achtergrondtaken {#background-jobs}

Een aantal dingen gebeurt automatisch, zonder dat je iets doet:

- Elke minuut: campagnes met een [ingepland moment](#scheduling) dat aangebroken is, worden verstuurd.
- Elke 5 minuten: de [A/B-winnaar](#ab-test) wordt gekozen en [opvolgcampagnes](#follow-up) worden gestart wanneer ze aan de beurt zijn.
- Elke 30 minuten: bounces worden verwerkt. Een hard bounce zet het adres op de blokkeerlijst, zodat het nooit meer mail krijgt. Een soft bounce doet dat na 3 keer.
- Elke 30 minuten: het systeem controleert de bounce- en klachtenpercentages. Zijn ze te hoog, dan krijgen admins, campaign managers en super admins een melding in het belletje.

Daardoor kan het enkele minuten duren voor resultaten op de [campagnepagina](#action-campaign-view) verschijnen.

## Veelgestelde vragen {#faq}

**Ik zie de knop Goedkeuren of Nu versturen niet.**
De knop wordt enkel getoond bij de juiste status en rol. Zie [Wie mag wat](#access) en [de levenscyclus](#campaign-lifecycle). Een marketer kan niet goedkeuren, en [Nu versturen](#action-campaign-send_now) is niet beschikbaar voor campagnes met een ingeplande datum of voor opvolgcampagnes.

**Ik heb een fout gemaakt in een campagne.**
Er is geen bewerkpagina. Zolang de campagne niet verstuurd wordt, kan een admin ze [annuleren](#action-campaign-cancel). Maak daarna een nieuwe aan.

**Ik heb een sjabloon aangepast, maar de campagne toont nog de oude tekst.**
De campagne houdt de kopie van bij het aanmaken. Zie [E-mailsjablonen](#email-templates).

**Waarom worden sommige ontvangers overgeslagen?**
Ze hebben zich afgemeld, de sjablooncategorie uitgeschakeld of hebben een geblokkeerd adres. Zie [Doelgroep](#audience).

**Krijgen ontvangers een link om hun voorkeuren te beheren?**
Nee. Elke e-mail heeft onderaan een afmeldlink. Die link meldt de ontvanger af voor alle e-mails.
