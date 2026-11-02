<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * "Guides & Procedures": the internal ETS6 Project Archive guide, in both panels.
 *
 * Design sources: the Electro Bertels mockup is used as-is; the Claesen one carries the same
 * content in the Claesen identity (DESIGN.md). Neither guide names a company in its footer; the network paths in the body do.
 *
 * Access is super_admin only, in BOTH panels (user decision): the bertels panel also admits the
 * client organisation's users, and an internal procedure is not theirs to read.
 *
 * Real HTTP GETs: the sidebar and the page shell are panel layout, which only a full request
 * renders. One panel per test method, because Filament's NavigationManager is a scoped binding
 * the test client reuses across ->get() calls (see BertelsSidebarTest).
 */
final class GuidesAndProceduresTest extends TestCase
{
    use RefreshDatabase;

    /** The admin (Claesen) panel is mounted at the root: ->path('').  */
    private const ADMIN_GUIDE = '/guides/ets6-project-archive';

    private const BERTELS_GUIDE = '/bertels/guides/ets6-project-archive';

    private Organization $bertels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        foreach (['super_admin', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->bertels = Organization::factory()->create(['slug' => 'electro-bertels']);
        Site::factory()->create(['organization_id' => $this->bertels->id, 'key' => 'electro-bertels']);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['is_active' => true, ...$attributes]);
        $user->assignRole($role);

        return $user;
    }

    /** The guide's own markup, without the panel shell around it (brand name, title, sidebar). */
    private function guideMarkup(string $html): string
    {
        $start = strpos($html, 'data-guide="ets6-project-archive"');
        $this->assertNotFalse($start, 'The guide markup is missing from the page.');

        $end = strpos($html, '</footer>', $start);
        $this->assertNotFalse($end, 'The guide footer is missing from the page.');

        return substr($html, $start, $end - $start);
    }

    /** The guide's footer element only. */
    private function footerMarkup(string $html): string
    {
        $start = strpos($html, '<footer class="ets6-guide__footer"');
        $this->assertNotFalse($start, 'The guide footer is missing from the page.');

        $end = strpos($html, '</footer>', $start);
        $this->assertNotFalse($end, 'The guide footer is not closed.');

        return substr($html, $start, $end - $start);
    }

    public function test_a_super_admin_reads_the_guide_in_the_claesen_skin(): void
    {
        $response = $this->actingAs($this->userWithRole('super_admin'))
            ->get(self::ADMIN_GUIDE)
            ->assertSuccessful()
            ->assertSee('Working together on KNX projects with the ETS6 Project Archive')
            ->assertSee('ets6-guide--claesen', escape: false)
            ->assertDontSee('ets6-guide--bertels', escape: false);

        $guide = $this->guideMarkup($response->getContent());

        foreach (['One-time setup on every PC', 'Adding a project to the archive', 'Daily workflow', 'When two copies have diverged', 'Good practices', 'Based on ETS6 6.4'] as $text) {
            $this->assertStringContainsString($text, $guide);
        }
    }

    public function test_a_super_admin_reads_the_guide_in_the_bertels_skin(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get(self::BERTELS_GUIDE)
            ->assertSuccessful()
            ->assertSee('Working together on KNX projects with the ETS6 Project Archive')
            ->assertSee('ets6-guide--bertels', escape: false)
            ->assertDontSee('ets6-guide--claesen', escape: false);
    }

    public function test_the_claesen_guide_footer_names_no_company(): void
    {
        $response = $this->actingAs($this->userWithRole('super_admin'))->get(self::ADMIN_GUIDE);

        $footer = $this->footerMarkup($response->getContent());

        $this->assertStringNotContainsStringIgnoringCase('Claesen', $footer);
        $this->assertStringNotContainsStringIgnoringCase('Bertels', $footer);
    }

    public function test_the_bertels_guide_footer_names_no_company(): void
    {
        $response = $this->actingAs($this->userWithRole('super_admin'))->get(self::BERTELS_GUIDE);

        $footer = $this->footerMarkup($response->getContent());

        $this->assertStringNotContainsStringIgnoringCase('Claesen', $footer);
        $this->assertStringNotContainsStringIgnoringCase('Bertels', $footer);
    }

