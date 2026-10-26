<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;
use Tests\TestCase;

/**
 * K7 — site readiness zones (docs/BACKEND-API-ZONES.md §1, a closed contract).
 *
 * What this slice promises is derivation: the status, the blocker and its owner are
 * computed on every read and every write, the front never sends a status, and
 * nothing stores one. The tests therefore check the derivation *table*, the order
 * of the eight checks, and the two rules that make a blocked zone useful (a reason
 * and an owner).
 */
final class KnxZonesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The tenant has to exist before the fixture runs (KnxTenant refuses to
        // invent one), and it is the fixture that creates the office account:
        // seeding *after* creating our own person would delete it, because the
        // demo seed replaces the tenant's rows.
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );

    }

    private function zone(string $name): KnxZone
    {
        return KnxZone::query()->where('name', $name)->sole();
    }

    public function test_the_zone_list_has_the_contract_shape_and_the_eight_checks_in_order(): void
    {
        $zones = $this->getJson('/api/v1/knx/zones')->assertOk()->json();

        $this->assertCount(7, $zones);
        $this->assertSame(
            ['id', 'projectCode', 'projectName', 'name', 'floor', 'status', 'blockingReason', 'blockedBy', 'nextReviewAt', 'checks'],
            array_keys($zones[0]),
        );

        $this->assertSame(
            ['key', 'status', 'updatedAt', 'updatedBy', 'note'],
            array_keys($zones[0]['checks'][0]),
        );

        // The contract promises "always the 8 keys, in order" — the database does
        // not, so this is the assertion that protects the front's rendering.
        $this->assertSame(KnxZoneCheck::keys(), array_column($zones[0]['checks'], 'key'));

        foreach ($zones as $zone) {
            $this->assertSame(KnxZoneCheck::keys(), array_column($zone['checks'], 'key'));
            $this->assertContains($zone['status'], ['not_ready', 'in_progress', 'ready', 'blocked']);
        }
    }

    public function test_the_derived_status_matches_the_derivation_table(): void
    {
        $statuses = collect($this->getJson('/api/v1/knx/zones')->json())
            ->mapWithKeys(fn (array $zone): array => [$zone['name'] => $zone['status']])
            ->all();

        // Everything passed → ready.
        $this->assertSame('ready', $statuses['Inkomhal']);
        // A failed check wins over everything else.
        $this->assertSame('blocked', $statuses['Vergaderzaal']);
        $this->assertSame('blocked', $statuses['Zaal 2.03']);
        $this->assertSame('blocked', $statuses['Bord E10 — Sporthal']);
        // Some passed, none failed → in progress.
        $this->assertSame('in_progress', $statuses['Gang gelijkvloers']);
        $this->assertSame('in_progress', $statuses['Gang 2e']);
    }

    public function test_the_blocker_is_the_first_failed_check_in_display_order(): void
    {
        $zone = collect($this->getJson('/api/v1/knx/zones?project=C1618')->json())
            ->firstWhere('name', 'Vergaderzaal');

        // function_approved comes before blockers in the enum order, and it is the
        // root cause; the later one is the follow-up action.
        $this->assertSame('DALI-driver niet geleverd — kan verlichting niet testen', $zone['blockingReason']);
        $this->assertSame('S. Wouters', $zone['blockedBy']);
    }

    public function test_the_list_filters_by_project_and_shows_a_zone_by_id(): void
    {
        $this->getJson('/api/v1/knx/zones?project=C1618')->assertOk()->assertJsonCount(4);
        $this->getJson('/api/v1/knx/zones?project=239870')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/v1/knx/zones?project=NOPE')->assertOk()->assertJsonCount(0);

        $zone = collect($this->getJson('/api/v1/knx/zones')->json())->first();

        $this->getJson("/api/v1/knx/zones/{$zone['id']}")->assertOk()->assertJsonPath('name', $zone['name']);

        $this->getJson('/api/v1/knx/zones/999999')->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_updating_a_check_answers_the_zone_already_recomputed(): void
    {
        $zone = $this->zone('Vergaderzaal');

        $response = $this->patchJson("/api/v1/knx/zones/{$zone->id}/checks/loads", [
            'status' => 'passed',
        ])->assertOk();

        // Still blocked — the two failed checks remain — but the assertion that
        // matters is that the payload is recomputed, not echoed.
        $this->assertSame('blocked', $response->json('status'));


        $loads = collect($response->json('checks'))->firstWhere('key', 'loads');

        $this->assertSame('passed', $loads['status']);
        $this->assertNull($loads['note']);
        // The office person who made the change owns it now.
        $this->assertSame('L. Smet', $loads['updatedBy']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $loads['updatedAt']);
    }

    public function test_clearing_the_root_cause_moves_the_blocker_to_the_next_failure(): void
    {
        $zone = $this->zone('Vergaderzaal');
        $this->assertSame('function_approved', $zone->blockingCheck()?->key);

        $response = $this->patchJson("/api/v1/knx/zones/{$zone->id}/checks/function_approved", [
            'status' => 'passed',
        ])->assertOk();

        // The follow-up failure becomes the blocker, with its own note and owner.
        $this->assertSame('blocked', $response->json('status'));
        $this->assertSame('Levering driver gepland, elektricien volgt op', $response->json('blockingReason'));

        // …and clearing that one too leaves the zone in progress (one check is
        // still pending), with no blocker at all.
        $response = $this->patchJson("/api/v1/knx/zones/{$zone->id}/checks/blockers", [
            'status' => 'passed',
        ])->assertOk();

        $this->assertSame('in_progress', $response->json('status'));
        $this->assertNull($response->json('blockingReason'));
        $this->assertNull($response->json('blockedBy'));
    }

    public function test_failed_and_na_must_explain_themselves(): void
    {
        $zone = $this->zone('Gang gelijkvloers');

        foreach (['failed', 'na'] as $status) {
            $this->patchJson("/api/v1/knx/zones/{$zone->id}/checks/bus", ['status' => $status])
                ->assertStatus(422)
                ->assertJsonPath('code', 'validation_error')
                ->assertJsonStructure(['errors' => ['note']]);
        }

        // With a reason it goes through, and the reason is what the blocker shows.
        $response = $this->patchJson("/api/v1/knx/zones/{$zone->id}/checks/bus", [
            'status' => 'failed',
            'note' => 'Bus niet aangesloten — wacht op elektricien',
        ])->assertOk();

        $this->assertSame('blocked', $response->json('status'));
        $this->assertSame('Bus niet aangesloten — wacht op elektricien', $response->json('blockingReason'));
        $this->assertSame('L. Smet', $response->json('blockedBy'));
    }

    public function test_an_unknown_check_or_status_is_rejected(): void
    {
        $zone = $this->zone('Inkomhal');

        $this->patchJson("/api/v1/knx/zones/{$zone->id}/checks/banana", ['status' => 'passed'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['key']]);

        $this->patchJson("/api/v1/knx/zones/{$zone->id}/checks/loads", ['status' => 'onzin'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);

        $this->patchJson("/api/v1/knx/zones/{$zone->id}/checks/loads", [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_a_na_check_leaves_the_derivation_alone(): void
    {
        $zone = $this->zone('Inkomhal');
        $this->assertSame('ready', collect($this->getJson("/api/v1/knx/zones/{$zone->id}")->json())->get('status'));

        // Marking one check as not applicable must not break a zone that was ready.
        $response = $this->patchJson("/api/v1/knx/zones/{$zone->id}/checks/loads", [
            'status' => 'na',
            'note' => 'Geen belasting in deze ruimte',
        ])->assertOk();

        $this->assertSame('ready', $response->json('status'));
    }

    public function test_the_zone_endpoints_require_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/zones')->assertUnauthorized();
        $this->getJson('/api/v1/knx/zones/1')->assertUnauthorized();
        $this->patchJson('/api/v1/knx/zones/1/checks/loads', ['status' => 'passed'])->assertUnauthorized();
    }
}
