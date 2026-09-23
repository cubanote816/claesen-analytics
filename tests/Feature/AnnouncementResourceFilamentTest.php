<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Resources\AnnouncementResource\Pages\CreateAnnouncement;
use App\Filament\Clusters\Website\Resources\AnnouncementResource\Pages\EditAnnouncement;
use App\Filament\Clusters\Website\Resources\AnnouncementResource\Pages\ListAnnouncements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Website\Models\Announcement;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A real CRUD list (unlike SiteSettingsPage's fixed slots) — see
 * AnnouncementResource's own docblock. Hardcoded to Claesen (CreateAnnouncement::
 * mutateFormDataBeforeCreate()) until Bertels has its own panel/resources.
 */
final class AnnouncementResourceFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_list_page_renders_existing_announcements(): void
    {
        $this->actingAsSuperAdmin();
        Announcement::factory()->published()->create(['message' => ['en' => 'Hello there']]);

        Livewire::test(ListAnnouncements::class)
            ->assertOk()
            ->assertCanSeeTableRecords(Announcement::all());
    }

    public function test_creating_an_announcement_defaults_it_to_the_claesen_site(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(CreateAnnouncement::class)
            ->fillForm([
                'message' => 'Closed for the holidays',
                'status' => Announcement::STATUS_DRAFT,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $announcement = Announcement::query()->firstOrFail();
        $this->assertSame(Site::claesenId(), $announcement->site_id);
        $this->assertSame('Closed for the holidays', $announcement->message);
        $this->assertSame(Announcement::STATUS_DRAFT, $announcement->status);
    }

    public function test_editing_an_announcement_hydrates_the_current_locale_translation_correctly(): void
    {
        $this->actingAsSuperAdmin();
        $announcement = Announcement::factory()->create([
            'message' => ['nl' => 'Nederlandse tekst', 'en' => 'English text'],
        ]);

        app()->setLocale('nl');

        Livewire::test(EditAnnouncement::class, ['record' => $announcement->getKey()])
            ->assertFormSet(['message' => 'Nederlandse tekst']);
    }

    public function test_updating_status_to_published_persists(): void
    {
        $this->actingAsSuperAdmin();
        $announcement = Announcement::factory()->create(['status' => Announcement::STATUS_DRAFT]);

        Livewire::test(EditAnnouncement::class, ['record' => $announcement->getKey()])
            ->fillForm(['status' => Announcement::STATUS_PUBLISHED])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(Announcement::STATUS_PUBLISHED, $announcement->fresh()->status);
    }

    private function actingAsSuperAdmin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $this->actingAs($user);
    }
}
