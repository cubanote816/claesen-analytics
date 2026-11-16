<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;

abstract class TestCase extends BaseTestCase
{
    /**
     * The admin/super_admin MFA gate (CLA-464) redirects every panel request
     * of a user without a second factor, which would turn every panel test
     * that isn't about MFA into a 302. Tests about the gate itself re-enable
     * it after parent::setUp().
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['core.panel_mfa_enforced' => false]);

        $this->discardMicrosoftGraphCredentials();
    }

    /**
     * CLA-692: the suite must never send real e-mail through Microsoft Graph.
     *
     * SendConsultationEmailJob sends with the named Mail::mailer('microsoft-graph'),
     * which ignores phpunit.xml's MAIL_MAILER=array, and QUEUE_CONNECTION=sync
     * runs it inside the test. Real MICROSOFT_GRAPH_* values from a developer's
     * .env therefore turned every consultation a test created into a real
     * e-mail. Dropping the credentials makes MicrosoftGraphService fail with
     * MailConfigurationException before any HTTP call, while tests that
     * exercise the Graph transport keep setting their own credentials per case
     * (with Http::fake()).
     *
     * MicrosoftGraphService reads the credentials when it is built. When
     * mail.always_to is set, MailingServiceProvider::boot() already resolved
     * the mailer with them, so its transport is rebuilt here (the mailer keeps
     * its alwaysTo). Otherwise the mailer is purged so it resolves lazily from
     * whatever config the test sets.
     */
    private function discardMicrosoftGraphCredentials(): void
    {
        config([
            'mail.mailers.microsoft-graph.client_id' => null,
            'mail.mailers.microsoft-graph.tenant_id' => null,
            'mail.mailers.microsoft-graph.client_secret' => null,
        ]);

        $this->rebuildMicrosoftGraphTransport();
    }

    /**
     * Make the 'microsoft-graph' mailer use the current mail.mailers.microsoft-graph
     * config. Tests that set Graph credentials after a boot-time resolution
     * (mail.always_to set) call this after setting them.
     */
    protected function rebuildMicrosoftGraphTransport(): void
    {
        if (! config('mail.always_to')) {
            Mail::purge('microsoft-graph');

            return;
        }

        Mail::mailer('microsoft-graph')->setSymfonyTransport(
            Mail::createSymfonyTransport(config('mail.mailers.microsoft-graph'))
        );
    }

    /**
     * F1/P3a of the multi-organization program — docs/ai/adr-multi-organization.md.
     *
     * Only consulted by tests using Illuminate\Foundation\Testing\DatabaseTruncation
     * (currently 3 files under Modules/Website/tests/Feature). That trait migrates
     * once (running the phase P1 seed migration that inserts the Claesen
     * organization/site) and then TRUNCATEs every table between tests within the
     * same class — including organizations/sites, which nothing re-seeds. Any
     * model creation depending on Site::claesenId()/Organization::claesenId()
     * (Website's ConsultationRequest/Project since CLA-547, users since CLA-459)
     * would throw on the 2nd+ test in a DatabaseTruncation class without this.
     * RefreshDatabase tests are unaffected — they roll back a transaction per
     * test instead of truncating, so the seed row is never actually deleted.
     *
     * website_project_categories (CLA-468) is the same situation: seeded by
     * a migration (Claesen's three categories), never re-seeded by any test,
     * and read by Modules\Website\Models\ProjectCategory — a
     * DatabaseTruncation class truncating it would silently empty Claesen's
     * whole taxonomy for every test that runs after in the same process.
     *
     * prospects_regions (F4/CLA-478) — the 11 Belgian regions seeded by
     * database/migrations/2026_04_03_190000_create_prospects_regions_table.php
     * — is the same situation again, found the same way: a DatabaseTruncation
     * class truncating it left Modules\Prospects\Services\LeadService::
     * persistContactLead()'s `Region::where('slug', 'brussel')->value('id')`
     * fallback resolving to null for every test running afterward in the
     * same process, breaking the NOT NULL prospects_prospects.region_id
     * column CLA-478 fixed it against in the first place.
     */
    protected array $exceptTables = ['organizations', 'sites', 'website_project_categories', 'prospects_regions'];
}
