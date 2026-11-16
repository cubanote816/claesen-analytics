<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

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
     * MicrosoftGraphService reads the credentials when it is built, and
     * MailingServiceProvider::boot() may already have resolved the mailer
     * (when mail.always_to is set) with the environment's credentials. So the
     * mailer gets a transport that builds the real one from the current
     * config on every send: the environment's credentials are gone, a test's
     * own credentials always apply, and the mailer keeps its alwaysTo however
     * MAIL_TO_ADDRESS arrived (process environment or .env).
     */
    private function discardMicrosoftGraphCredentials(): void
    {
        config([
            'mail.mailers.microsoft-graph.client_id' => null,
            'mail.mailers.microsoft-graph.tenant_id' => null,
            'mail.mailers.microsoft-graph.client_secret' => null,
        ]);

        Mail::mailer('microsoft-graph')->setSymfonyTransport(new class implements TransportInterface
        {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                return Mail::createSymfonyTransport(config('mail.mailers.microsoft-graph'))
                    ->send($message, $envelope);
            }

            public function __toString(): string
            {
                return 'microsoft-graph (built from config on each send, CLA-692)';
            }
        });
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
