---
module: Core
title: Gebruikers en toegang
navigation_label: Gebruikers en toegang
locale: nl
summary: Hoe mensen zich aanmelden in het beheerpaneel, en hoe een super admin gebruikers, rollen, permissies, het auditlogboek en de login-analyse beheert.
---
## Overzicht {#overview}

Deze handleiding beschrijft wie zich in het beheerpaneel kan aanmelden en hoe accounts worden beheerd. Alle schermen uit deze handleiding staan in één menugroep:

- Gebruikers: [[nav:navigation.groups.user_management > users/resource.plural_model_label]]
- Rollen: [[nav:navigation.groups.user_management > roles/resource.plural_model_label]]
- Permissies: [[nav:navigation.groups.user_management > permissions/resource.plural_model_label]]
- Auditlogboek: [[nav:navigation.groups.user_management > activity_log/resource.plural_model_label]]
- Login-analyse: [[nav:navigation.groups.user_management > core::access_analytics.navigation_label]]

Kort samengevat: [maak een gebruiker aan](#action-user-create), kies het [accounttype](#account-types) en [geef rollen](#action-user-changeRoles). Hoe mensen zich daarna aanmelden, staat in [Aanmelden](#sign-in).

## Wie mag wat {#access}

Alleen een super admin kan deze schermen openen. Deze handleiding volgt dezelfde regel: wie haar kan lezen, kan elke knop erin gebruiken.

Welke delen van het platform iemand ziet, hangt af van zijn rollen:

| Rol | Wat ze krijgen |
| --- | --- |
| Super admin | Alles, ook deze module. |
| Admin, financieel manager, HR-manager, viewer | Het beheerpaneel, beperkt door hun andere rechten. |
| Technicus | In het beheerpaneel alleen de eigen werkorders. |
| Projectmanager | Geen beheerpaneel. Ze gebruiken de Safety-app; in het beheerpaneel zien ze een pagina dat ze nog geen toegang hebben. |
| Klant | Helemaal geen beheerpaneel. |

## Accounttypes {#account-types}

Bij het aanmaken van een gebruiker kies je het [[label:users/resource.fields.account_type]]:

- [[label:users/resource.fields.internal_account]]: een collega met een e-mailadres van het bedrijf. Je kiest hem uit de medewerkers in Cafca. Hij meldt zich aan met Microsoft en krijgt nooit een wachtwoord van dit platform.
- [[label:users/resource.fields.external_account]]: iemand zonder bedrijfsaccount. Hij krijgt een [activatiemail](#activation) en meldt zich aan met e-mailadres en wachtwoord.
- [[label:users/resource.fields.client_account]]: een contactpersoon van een klant. Je koppelt hem aan een of meer klanten. Hij kan zich niet aanmelden in het beheerpaneel.

## Aanmelden {#sign-in}

### Met Microsoft {#sign-in-microsoft}

Collega's met een bedrijfsaccount gebruiken [[action:core::auth.microsoft_login]] op de aanmeldpagina. Ze hebben geen wachtwoord en geen extra code van dit platform nodig.

### Met e-mail en wachtwoord {#sign-in-password}

Alle anderen melden zich aan met hun e-mailadres en wachtwoord. [[label:core::auth.remember]] houdt ze 30 dagen aangemeld op dat toestel.

Na een aanmelding met wachtwoord vraagt het paneel altijd een tweede stap: een code uit een authenticator-app of een code per e-mail. De eerste keer vraagt het paneel om een van de twee in te stellen. Bij aanmelden met Microsoft valt deze stap weg.

### Activatie {#activation}

Een nieuwe externe medewerker krijgt een e-mail met een link om een wachtwoord te kiezen. De link werkt 72 uur. Is hij verlopen of kwijt, [stuur hem opnieuw](#action-user-resendActivationEmail): de nieuwe link vervangt de oude.

### Wachtwoord vergeten {#password-reset}

[[label:core::auth.forgot_password_link]] op de aanmeldpagina stuurt een resetlink die 60 minuten werkt. De pagina toont dezelfde melding, of het adres nu een account heeft of niet.

Accounts die zich met Microsoft aanmelden en accounts die hun activatie nooit hebben afgerond, krijgen geen resetmail. Voor het tweede geval [stuur je de activatiemail opnieuw](#action-user-resendActivationEmail).

### Als de sessie verloopt {#session-expired}

Na een lange pauze meldt het paneel dat de sessie verlopen is, met een knop om de pagina te vernieuwen. Vernieuw en meld je opnieuw aan; niet-opgeslagen wijzigingen op die pagina gaan verloren.

## Gebruikers {#users}

De lijst toont per gebruiker de naam, de organisatie, of hij online is, wanneer hij laatst actief was en zich laatst aanmeldde, vanuit welke app, zijn e-mail en rollen. Je kunt filteren op organisatie.

### [[action:filament-actions::create.single.label?label=users/resource.model_label]] {#action-user-create}

1. Kies het [accounttype](#account-types).
2. Vul de velden voor dat type in:
   - [[label:users/resource.fields.internal_account]]: kies de [[label:users/resource.fields.employee]]. Alleen Cafca-medewerkers met een e-mailadres van het bedrijf die nog geen account hebben, staan in de lijst. Het [[label:users/resource.fields.email]] wordt voor je ingevuld.
   - [[label:users/resource.fields.external_account]]: vul [[label:users/resource.fields.name]] en [[label:users/resource.fields.email]] in. Een e-mailadres van het bedrijf wordt geweigerd: gebruik voor collega's het interne type.
   - [[label:users/resource.fields.client_account]]: vul naam en e-mail in, kies minstens één klant bij [[label:users/resource.fields.clients]] en kies of hij contacten mag beheren. De klantrol wordt automatisch toegekend.
3. Kies voor interne en externe gebruikers minstens één rol bij [[label:users/resource.fields.roles]]. De rollen super admin en klant kun je hier niet toekennen.
4. Sla op. Een externe medewerker krijgt meteen de [activatiemail](#activation).

Een e-mailadres kan maar één keer gebruikt worden.

### [[action:filament-actions::edit.single.label]] {#action-user-edit}

Wijzig de naam of het e-mailadres, of zet [[label:users/resource.fields.is_active]] uit. Uitzetten blokkeert meteen elke aanmelding, zonder het account te verwijderen.

Rollen kun je in dit formulier niet wijzigen: gebruik [[action:users/resource.actions.change_roles]]. Bij klantcontacten toont het formulier ook hun klantkoppelingen en rechten.

### [[action:users/resource.actions.change_roles]] {#action-user-changeRoles}

Op de bewerkpagina van de gebruiker. Vink de rollen aan die de gebruiker moet hebben en bevestig. Omdat rollen bepalen wat iemand kan zien, bevestig je de wijziging met je eigen authenticator-app of e-mailcode.

### [[action:users/resource.actions.resend_activation]] {#action-user-resendActivationEmail}

Op de bewerkpagina van een actieve gebruiker die zich niet met Microsoft aanmeldt en nog geen wachtwoord gekozen heeft. Het stuurt een nieuwe activatielink die 72 uur werkt. De vorige link werkt dan niet meer.

### [[action:users/resource.actions.revoke_sessions]] {#action-user-revokeSessions}

Op de bewerkpagina van de gebruiker. Het meldt de gebruiker af op elk toestel; hij moet zich opnieuw aanmelden. Gebruik het als een toestel kwijt is of een account misbruikt kan worden. De knop verschijnt alleen als het platform sessies in zijn database bewaart.

### [[action:filament-actions::delete.single.label]] {#action-user-delete}

Op de bewerkpagina van de gebruiker. Het verwijdert het account. Wil je iemand alleen blokkeren, zet dan [[label:users/resource.fields.is_active]] uit.

### [[action:filament-actions::delete.multiple.label]] {#action-user-delete_bulk}

Selecteer gebruikers in de lijst en verwijder ze samen.

## Rollen {#roles}

Een rol is een benoemde set permissies. Gebruikers krijgen hun rechten via hun rollen.

### [[action:filament-actions::create.single.label?label=roles/resource.model_label]] {#action-role-create}

Vul een naam in en vink de permissies aan die de rol geeft. Permissies zijn gegroepeerd op het eerste deel van hun naam. De naam wordt opgeslagen in kleine letters met underscores.

### [[action:filament-actions::edit.single.label]] {#action-role-edit}

Wijzig de naam of de permissies van een rol. De wijziging geldt voor elke gebruiker met die rol.

### [[action:filament-actions::delete.single.label]] {#action-role-delete}

Op de bewerkpagina van de rol. Gebruikers die alleen deze rol hadden, verliezen zijn rechten.

### [[action:filament-actions::delete.multiple.label]] {#action-role-delete_bulk}

Selecteer rollen in de lijst en verwijder ze samen.

## Permissies {#permissions}

Een permissie is één recht dat de code controleert, zoals een scherm bekijken. Nieuwe permissies komen meestal mee met een nieuwe functie; je maakt er zelden een met de hand.

### [[action:filament-actions::create.single.label?label=permissions/resource.model_label]] {#action-permission-create}

Vul de naam in en laat de guard op `web` staan, tenzij een ontwikkelaar iets anders zegt.

### [[action:filament-actions::edit.single.label]] {#action-permission-edit}

Wijzig de naam of de guard van een permissie. Code die de oude naam controleert, herkent ze dan niet meer.

### [[action:filament-actions::delete.single.label]] {#action-permission-delete}

Op de bewerkpagina van de permissie. Rollen die ze hadden, verliezen dit recht.

### [[action:filament-actions::delete.multiple.label]] {#action-permission-delete_bulk}

Selecteer permissies in de lijst en verwijder ze samen.

## Auditlogboek {#audit-log}

Een lijst, alleen om te lezen, van wat er in het platform veranderde: wanneer, in welk logboek, een beschrijving, wie het deed, op welk record, de organisatie en de site, en een referentie die samenhorende regels verbindt. De nieuwste regels staan bovenaan. Filter op [[label:activity_log/resource.fields.log_name]] om te verfijnen. Hier kan niets gewijzigd of verwijderd worden.

## Login-analyse {#access-analytics}

Een overzicht van wie welke app gebruikt en van verdachte aanmeldpogingen. Kies de [[label:core::access_analytics.period_label]] (7, 30 of 90 dagen; standaard 30). Je ziet:

- hoeveel gebruikers zich mogen aanmelden, hoeveel het deden, hoeveel inactief bleven en welke apps gebruikt werden;
- adoptie en actief gebruik per app;
- mislukte, geblokkeerde en afgeremde aanmeldpogingen, gemarkeerde IP-adressen en meldingen;
- recente activiteit, recente beveiligingsgebeurtenissen en een momentopname per gebruiker.
