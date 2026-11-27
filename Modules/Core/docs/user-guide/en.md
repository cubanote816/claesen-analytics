---
module: Core
title: Users and access
navigation_label: Users and access
locale: en
summary: How people sign in to the admin panel, and how a super admin manages users, roles, permissions, the audit log and login analytics.
---
## Overview {#overview}

This guide covers who can sign in to the admin panel and how accounts are managed. All screens in this guide sit in one menu group:

- Users: [[nav:navigation.groups.user_management > users/resource.plural_model_label]]
- Roles: [[nav:navigation.groups.user_management > roles/resource.plural_model_label]]
- Permissions: [[nav:navigation.groups.user_management > permissions/resource.plural_model_label]]
- Audit log: [[nav:navigation.groups.user_management > activity_log/resource.plural_model_label]]
- Login analytics: [[nav:navigation.groups.user_management > core::access_analytics.navigation_label]]

The short version: [create a user](#action-user-create), pick the [account type](#account-types), and [give them roles](#action-user-changeRoles). How people then sign in is described in [Signing in](#sign-in).

## Who can do what {#access}

Only a super admin can open these screens. This guide follows the same rule: if you can read it, you can use every button in it.

Which parts of the platform a user sees depends on their roles:

| Role | What they get |
| --- | --- |
| Super admin | Everything, including this module. |
| Admin, financial manager, HR manager, viewer | The admin panel, limited by their other rights. |
| Technician | Only their own work orders in the admin panel. |
| Project manager | No admin panel. They use the Safety app; in the admin panel they see a page that says they have no access yet. |
| Client | No admin panel at all. |

## Account types {#account-types}

When you create a user you choose the [[label:users/resource.fields.account_type]]:

- [[label:users/resource.fields.internal_account]]: a colleague with a company email address. You pick them from the employees in Cafca. They sign in with Microsoft and never get a password from this platform.
- [[label:users/resource.fields.external_account]]: someone without a company account. They receive an [activation email](#activation) and sign in with an email address and password.
- [[label:users/resource.fields.client_account]]: a contact person of a client. You link them to one or more clients. They cannot sign in to the admin panel.

## Signing in {#sign-in}

### With Microsoft {#sign-in-microsoft}

Colleagues with a company account use [[action:core::auth.microsoft_login]] on the sign-in page. They need no password and no extra code from this platform.

### With email and password {#sign-in-password}

Everyone else signs in with their email address and password. [[label:core::auth.remember]] keeps them signed in for 30 days on that device.

After a password sign-in the panel always asks for a second step: a code from an authenticator app or a code sent by email. The first time, the panel asks the user to set one of the two up. Microsoft sign-ins skip this step.

### Activation {#activation}

A new external worker receives an email with a link to choose a password. The link works for 72 hours. If it expired or got lost, [resend it](#action-user-resendActivationEmail): the new link replaces the old one.

### Forgotten password {#password-reset}

[[label:core::auth.forgot_password_link]] on the sign-in page sends a reset link that works for 60 minutes. The page shows the same message whether or not the address has an account.

No reset email is sent to accounts that sign in with Microsoft, or to accounts that never finished their activation. For the second case, [resend the activation email](#action-user-resendActivationEmail).

### When the session expires {#session-expired}

After a long pause the panel shows a message that the session has expired, with a button to reload the page. Reload and sign in again; unsaved changes on that page are lost.

## Users {#users}

The list shows each user's name, organization, whether they are online, when they were last active and last signed in, from which app, their email and roles. You can filter on organization.

### [[action:filament-actions::create.single.label?label=users/resource.model_label]] {#action-user-create}

1. Choose the [account type](#account-types).
2. Fill in the fields for that type:
   - [[label:users/resource.fields.internal_account]]: pick the [[label:users/resource.fields.employee]]. Only Cafca employees with a company email address who have no account yet are listed. The [[label:users/resource.fields.email]] is filled in for you.
   - [[label:users/resource.fields.external_account]]: enter [[label:users/resource.fields.name]] and [[label:users/resource.fields.email]]. A company email address is refused: use the internal type for colleagues.
   - [[label:users/resource.fields.client_account]]: enter name and email, pick at least one client under [[label:users/resource.fields.clients]], and choose whether they may manage contacts. The client role is given automatically.
3. For internal and external users, pick at least one role under [[label:users/resource.fields.roles]]. The super admin and client roles cannot be given here.
4. Save. An external worker immediately receives the [activation email](#activation).

An email address can only be used once.

### [[action:filament-actions::edit.single.label]] {#action-user-edit}

Change the name or email, or switch [[label:users/resource.fields.is_active]] off. Switching it off blocks every sign-in at once, without deleting the account.

Roles cannot be changed in this form: use [[action:users/resource.actions.change_roles]]. For client contacts the form also shows their client links and rights.

### [[action:users/resource.actions.change_roles]] {#action-user-changeRoles}

On the user's edit page. Tick the roles the user should have and confirm. Because roles decide what someone can see, you must confirm the change with your own authenticator app or email code.

### [[action:users/resource.actions.resend_activation]] {#action-user-resendActivationEmail}

On the edit page of an active user who does not sign in with Microsoft and has not chosen a password yet. It emails a new activation link that works for 72 hours. The previous link stops working.

### [[action:users/resource.actions.revoke_sessions]] {#action-user-revokeSessions}

On the user's edit page. It signs the user out on every device; they have to sign in again. Use it when a device is lost or an account may be misused. The button only appears when the platform keeps sessions in its database.

### [[action:filament-actions::delete.single.label]] {#action-user-delete}

On the user's edit page. It removes the account. To only block someone, switch [[label:users/resource.fields.is_active]] off instead.

### [[action:filament-actions::delete.multiple.label]] {#action-user-delete_bulk}

Select users in the list and delete them together.

## Roles {#roles}

A role is a named set of permissions. Users get their rights through their roles.

### [[action:filament-actions::create.single.label?label=roles/resource.model_label]] {#action-role-create}

Enter a name and tick the permissions the role gives. Permissions are grouped by the first part of their name. The name is stored in lowercase with underscores.

### [[action:filament-actions::edit.single.label]] {#action-role-edit}

Change the name or the permissions of a role. The change applies to every user with that role.

### [[action:filament-actions::delete.single.label]] {#action-role-delete}

On the role's edit page. Users who had only this role lose its rights.

### [[action:filament-actions::delete.multiple.label]] {#action-role-delete_bulk}

Select roles in the list and delete them together.

## Permissions {#permissions}

A permission is a single right that the code checks, such as viewing a screen. New permissions usually come with a new feature; you rarely create one by hand.

### [[action:filament-actions::create.single.label?label=permissions/resource.model_label]] {#action-permission-create}

Enter the name and keep the guard on `web` unless a developer tells you otherwise.

### [[action:filament-actions::edit.single.label]] {#action-permission-edit}

Change the name or guard of a permission. Code that checks the old name stops recognising it.

### [[action:filament-actions::delete.single.label]] {#action-permission-delete}

On the permission's edit page. Roles that had it lose this right.

### [[action:filament-actions::delete.multiple.label]] {#action-permission-delete_bulk}

Select permissions in the list and delete them together.

## Audit log {#audit-log}

A read-only list of what changed in the platform: when, which log, a description, who did it, on which record, the organization and site, and a reference that ties related entries together. The newest entries come first. Filter on [[label:activity_log/resource.fields.log_name]] to narrow it down. Nothing can be changed or deleted here.

## Login analytics {#access-analytics}

An overview of who uses which app and of suspicious sign-in attempts. Choose the [[label:core::access_analytics.period_label]] (7, 30 or 90 days; 30 by default). It shows:

- how many users may sign in, how many did, how many stayed inactive, and which apps were used;
- adoption and active use per app;
- failed, blocked and throttled sign-in attempts, flagged IP addresses and alerts;
- recent activity, recent security events and a snapshot per user.
