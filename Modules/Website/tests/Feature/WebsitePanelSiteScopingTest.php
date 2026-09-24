<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use App\Filament\Clusters\Website\Resources\AnnouncementResource\Pages\ListAnnouncements;
use App\Filament\Clusters\Website\Resources\ConsultationRequestResource\Pages\ListConsultationRequests;
use App\Filament\Clusters\Website\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Clusters\Website\Resources\ProjectResource\Pages\ListProjects;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Website\Models\Announcement;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\Project;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-598 (T3/T4): each panel lists, opens and creates only its own site's
 * Website content — independent of ORGANIZATIONS_ENFORCE, which stays off here.
 */
final class WebsitePanelSiteScopingTest extends TestCase
{
    use RefreshDatabase;

    private Site $bertels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);
        $this->bertels = Site::factory()->create(['organization_id' => $organization->id, 'key' => 'electro-bertels']);

        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    private function panel(string $id): void
    {
        Filament::setCurrentPanel(Filament::getPanel($id));
    }

    public function test_the_admin_panel_lists_only_claesen_content(): void
    {
        $claesen = Project::factory()->create(['site_id' => Site::claesenId()]);
        $other = Project::factory()->create(['site_id' => $this->bertels->id, 'slug' => 'bertels-only']);
        $lead = ConsultationRequest::factory()->create(['site_id' => Site::claesenId()]);
        $otherLead = ConsultationRequest::factory()->create(['site_id' => $this->bertels->id]);
        $note = Announcement::factory()->create(['site_id' => Site::claesenId()]);
        $otherNote = Announcement::factory()->create(['site_id' => $this->bertels->id]);

        $this->panel('admin');

        Livewire::test(ListProjects::class)->assertCanSeeTableRecords([$claesen])->assertCanNotSeeTableRecords([$other]);
        Livewire::test(ListConsultationRequests::class)->assertCanSeeTableRecords([$lead])->assertCanNotSeeTableRecords([$otherLead]);
        Livewire::test(ListAnnouncements::class)->assertCanSeeTableRecords([$note])->assertCanNotSeeTableRecords([$otherNote]);
    }

    public function test_the_bertels_panel_lists_only_bertels_content(): void
    {
        $claesen = Project::factory()->create(['site_id' => Site::claesenId()]);
        $own = Project::factory()->create(['site_id' => $this->bertels->id]);
        $lead = ConsultationRequest::factory()->create(['site_id' => Site::claesenId()]);
        $ownLead = ConsultationRequest::factory()->create(['site_id' => $this->bertels->id]);
        $note = Announcement::factory()->create(['site_id' => Site::claesenId()]);
        $ownNote = Announcement::factory()->create(['site_id' => $this->bertels->id]);

        $this->panel('bertels');

        Livewire::test(ListProjects::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$claesen]);
        Livewire::test(ListConsultationRequests::class)->assertCanSeeTableRecords([$ownLead])->assertCanNotSeeTableRecords([$lead]);
        Livewire::test(ListAnnouncements::class)->assertCanSeeTableRecords([$ownNote])->assertCanNotSeeTableRecords([$note]);
    }

    public function test_a_record_of_the_other_site_is_not_reachable_by_direct_url(): void
    {
        $claesen = Project::factory()->create(['site_id' => Site::claesenId()]);
        $bertels = Project::factory()->create(['site_id' => $this->bertels->id]);

        $this->get("/bertels/website/projects/{$claesen->id}/edit")->assertNotFound();
        $this->get("/website/projects/{$bertels->id}/edit")->assertNotFound();
    }

    public function test_creating_in_each_panel_stamps_that_panels_site(): void
    {
        foreach (['admin' => Site::claesenId(), 'bertels' => $this->bertels->id] as $panel => $siteId) {
            $this->panel($panel);

            $page = app(CreateProject::class);
            $method = new \ReflectionMethod($page, 'mutateFormDataBeforeCreate');
            $method->setAccessible(true);

            $this->assertSame($siteId, $method->invoke($page, [])['site_id'], "panel [{$panel}]");
        }
    }

    public function test_the_bertels_panel_hides_the_cluster_until_its_site_row_exists(): void
    {
        $this->bertels->delete();

        foreach (['/bertels/website/projects', '/bertels/website/site-settings'] as $url) {
            $this->assertContains($this->get($url)->getStatusCode(), [403, 404], $url);
        }

        $this->get('/website/projects')->assertOk();
    }
}
