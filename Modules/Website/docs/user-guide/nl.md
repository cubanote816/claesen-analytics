---
module: Website
title: Website
navigation_label: Website
locale: nl
summary: Hoe je de publieke website bewerkt, nakijkt en publiceert — instellingen, projecten, media, mededelingen, pagina- en vertaalreview — en hoe je adviesaanvragen behandelt.
filament: app/Filament/Clusters/Website
---
## Overzicht {#overview}

Met de Website-schermen beheer je de publieke website: de instellingen, de projectgalerij, afbeeldingen, mededelingen, welke pagina's en vertalingen online gaan, en de adviesaanvragen die via de site binnenkomen.

Elk beheerpaneel beheert zijn eigen website. Het Claesen-paneel beheert de site van Claesen Verlichting, het Electro Bertels-paneel de site van Electro Bertels. Je ziet of wijzigt nooit de site van het andere bedrijf.

Waar je de schermen vindt:

- **Claesen-paneel:** één menu-item, [[nav:navigation.groups.content_website > website.cluster_label]], met alle schermen eronder.
- **Electro Bertels-paneel:** vier menugroepen: [[label:website.nav_groups.publishing]], [[label:website.nav_groups.content]], [[label:website.nav_groups.site_settings]] en [[label:website.nav_groups.inbox]].

