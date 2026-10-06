<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Pages\SiteSettingsPage;
use App\Filament\Clusters\Website\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Clusters\Website\Widgets\PublicationStatusWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Website\Jobs\TriggerStaticSiteRebuildJob;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * WU3 of odd/tasks/publish-to-live-website.md — the publication state and the publish action
 * belong on the screens where content is edited, not only on the Projects list.
 *
 * Deliberate publishing (2026-10-06) means saving no longer publishes, so this header is how
 * an editor tells a saved change from a live one, and the action is the only way to make one
 * live. Both screens take them from one trait; this file exercises the shared piece on each
 * surface instead of trusting that the trait was applied.
 */
final class SitePublicationHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function actingAsSuperAdmin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $this->actingAs($user);
    }

    private function enablePublishPipeline(): void
    {
        config([
            'static_site.enabled'        => true,
            'static_site.webhook_url'    => 'http://fake-frontend:9000/rebuild',
            'static_site.webhook_secret' => 'test-secret',
        ]);
    }

    public function test_the_settings_screen_shows_the_state_and_the_publish_action(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SiteSettingsPage::class)
            ->assertOk()
            ->assertActionExists('publish_now')
            // assertSeeLivewire and not assertSee: the widget is a Livewire child, so its text
            // is not in the page's initial HTML. Asserting the label with assertSee fails even
            // on the Projects list, which has carried this widget all along.
            ->assertSeeLivewire(PublicationStatusWidget::class);
    }

    /**
     * And it publishes: with force, because a person who pressed the button has already
     * decided and should not have to wait out a debounce window to learn whether the click
     * registered.
     */
    public function test_publishing_from_the_settings_screen_requests_a_forced_rebuild(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipeline();

        Queue::fake();

        Livewire::test(SiteSettingsPage::class)->callAction('publish_now');

        Queue::assertPushed(
            TriggerStaticSiteRebuildJob::class,
            fn (TriggerStaticSiteRebuildJob $job): bool => $job->force === true,
        );
    }

    /**
     * Moving the publish action into a shared definition took it out of the Projects list,
     * which also needs its create action — a trait method cannot be extended, so that pair is
     * written out in the class. This is what keeps that from silently losing one of them.
     */
    public function test_the_projects_list_keeps_both_actions_and_the_state(): void
    {
        $this->actingAsSuperAdmin();
        $this->enablePublishPipeline();

        Livewire::test(ListProjects::class)
            ->assertOk()
            ->assertActionExists('publish_now')
            ->assertActionExists('create')
            ->assertSeeLivewire(PublicationStatusWidget::class);
    }
}
