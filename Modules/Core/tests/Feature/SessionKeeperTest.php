<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Livewire\Livewire;
use Modules\Core\Livewire\SessionKeeper;
use Tests\TestCase;

class SessionKeeperTest extends TestCase
{
    public function test_session_keeper_uses_configured_session_lifetime_by_default(): void
    {
        config(['session.lifetime' => 37]);
        config(['session.warning_threshold' => 123]);

        Livewire::test(SessionKeeper::class)
            ->assertSet('lifetime', 37 * 60)
            ->assertSet('warningThreshold', 123);
    }

    public function test_session_keeper_accepts_explicit_override_values(): void
    {
        Livewire::test(SessionKeeper::class, [
            'lifetime' => 1800,
            'warningThreshold' => 120,
        ])
            ->assertSet('lifetime', 1800)
            ->assertSet('warningThreshold', 120);
    }
}
