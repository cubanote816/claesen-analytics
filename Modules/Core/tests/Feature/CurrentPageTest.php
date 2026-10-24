<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Http\Request;
use Modules\Core\Support\CurrentPage;
use Tests\TestCase;

final class CurrentPageTest extends TestCase
{
    private const DASHBOARD = 'filament.admin.pages.dashboard';

    /** Binds a request the way Livewire's update endpoint receives it. */
    private function livewireRequest(mixed $snapshot, bool $livewire = true): void
    {
        $request = Request::create('/livewire-test/update', 'POST', [
            'components' => [['snapshot' => is_string($snapshot) ? $snapshot : json_encode($snapshot)]],
        ]);

        if ($livewire) {
            $request->headers->set('X-Livewire', '1');
        }

        app()->instance('request', $request);
    }

    public function test_a_livewire_request_resolves_the_page_the_component_was_rendered_on(): void
    {
        $this->livewireRequest(['memo' => ['path' => '/', 'method' => 'GET']]);

        $this->assertTrue(CurrentPage::is(self::DASHBOARD));
        $this->assertTrue(CurrentPage::is('nope', self::DASHBOARD));
    }

    public function test_a_livewire_request_from_another_page_does_not_match_the_dashboard(): void
    {
        $this->livewireRequest(['memo' => ['path' => 'users', 'method' => 'GET']]);

        $this->assertFalse(CurrentPage::is(self::DASHBOARD));
    }

    public function test_it_fails_closed_when_the_original_page_cannot_be_resolved(): void
    {
        foreach ([
            ['memo' => ['path' => 'no/such/page', 'method' => 'GET']],
            ['memo' => ['path' => '/', 'method' => 'DELETE']],
            ['memo' => []],
            [],
            'not json',
        ] as $snapshot) {
            $this->livewireRequest($snapshot);

            $this->assertFalse(CurrentPage::is(self::DASHBOARD), 'Unresolvable snapshot: ' . json_encode($snapshot));
        }
    }

    public function test_the_snapshot_is_ignored_on_a_plain_request(): void
    {
        $this->livewireRequest(['memo' => ['path' => '/', 'method' => 'GET']], livewire: false);

        $this->assertFalse(CurrentPage::is(self::DASHBOARD));
    }
}
