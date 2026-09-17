<?php

/*
|--------------------------------------------------------------------------
| Multi-organization program configuration
|--------------------------------------------------------------------------
|
| docs/ai/adr-multi-organization.md is the source of truth for every
| decision referenced below. This file is scaffolding for phases P3-P5;
| as of phase P1 nothing in the application reads it yet.
|
| `enforce` (ADR D4): with the flag off, the site-scoped global scope that
| phase P3 adds to shared-domain models stays inert — it neither filters
| nor throws. Only when this flag is explicitly turned on (phase P5) does
| it fail closed with MissingOrganizationContext when no site is resolved.
| Never flip this to true in production ahead of phase P5 being fully
| implemented and verified end to end.
|
| `owned_modules` (ADR D9): modules whose routes, resources, jobs and
| scheduled commands belong exclusively to one organization and are
| registered without any row-level organization_id/site_id column.
| Populated in phase P5b. Mailing is a deliberate exception (ADR D11): only
| its transactional transport is shared, so it still belongs in this list
| once P5b wires the `organization:claesen` middleware onto its routes.
|
| `platform_abilities` (ADR D5): the only Gate abilities allowed to bypass
| the organization boundary when no Eloquent model or class-string is
| involved. Starts empty on purpose — every authorization check in this
| codebase today passes a model or a class-string, so nothing needs to be
| listed yet. Never add an ability here just to work around a check that
| is missing its model argument; fix the check instead.
|
*/

return [

    'enforce' => env('ORGANIZATIONS_ENFORCE', false),

    'owned_modules' => [],

    'platform_abilities' => [],

];
