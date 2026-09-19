<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
     */
    protected array $exceptTables = ['organizations', 'sites', 'website_project_categories'];
}
