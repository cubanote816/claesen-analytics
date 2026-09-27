<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\SyncHistory;
use RuntimeException;
use Tests\TestCase;

class SyncLbfaClubsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_club_is_logged_and_records_count_uses_successful_writes(): void
    {
        Http::fake([
            'https://www.lbfa.be/fr/liste-des-clubs' => Http::response(<<<'HTML'
                <table>
                    <tr>
                        <td><strong>Failing LBFA</strong></td>
                        <td><p>Rue Test 1, 1000 Bruxelles</p><p>Extra</p></td>
                    </tr>
                    <tr>
                        <td><strong>Surviving LBFA</strong></td>
                        <td><p>Rue Test 2, 4000 Liège</p><p>Extra</p></td>
                    </tr>
                </table>
                HTML),
        ]);

        $attempt = 0;
        Prospect::creating(function () use (&$attempt): void {
            $attempt++;
            if ($attempt === 1) {
                throw new RuntimeException('write failed');
            }
        });

        $this->artisan('prospects:sync-lbfa-clubs')->assertExitCode(0);

        $history = SyncHistory::where('command', 'prospects:sync-lbfa-clubs')->firstOrFail();
        $messages = implode("\n", array_column($history->logs ?? [], 'message'));

        $this->assertStringContainsString('Failing LBFA', $messages);
        $this->assertStringContainsString('write failed', $messages);
        $this->assertSame(1, $history->records_count);
        $this->assertDatabaseHas('prospects_prospects', [
            'external_id' => 'FR-LBFA-surviving-lbfa',
        ]);
    }
}