    public function test_the_bertels_guide_lists_its_network_folders(): void
    {
        $response = $this->actingAs($this->userWithRole('super_admin'))->get(self::BERTELS_GUIDE);

        $guide = $this->guideMarkup($response->getContent());

        $this->assertStringContainsString('\\\\RSBertels\\BertelsGedeeld\\Projecten\\Automation\\_ETS6_ProjectArchive', $guide);
        $this->assertStringContainsString('\\\\RSBertels\\BertelsGedeeld\\Projecten\\Automation\\_ETS6_Claesen_ProjectArchive', $guide);
        $this->assertSame(2, substr_count($guide, 'data-copy-path'));
        $this->assertStringNotContainsString('ClaesenGedeeld', $guide);
    }

    public function test_the_claesen_guide_lists_its_network_folder(): void
    {
        $response = $this->actingAs($this->userWithRole('super_admin'))->get(self::ADMIN_GUIDE);

        $guide = $this->guideMarkup($response->getContent());

        $this->assertStringContainsString('\\\\RSBertels\\ClaesenGedeeld\\Projecten\\Automation\\_ETS6_ProjectArchive', $guide);
        $this->assertSame(1, substr_count($guide, 'data-copy-path'));
        $this->assertStringNotContainsString('BertelsGedeeld', $guide);
    }

    /** @var list<string> */
    private const AUDITED_PHRASES = [
        'Keep local project',
        'Lock the archive copy for others',
        'Restore overwrites your local copy',
        'one person at a time',
        'Compare Versions',
        'Import Copy',
        'Number of automatically retained project versions',
        'The local project store is single-user',
        'Keep every PC on the same ETS6 version',
    ];

    /** @var list<string> */
    private const REMOVED_PHRASES = ['Keep project locally', 'who changed what', 'Save as'];

    public function test_the_bertels_guide_carries_the_audited_ets6_wording(): void
    {
        $response = $this->actingAs($this->userWithRole('super_admin'))->get(self::BERTELS_GUIDE)->assertSuccessful();

        foreach ([...self::AUDITED_PHRASES, 'Linked Folders → Add Link', 'add it once with'] as $text) {
            $response->assertSee($text);
        }

        foreach (self::REMOVED_PHRASES as $text) {
            $response->assertDontSee($text);
        }
    }

    public function test_the_claesen_guide_carries_the_audited_ets6_wording_without_a_linked_folder_step(): void
    {
        $response = $this->actingAs($this->userWithRole('super_admin'))->get(self::ADMIN_GUIDE)->assertSuccessful();

        foreach (self::AUDITED_PHRASES as $text) {
            $response->assertSee($text);
        }

        foreach ([...self::REMOVED_PHRASES, 'add it once with'] as $text) {
            $response->assertDontSee($text);
        }
    }

    public function test_the_claesen_sidebar_offers_the_guide_to_a_super_admin(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/')
            ->assertSuccessful()
            ->assertSeeInOrder([__('navigation.groups.guides_procedures'), 'ETS6 Project Archive'])
            ->assertSee(self::ADMIN_GUIDE, escape: false);
    }

    public function test_the_bertels_sidebar_offers_the_guide_to_a_super_admin(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))
            ->get('/bertels')
            ->assertSuccessful()
            ->assertSeeInOrder([__('navigation.groups.guides_procedures'), 'ETS6 Project Archive'])
            ->assertSee(self::BERTELS_GUIDE, escape: false);
    }

    public function test_a_claesen_user_who_is_not_super_admin_is_refused_and_not_offered_the_guide(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)->get(self::ADMIN_GUIDE)->assertForbidden();

        $this->actingAs($viewer)
            ->get('/')
            ->assertSuccessful()
            ->assertDontSee(__('navigation.groups.guides_procedures'))
            ->assertDontSee(self::ADMIN_GUIDE, escape: false);
    }

    public function test_a_bertels_client_user_is_refused_and_not_offered_the_guide(): void
    {
        $client = $this->userWithRole('viewer', ['organization_id' => $this->bertels->id]);

        $this->actingAs($client)->get(self::BERTELS_GUIDE)->assertForbidden();

        $this->actingAs($client)
            ->get('/bertels')
            ->assertSuccessful()
            ->assertDontSee(__('navigation.groups.guides_procedures'))
            ->assertDontSee(self::BERTELS_GUIDE, escape: false);
    }

    public function test_the_group_is_labelled_in_every_locale(): void
    {
        foreach (['nl', 'en', 'fr', 'de'] as $locale) {
            $label = __('navigation.groups.guides_procedures', [], $locale);

            $this->assertNotSame('navigation.groups.guides_procedures', $label, "Missing label for {$locale}.");
        }
    }
}
