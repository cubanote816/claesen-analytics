<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Prospects\Filament\Resources\SyncHistoryResource\Pages\ViewSyncHistory;
use Modules\Prospects\Models\SyncHistory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncHistoryLogLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    private function history(): SyncHistory
    {
        return SyncHistory::create([
            'command' => 'prospects:sync-rbfa-graphql',
            'status' => 'failed',
            'records_count' => 0,
            'started_at' => now()->subMinutes(3),
            'finished_at' => now(),
            'logs' => [
                ['timestamp' => now()->toIso8601String(), 'message' => 'Starting synchronization...', 'type' => 'info', 'icon' => '🚀'],
                ['timestamp' => now()->toIso8601String(), 'message' => 'Error: All RBFA discovery series failed.', 'type' => 'error', 'icon' => '❌'],
            ],
        ]);
    }

    // CLA-665: the Type badge of each log card must get two of the ten grid columns,
    // otherwise "error" is wider than its column and gets clipped.
    public function test_log_type_badge_gets_two_grid_columns(): void
    {
        $html = Livewire::actingAs($this->superAdmin())
            ->test(ViewSyncHistory::class, ['record' => $this->history()->getKey()])
            ->assertOk()
            ->assertSee('Error: All RBFA discovery series failed.')
            ->html();

        foreach (['info', 'error'] as $badge) {
            $this->assertSame(2, $this->largeSpanOfColumnContaining($html, "/>\\s*{$badge}\\s*</"), "{$badge} badge column");
        }
        $this->assertSame(7, $this->largeSpanOfColumnContaining($html, '/Error: All RBFA discovery series failed\./'));
    }

    // The grid column that wraps the match is the last one opened before it.
    private function largeSpanOfColumnContaining(string $html, string $pattern): int
    {
        $this->assertSame(1, preg_match($pattern, $html, $match, PREG_OFFSET_CAPTURE), "{$pattern} not rendered");
        $position = $match[0][1];
        preg_match_all('/--col-span-lg: span (\d+)/', substr($html, 0, $position), $spans);
        $this->assertNotEmpty($spans[1], "no grid column before {$pattern}");

        return (int) end($spans[1]);
    }
}
