<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Pages\PagePublicationReviewPage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Website\Models\PagePublication;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The screen that writes the publication state.
 *
 * Four things are worth pinning here, and only the first is about rendering:
 * the table never shows another site's rows, approving records **who** did it
 * (the decision that made these columns exist), publishing is not offered for
 * something nobody approved, and retiring really does take the page out of the
 * manifest the build reads — which is the entire reason the screen exists.
 */
final class PagePublicationReviewPageTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        // The site the bertels panel serves. `Site::forPanel()` resolves it from
        // config('organizations.panel_sites'), which is also what scopes the table.
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->site = Site::factory()->create([
            'organization_id' => $organization->id,
            'key' => 'electro-bertels',
            'locales' => ['nl', 'fr'],
            'default_locale' => 'nl',
            'status' => Site::STATUS_ACTIVE,
        ]);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        Filament::setCurrentPanel(Filament::getPanel('bertels'));

        // Whoever acts is the responsible party; in this slice that is still a
        // super_admin, because opening the panel to the client is its own slice.
        $this->client = User::factory()->create(['is_active' => true]);
        $this->client->assignRole('super_admin');
        $this->actingAs($this->client);
    }

    private function row(
        string $page = 'home',
        string $locale = 'fr',
        string $status = PagePublication::STATUS_MACHINE,
        ?Site $site = null,
    ): PagePublication {
        return PagePublication::query()->create([
            'site_id' => ($site ?? $this->site)->getKey(),
            'page' => $page,
            'locale' => $locale,
            'status' => $status,
        ]);
    }

    public function test_it_only_lists_rows_of_the_panels_own_site(): void
    {
        $mine = $this->row();

        $otherOrganization = Organization::factory()->create(['slug' => 'otro-sitio']);
        $foreignSite = Site::factory()->create([
            'organization_id' => $otherOrganization->id,
            'key' => 'otro-sitio',
        ]);
        $foreign = $this->row(page: 'contact', site: $foreignSite);

        Livewire::test(PagePublicationReviewPage::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_approving_records_who_did_it(): void
    {
        $row = $this->row();

        Livewire::test(PagePublicationReviewPage::class)
            ->callTableAction('approve', $row);

        $row->refresh();

        $this->assertSame(PagePublication::STATUS_REVIEWED, $row->status);
        // The decision: the client who approves is the responsible party, and
        // nothing assigns this by hand.
        $this->assertSame($this->client->getKey(), $row->reviewed_by_user_id);
        $this->assertNotNull($row->reviewed_at);

        // And it is auditable beyond the row itself.
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'page_publication_review',
            'causer_id' => $this->client->getKey(),
        ]);
    }

    public function test_publishing_is_only_offered_after_approval(): void
    {
        $draft = $this->row();
        $approved = $this->row(page: 'contact', status: PagePublication::STATUS_REVIEWED);

        Livewire::test(PagePublicationReviewPage::class)
            // Publishing what nobody read is the mistake this table prevents, so
            // the action is not merely refused — it is not offered.
            ->assertTableActionHidden('publish', $draft)
            ->assertTableActionVisible('publish', $approved)
            ->callTableAction('publish', $approved);

        $this->assertSame(PagePublication::STATUS_PUBLISHED, $approved->refresh()->status);
        $this->assertSame($this->client->getKey(), $approved->reviewed_by_user_id);
    }

    public function test_retiring_takes_the_page_out_of_the_manifest(): void
    {
        $row = $this->row(status: PagePublication::STATUS_PUBLISHED);

        $this->assertSame(['home' => ['fr']], PagePublication::manifestFor($this->site));

        Livewire::test(PagePublicationReviewPage::class)
            ->callTableAction('retire', $row);

        $row->refresh();

        // Back to `reviewed`, not to `machine`: the build sees the same thing
        // either way, and keeping the approval recorded is truer than pretending
        // nobody ever read it.
        $this->assertSame(PagePublication::STATUS_REVIEWED, $row->status);

        // The point of the whole screen: what the build will index just changed.
        $this->assertSame([], PagePublication::manifestFor($this->site));
    }
}
