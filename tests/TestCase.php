<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

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
        // Before parent::setUp(): the base class runs the test traits, and
        // RefreshDatabase is one of them. Checking afterwards would be checking
        // after the development database had already been migrated and wiped.
        $this->refuseToTouchTheDevelopmentDatabase();

        parent::setUp();

        config(['core.panel_mfa_enforced' => false]);
    }

    /**
     * Fail closed when the suite is not pointed at a test database.
     *
     * `phpunit.xml` pins `DB_DATABASE=claesen_analytics_web_testing`, and Laravel's
     * Dotenv does not overwrite an env var PHPUnit already set, so the suite normally
     * resolves the testing database no matter what `.env` says. The risk is the path
     * where that chain breaks: a different phpunit config, a `.env.testing` added by
     * mistake, or `DB_DATABASE` unset. Then the suite resolves `.env` —
     * `electrobertels_knx`, the shared development database — and `RefreshDatabase`
     * MIGRATES AND WIPES it before the first assertion runs.
     *
     * A test run that is not clearly aimed at a `*_testing` database is a mistake, so
     * it stops with an explanation instead of destroying development data. This is the
     * same fail-closed rule KnxDemoSeeder applies to production.
     */
    private function refuseToTouchTheDevelopmentDatabase(): void
    {
        $database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');

        if (is_string($database) && str_ends_with($database, '_testing')) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to run tests against [%s]: the suite must point at a database whose name ends in "_testing" '
            .'(phpunit.xml pins it). Running RefreshDatabase here would wipe development data.',
            is_string($database) && $database !== '' ? $database : '(unset)',
        ));
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