Kort samengevat: wijzig een [instelling](#site-settings) of een [project](#projects), sla op en [publiceer](#publishing). Bezoekers zien een wijziging pas nadat ze gepubliceerd is.

## Wie mag wat {#access}

In het Claesen-paneel kan elke gebruiker van het paneel de Website-schermen openen, behalve de rol technicus. In het Electro Bertels-paneel kunnen de leden van Electro Bertels dat. Deze handleiding volgt dezelfde regel.

Twee acties vragen daarbovenop een extra recht: [[action:website.consultation_requests.actions.export]] en [[action:website.consultation_requests.actions.erase]]. Een knop die je niet mag gebruiken, wordt niet getoond.

## Publiceren {#publishing}

De website is een aparte kopie die vanuit het platform opnieuw wordt opgebouwd. Iets hier wijzigen verandert de website nog niet:

1. **Opslaan.** Je wijziging wordt als concept bewaard. Bezoekers zien ze nog niet.
2. **Publiceren.** Je vraagt om de website opnieuw op te bouwen met de opgeslagen wijzigingen:
   - [[action:website.publication.actions.publish_section]] op een instellingenpagina publiceert alleen de instellingen van die pagina. Als ze niets zouden veranderen op de site, krijg je een melding dat er niets te publiceren is.
   - [[action:website.publication.actions.publish_now]] bouwt de hele site opnieuw op. De huidige versie blijft online tot de nieuwe klaar is.
3. **Controleren.** Een publicatie is een aanvraag: het opbouwen duurt even. Volg ze bij [[label:website.publication_history.label]], dat elke aanvraag toont, wie ze deed en of ze gelukt of mislukt is.

Als publiceren op de server uitgeschakeld is, tonen beide knoppen een waarschuwing en wordt er niets verstuurd.

De schermen tonen hoeveel opgeslagen wijzigingen nog niet gepubliceerd zijn.

## Site-instellingen {#site-settings}

Zes pagina's bevatten de algemene informatie van de site: [[label:website.settings_sections.general.label]], [[label:website.settings_sections.contact.label]], [[label:website.settings_sections.address.label]], [[label:website.settings_sections.hours.label]], [[label:website.settings_sections.social.label]] en [[label:website.settings_sections.privacy.label]].

Ze werken allemaal op dezelfde manier. Zodra je een veld wijzigt, verschijnt onderaan een balk met [[action:website.settings_bar.discard]] en [[action:website.settings_bar.save_draft]]. Opslaan bewaart alleen de velden van die pagina, als concept. Publiceer daarna de pagina of de hele site, zoals beschreven in [Publiceren](#publishing).

### [[label:website.settings_sections.general.label]]: opslaan {#action-general-settings-page-save}

[[action:website.settings_bar.save_draft]] in de balk onderaan bewaart de algemene instellingen als concept. [[action:website.settings_bar.discard]] zet de laatst opgeslagen waarden terug.

### [[label:website.settings_sections.general.label]]: [[action:website.publication.actions.publish_section]] {#action-general-settings-page-publish_section}

Publiceert de opgeslagen algemene instellingen. Een bevestiging toont hoeveel wijzigingen er wachten.

### [[label:website.settings_sections.general.label]]: [[action:website.publication.actions.publish_now]] {#action-general-settings-page-publish_now}

Bouwt de hele site opnieuw op. Zie [Publiceren](#publishing).

### [[label:website.settings_sections.contact.label]]: opslaan {#action-contact-settings-page-save}

[[action:website.settings_bar.save_draft]] bewaart de contactinstellingen als concept; [[action:website.settings_bar.discard]] zet de laatst opgeslagen waarden terug.

### [[label:website.settings_sections.contact.label]]: [[action:website.publication.actions.publish_section]] {#action-contact-settings-page-publish_section}

Publiceert de opgeslagen contactinstellingen.

### [[label:website.settings_sections.contact.label]]: [[action:website.publication.actions.publish_now]] {#action-contact-settings-page-publish_now}

Bouwt de hele site opnieuw op.

### [[label:website.settings_sections.address.label]]: opslaan {#action-address-settings-page-save}

[[action:website.settings_bar.save_draft]] bewaart de adres- en kaartinstellingen als concept; [[action:website.settings_bar.discard]] zet de laatst opgeslagen waarden terug.

### [[label:website.settings_sections.address.label]]: [[action:website.publication.actions.publish_section]] {#action-address-settings-page-publish_section}

Publiceert de opgeslagen adres- en kaartinstellingen.

### [[label:website.settings_sections.address.label]]: [[action:website.publication.actions.publish_now]] {#action-address-settings-page-publish_now}

Bouwt de hele site opnieuw op.

### [[label:website.settings_sections.hours.label]]: opslaan {#action-opening-hours-settings-page-save}

[[action:website.settings_bar.save_draft]] bewaart de openingsuren als concept; [[action:website.settings_bar.discard]] zet de laatst opgeslagen waarden terug.

### [[label:website.settings_sections.hours.label]]: [[action:website.publication.actions.publish_section]] {#action-opening-hours-settings-page-publish_section}

Publiceert de opgeslagen openingsuren.

### [[label:website.settings_sections.hours.label]]: [[action:website.publication.actions.publish_now]] {#action-opening-hours-settings-page-publish_now}

Bouwt de hele site opnieuw op.

### [[label:website.settings_sections.social.label]]: opslaan {#action-social-links-settings-page-save}

[[action:website.settings_bar.save_draft]] bewaart de sociale links als concept; [[action:website.settings_bar.discard]] zet de laatst opgeslagen waarden terug.

### [[label:website.settings_sections.social.label]]: [[action:website.publication.actions.publish_section]] {#action-social-links-settings-page-publish_section}

Publiceert de opgeslagen sociale links.

### [[label:website.settings_sections.social.label]]: [[action:website.publication.actions.publish_now]] {#action-social-links-settings-page-publish_now}

Bouwt de hele site opnieuw op.

### [[label:website.settings_sections.privacy.label]]: opslaan {#action-privacy-consent-settings-page-save}

[[action:website.settings_bar.save_draft]] bewaart de privacy- en toestemmingsinstellingen als concept; [[action:website.settings_bar.discard]] zet de laatst opgeslagen waarden terug.

### [[label:website.settings_sections.privacy.label]]: [[action:website.publication.actions.publish_section]] {#action-privacy-consent-settings-page-publish_section}

Publiceert de opgeslagen privacy- en toestemmingsinstellingen.

### [[label:website.settings_sections.privacy.label]]: [[action:website.publication.actions.publish_now]] {#action-privacy-consent-settings-page-publish_now}

Bouwt de hele site opnieuw op.

## Projecten {#projects}

De projectgalerij op de website. Elk project heeft een titel (het webadres wordt ervan gemaakt), een categorie, de klant, de locatie en het jaar, een beschrijving, en schakelaars voor gepubliceerd, uitgelicht en de volgorde. Sleep rijen in de lijst om de volgorde te wijzigen.

Afbeeldingen: maximaal 10 MB en 8000 pixels per afbeelding. Eén uitgelichte afbeelding, plus een galerij en een detailgalerij die je kunt herschikken.

### [[action:filament-actions::create.single.label?label=website.projects.label]] {#action-project-create}

Vul het project in en sla op. Het komt op de website nadat je [publiceert](#publishing).

### [[action:filament-actions::edit.single.label]] {#action-project-edit}

Wijzig de tekst, schakelaars of afbeeldingen van een project, sla op en publiceer.

### [[action:filament-actions::delete.single.label]] {#action-project-delete}

Op de bewerkpagina van het project. Het verwijdert het project; publiceer om het van de website te halen.

### [[action:filament-actions::delete.multiple.label]] {#action-project-delete_bulk}

Selecteer projecten in de lijst en verwijder ze samen.

### [[action:website.publication.actions.publish_now]] {#action-project-publish_now}

Op de lijst en op de bewerkpagina. Bouwt de hele site opnieuw op, zie [Publiceren](#publishing).

## Mediaslots {#media-slots}

Een mediaslot is een vaste plaats op de website, zoals een kopafbeelding. De naam van het slot moet de naam zijn die de website gebruikt; vraag de webontwikkelaar als je een nieuw slot nodig hebt. Elk slot toont één afbeelding, gekozen uit de projectafbeeldingen van deze site of uit haar eigen afbeeldingen. De alternatieve tekst en het onderschrift worden automatisch gemaakt.

### [[action:filament-actions::create.single.label?label=website.media_slots.label]] {#action-media-slot-create}

Vul de naam van het slot in en kies de afbeelding. Een naam kan maar één keer per site gebruikt worden.

### [[action:filament-actions::edit.single.label]] {#action-media-slot-edit}

Kies een andere afbeelding voor het slot.

### [[action:filament-actions::delete.single.label]] {#action-media-slot-delete}

Verwijdert het slot. De plaats op de website blijft leeg tot het slot weer bestaat.

### [[action:website.publication.actions.publish_now]] {#action-media-slot-publish_now}

Bouwt de hele site opnieuw op, zie [Publiceren](#publishing).

## Mededelingen {#announcements}

Korte berichten op de website, bijvoorbeeld een sluiting tijdens de feestdagen. Een mededeling is concept, gepubliceerd of gearchiveerd, en kan een optionele begin- en einddatum hebben.

### [[action:filament-actions::create.single.label?label=website.announcements.label]] {#action-announcement-create}

Schrijf de mededeling, kies de status en eventueel de datums.

### [[action:filament-actions::edit.single.label]] {#action-announcement-edit}

Wijzig de tekst, status of datums.

### [[action:filament-actions::delete.single.label]] {#action-announcement-delete}

Verwijdert de mededeling. Wil je ze alleen verbergen, archiveer ze dan.

## Pagina-publicaties {#page-publications}

[[label:website.page_publication_review.label]] bepaalt welke websitepagina's in welke taal online gaan. De tekst van de pagina's schrijven de webontwikkelaars, dus hier valt niets te bewerken: je kijkt pagina's na en zet ze aan of uit.

Een pagina gaat van concept naar nagekeken naar gepubliceerd. Elke stap bewaart wie ze zette en wanneer.

### [[action:website.page_publication_review.actions.approve]] {#action-page-publication-review-page-approve}

Markeert een pagina als nagekeken, nadat je ze gecontroleerd hebt. Getoond zolang de pagina niet nagekeken of gepubliceerd is.

### [[action:website.page_publication_review.actions.publish]] {#action-page-publication-review-page-publish}

Zet een nagekeken pagina aan in die taal, zodat zoekmachines ze vinden. Getoond voor nagekeken pagina's.

### [[action:website.page_publication_review.actions.retire]] {#action-page-publication-review-page-retire}

Zet een gepubliceerde pagina terug naar nagekeken. Getoond voor gepubliceerde pagina's.

### [[action:website.publication.actions.publish_now]] {#action-page-publication-review-page-publish_now}

Bouwt de hele site opnieuw op zodat de paginawijzigingen bij de bezoekers komen, zie [Publiceren](#publishing).

## Vertaalreview {#translation-review}

Teksten die in één taal geschreven zijn, worden automatisch vertaald. [[label:website.translation_review.label]] toont die vertalingen, zodat iemand ze kan nakijken voor ze online gaan.

### [[action:website.translation_review.actions.edit]] {#action-translation-review-page-edit}

Verbeter een vertaling. Je verbetering wordt als concept bewaard en moet nog goedgekeurd worden.

### [[action:website.translation_review.actions.approve]] {#action-translation-review-page-approve}

Markeert een vertaling als nagekeken. Getoond zolang ze niet nagekeken of gepubliceerd is.

### [[action:website.translation_review.actions.publish]] {#action-translation-review-page-publish}

Laat een nagekeken vertaling online gaan. Getoond voor nagekeken vertalingen.

### [[action:website.translation_review.actions.retranslate]] {#action-translation-review-page-retranslate}

Vraagt een nieuwe automatische vertaling. Die vervangt de huidige, ook een goedgekeurde, en moet dus opnieuw goedgekeurd worden.

### [[action:website.publication.actions.publish_now]] {#action-translation-review-page-publish_now}

Bouwt de hele site opnieuw op, zie [Publiceren](#publishing).

## Adviesaanvragen {#consultation-requests}

[[label:website.consultation_requests.plural_label]] zijn de aanvragen die bezoekers via het websiteformulier sturen. Elke aanvraag heeft een status (nieuw, toegewezen, in behandeling, wacht op klant, afgesloten of spam), een prioriteit en de collega aan wie ze is toegewezen.

### [[action:filament-actions::create.single.label?label=website.consultation_requests.label]] {#action-consultation-request-create}

Voer een aanvraag in die op een andere manier binnenkwam, bijvoorbeeld telefonisch.

### [[action:filament-actions::edit.single.label]] {#action-consultation-request-edit}

Wijzig de status, prioriteit of toegewezen collega. Je kunt ook de naam, het e-mailadres, het telefoonnummer en het bedrijf verbeteren, bijvoorbeeld als de persoon erom vraagt. Het type, het projecttype en het bericht blijven zoals ze verstuurd werden.

### [[action:website.consultation_requests.actions.export]] {#action-consultation-request-export}

Downloadt alle aanvragen van deze site als CSV-bestand. Het vraagt het exportrecht en een bevestiging met je authenticator-app of e-mailcode. De beheerders van je bedrijf krijgen een melding dat er een export gemaakt is.

### [[action:website.consultation_requests.actions.erase]] {#action-consultation-request-erase}

Verwijdert de persoonsgegevens van een aanvraag, als de persoon daarom vraagt volgens de AVG. Het vraagt het wisrecht en een bevestiging met je authenticator-app of e-mailcode. Het kan niet ongedaan gemaakt worden; daarna wordt de knop voor die aanvraag niet meer getoond.

### [[action:filament-actions::view.single.label]] {#action-consultation-request-activities-view}

In de geschiedenis van de aanvraag: opent één regel om ze te lezen. De geschiedenis kan niet gewijzigd worden.
