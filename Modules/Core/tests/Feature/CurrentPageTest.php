<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Modules\Core\Models\User;
use Modules\Core\Support\CurrentPage;
use Modules\Core\Support\VerifiedLivewirePage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Records what CurrentPage answers while a component hydrates, as a widget's canView() would see it. */
final class CurrentPageProbe extends Component
{
    /** @var list<bool> */
    public static array $seen = [];

    public function hydrate(): void
    {
        self::$seen[] = CurrentPage::is('filament.admin.pages.dashboard');
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

final class CurrentPageTest extends TestCase
{
    use RefreshDatabase;

    private const DASHBOARD = 'filament.admin.pages.dashboard';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        CurrentPageProbe::$seen = [];
        Livewire::component('current-page-probe', CurrentPageProbe::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');
        $this->actingAs($user);
    }

    /** A snapshot rendered by Livewire itself and re-signed, claiming to have been rendered on the given page. */
    private function snapshot(array $memo): array
    {
        $snapshot = Livewire::test(CurrentPageProbe::class)->snapshot;
        unset($snapshot['memo']['path'], $snapshot['memo']['method']);
        $snapshot['memo'] = $memo + $snapshot['memo'];
        unset($snapshot['checksum']);
        $snapshot['checksum'] = Checksum::generate($snapshot);

        return $snapshot;
    }

    /** A real Livewire update request to the real update route. */
    private function update(array $snapshot): void
    {
        $this->withHeaders(['X-Livewire' => '1'])->postJson(EndpointResolver::updatePath(), [
            'components' => [['snapshot' => json_encode($snapshot), 'updates' => (object) [], 'calls' => []]],
        ]);
    }

    public function test_a_livewire_update_resolves_the_page_the_component_was_rendered_on(): void
    {
        $this->update($this->snapshot(['path' => '/', 'method' => 'GET']));

        $this->assertSame([true], CurrentPageProbe::$seen);
    }

    public function test_a_livewire_update_from_another_page_does_not_match_the_dashboard(): void
    {
        $this->update($this->snapshot(['path' => 'users', 'method' => 'GET']));

        $this->assertSame([false], CurrentPageProbe::$seen);
    }

    public function test_it_fails_closed_when_the_verified_page_cannot_be_resolved(): void
    {
        // Control: the pipeline does hydrate the probe and the dashboard is recognised.
        $this->update($this->snapshot(['path' => '/', 'method' => 'GET']));
        $this->assertSame([true], CurrentPageProbe::$seen);
        CurrentPageProbe::$seen = [];

        foreach ([
            ['path' => '', 'method' => 'GET'],
            ['path' => '/', 'method' => ''],
            ['path' => '/', 'method' => 'DELETE'],
            ['path' => 'no/such/page', 'method' => 'GET'],
            ['path' => '/'],
            ['method' => 'GET'],
            [],
        ] as $memo) {
            $this->update($this->snapshot($memo));
        }

        $this->assertNotContains(true, CurrentPageProbe::$seen);
    }

    public function test_the_verified_page_does_not_leak_between_two_update_requests(): void
    {
        $this->update($this->snapshot(['path' => '/', 'method' => 'GET']));
        $this->update($this->snapshot(['path' => 'users', 'method' => 'GET']));
        $this->update($this->snapshot(['path' => '/', 'method' => 'GET']));

        $this->assertSame([true, false, true], CurrentPageProbe::$seen);
    }

    public function test_an_update_request_without_a_verified_snapshot_fails_closed(): void
    {
        $verified = Request::create(EndpointResolver::updatePath(), 'POST');
        app(VerifiedLivewirePage::class)->record($verified, ['memo' => ['path' => '/', 'method' => 'GET']]);

        $update = Request::create(EndpointResolver::updatePath(), 'POST', [
            'components' => [['snapshot' => json_encode(['memo' => ['path' => '/', 'method' => 'GET']])]],
        ]);
        $update->headers->set('X-Livewire', '1');
        $route = (new Route('POST', ltrim(EndpointResolver::updatePath(), '/'), fn () => ''))->name('livewire.update');
        $update->setRouteResolver(fn () => $route);
        app()->instance('request', $update);

        $this->assertFalse(CurrentPage::is(self::DASHBOARD), 'Neither the payload nor another request\'s page counts.');
    }

    public function test_the_snapshot_is_ignored_on_a_plain_request(): void
    {
        $request = Request::create('/', 'GET', [
            'components' => [['snapshot' => json_encode(['memo' => ['path' => '/', 'method' => 'GET']])]],
        ]);
        $request->headers->set('X-Livewire', '1');
        app()->instance('request', $request);

        $this->assertFalse(CurrentPage::is(self::DASHBOARD));
    }
}
