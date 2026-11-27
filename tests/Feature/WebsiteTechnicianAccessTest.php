<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Pages\AddressSettingsPage;
use App\Filament\Clusters\Website\Pages\ContactSettingsPage;
use App\Filament\Clusters\Website\Pages\GeneralSettingsPage;
use App\Filament\Clusters\Website\Pages\OpeningHoursSettingsPage;
use App\Filament\Clusters\Website\Pages\PagePublicationReviewPage;
use App\Filament\Clusters\Website\Pages\PrivacyConsentSettingsPage;
use App\Filament\Clusters\Website\Pages\PublicationHistoryPage;
use App\Filament\Clusters\Website\Pages\SocialLinksSettingsPage;
use App\Filament\Clusters\Website\Pages\TranslationReviewPage;
use App\Filament\Clusters\Website\Resources\AnnouncementResource;
use App\Filament\Clusters\Website\Resources\ConsultationRequestResource;
use App\Filament\Clusters\Website\Resources\MediaSlotResource;
use App\Filament\Clusters\Website\Resources\ProjectResource;
use App\Filament\Clusters\Website\WebsiteCluster;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-734: CLAUDE.md rule 5 and the access contract (PanelAccessMatrixTest, CLA-581) say the
 * technician role enters the admin panel only to reach its own work orders. The Website screens
 * only checked that the panel has a site, so a technician could open all of them.
 */
class WebsiteTechnicianAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string, array{class-string}> */
    public static function websiteScreens(): iterable
    {
        foreach ([
            WebsiteCluster::class,
            ProjectResource::class,
            MediaSlotResource::class,
            AnnouncementResource::class,
            ConsultationRequestResource::class,
            GeneralSettingsPage::class,
            ContactSettingsPage::class,
            AddressSettingsPage::class,
            OpeningHoursSettingsPage::class,
            SocialLinksSettingsPage::class,
            PrivacyConsentSettingsPage::class,
            PagePublicationReviewPage::class,
            TranslationReviewPage::class,
            PublicationHistoryPage::class,
        ] as $screen) {
            yield class_basename($screen) => [$screen];
        }
    }

    private function actingAsRole(string $role): void
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create(['organization_id' => Organization::claesenId(), 'is_active' => true]);
        $user->assignRole($role);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @param  class-string  $screen */
    #[DataProvider('websiteScreens')]
    public function test_technician_cannot_open_the_website_screen(string $screen): void
    {
        $this->actingAsRole('technician');

        $this->assertFalse($screen::canAccess(), class_basename($screen).' is open to technician');
    }

    /** Over HTTP too: Filament authorizes the route from canAccess(), so the technician gets a 403. */
    public function test_technician_gets_403_on_website_routes(): void
    {
        $this->withoutVite();
        // MFA enrolment would redirect before the page; it is covered elsewhere.
        config(['core.panel_mfa_enforced' => false]);
        $this->actingAsRole('technician');

        $this->get(GeneralSettingsPage::getUrl(panel: 'admin'))->assertForbidden();
        $this->get(ProjectResource::getUrl(panel: 'admin'))->assertForbidden();
    }

    /** The guard is about technician only: a viewer keeps the access it had. */
    #[DataProvider('websiteScreens')]
    public function test_viewer_still_opens_the_website_screen(string $screen): void
    {
        $this->actingAsRole('viewer');

        $this->assertTrue($screen::canAccess(), class_basename($screen).' lost its viewer access');
    }
}
