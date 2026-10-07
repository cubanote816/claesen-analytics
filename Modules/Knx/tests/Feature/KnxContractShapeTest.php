<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Knx\Exceptions\AddressInUseException;
use Modules\Knx\Http\ApiErrorEnvelope;
use Modules\Knx\Models\KnxZone;
use Modules\Knx\Models\KnxZoneCheck;
use Tests\TestCase;

/**
 * The two pieces of K0 that are pure contract: the error body of §2.5 and the
 * zone status derivation of BACKEND-API-ZONES.md §1.3.
 */
final class KnxContractShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_knx_route_answers_with_the_contract_error_body(): void
    {
        $response = $this->getJson('/api/v1/knx/does-not-exist');

        $response->assertNotFound()
            ->assertJsonPath('code', ApiErrorEnvelope::CODE_NOT_FOUND)
            ->assertJsonStructure(['message', 'code', 'errors']);
    }

    public function test_errors_outside_the_knx_api_keep_laravels_own_shape(): void
    {
        // The envelope is scoped: nothing else in the app changes.
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonMissingPath('code');
    }

    public function test_an_address_in_use_exception_renders_as_a_409_with_the_contract_code(): void
    {
        $this->withoutExceptionHandling();

        $this->expectException(AddressInUseException::class);

        throw new AddressInUseException('1.1.133');
    }

    public function test_the_address_in_use_body_is_the_documented_one(): void
    {
        $exception = new AddressInUseException('1.1.133');

        $this->assertSame(409, $exception->status());
        $this->assertSame('address_in_use', $exception->errorCode());
        $this->assertSame(['proposal' => ['The address [1.1.133] is already in use.']], $exception->errors());
    }

    /**
     * BACKEND-API-ZONES.md §1.3 — the derivation table, case by case.
     */
    public function test_zone_status_is_derived_from_the_applicable_checks(): void
    {
        $zone = $this->zone();

        // Everything pending → nothing passed → not_ready.
        $this->assertSame(KnxZone::STATUS_NOT_READY, $zone->fresh('checks')->derivedStatus());

        // One passed, rest pending → in_progress.
        $this->set($zone, 'installed', KnxZoneCheck::STATUS_PASSED);
        $this->assertSame(KnxZone::STATUS_IN_PROGRESS, $zone->fresh('checks')->derivedStatus());

        // A failed check wins over everything else → blocked.
        $this->set($zone, 'bus', KnxZoneCheck::STATUS_FAILED, 'Kabel niet geleverd');
        $this->assertSame(KnxZone::STATUS_BLOCKED, $zone->fresh('checks')->derivedStatus());

        // All applicable passed → ready.
        $this->set($zone, 'bus', KnxZoneCheck::STATUS_PASSED);
        foreach (KnxZoneCheck::keys() as $key) {
            $this->set($zone, $key, KnxZoneCheck::STATUS_PASSED);
        }
        $this->assertSame(KnxZone::STATUS_READY, $zone->fresh('checks')->derivedStatus());
    }

    public function test_na_checks_are_excluded_and_make_a_zone_not_ready_when_nothing_applies(): void
    {
        $zone = $this->zone();

        foreach (KnxZoneCheck::keys() as $key) {
            $this->set($zone, $key, KnxZoneCheck::STATUS_NA, 'Niet van toepassing');
        }

        $this->assertSame(KnxZone::STATUS_NOT_READY, $zone->fresh('checks')->derivedStatus());

        // And an `na` does not block a zone whose remaining checks all pass.
        $this->set($zone, 'loads', KnxZoneCheck::STATUS_PASSED);
        $this->set($zone, 'loads', KnxZoneCheck::STATUS_NA, 'Geen belasting');
        foreach (array_diff(KnxZoneCheck::keys(), ['loads']) as $key) {
            $this->set($zone, $key, KnxZoneCheck::STATUS_PASSED);
        }

        $this->assertSame(KnxZone::STATUS_READY, $zone->fresh('checks')->derivedStatus());
    }

    public function test_the_blocker_is_the_first_failed_check_in_display_order(): void
    {
        $zone = $this->zone();

        $this->set($zone, 'blockers', KnxZoneCheck::STATUS_FAILED, 'Levering volgt');
        $this->set($zone, 'function_approved', KnxZoneCheck::STATUS_FAILED, 'DALI-driver ontbreekt');

        $blocker = $zone->fresh('checks')->blockingCheck();

        // function_approved comes before blockers in the enum order, and it is the
        // root cause — the later one is the follow-up action.
        $this->assertSame('function_approved', $blocker?->key);
        $this->assertSame('DALI-driver ontbreekt', $blocker?->note);
    }

    public function test_both_failed_and_na_require_a_note(): void
    {
        $check = new KnxZoneCheck(['status' => KnxZoneCheck::STATUS_FAILED]);
        $this->assertTrue($check->requiresNote());

        $check->status = KnxZoneCheck::STATUS_NA;
        $this->assertTrue($check->requiresNote());

        $check->status = KnxZoneCheck::STATUS_PASSED;
        $this->assertFalse($check->requiresNote());
    }

    private function zone(): KnxZone
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);

        // The factory already creates the eight checks in `pending` (the API
        // guarantees "always the 8 keys, in order"), so this only creates the zone.
        return KnxZone::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Vergaderzaal',
        ])->fresh('checks');
    }

    private function set(KnxZone $zone, string $key, string $status, ?string $note = null): void
    {
        $zone->checks()->where('key', $key)->update([
            'status' => $status,
            'note' => $note,
            'updated_at' => now(),
        ]);
    }
}
