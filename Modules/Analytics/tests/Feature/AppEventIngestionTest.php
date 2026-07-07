<?php

declare(strict_types=1);

namespace Modules\Analytics\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Analytics\Models\AppEvent;
use Modules\Core\Models\User;
use Tests\TestCase;

class AppEventIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'event_name' => 'session_started',
            'app' => 'backoffice',
            'session_id' => (string) Str::uuid(),
        ], $overrides);
    }

    // ── SCHEMA ────────────────────────────────────────────────────────────

    public function test_app_events_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('app_events'));
        $this->assertTrue(Schema::hasColumns('app_events', [
            'id', 'event_name', 'app', 'user_id', 'session_id', 'employee_id',
            'entity_type', 'entity_id', 'role_snapshot', 'properties',
            'duration_ms', 'occurred_at', 'created_at',
        ]));
    }

    // ── INGESTION ─────────────────────────────────────────────────────────

    public function test_store_records_event_for_authenticated_user(): void
    {
        $user = User::factory()->create(['employee_id' => 'EMP-001']);

        $this->actingAs($user)
            ->postJson('/api/v1/events', $this->validPayload())
            ->assertStatus(202)
            ->assertJsonPath('status', 'accepted');

        $this->assertDatabaseHas('app_events', [
            'event_name' => 'session_started',
            'app' => 'backoffice',
            'user_id' => $user->id,
            'employee_id' => 'EMP-001',
        ]);
    }

    public function test_store_allows_anonymous_event_without_session(): void
    {
        $this->postJson('/api/v1/events', $this->validPayload(['event_name' => 'error_encountered']))
            ->assertStatus(202);

        $this->assertDatabaseHas('app_events', [
            'event_name' => 'error_encountered',
            'user_id' => null,
            'employee_id' => null,
        ]);
    }

    public function test_store_persists_arbitrary_properties_as_json(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/events', $this->validPayload([
            'event_name' => 'action_completed',
            'entity_type' => 'FoMaintenanceRecord',
            'entity_id' => '123',
            'duration_ms' => 4200,
            'properties' => ['step' => 'submit', 'client_reported' => true],
        ]))->assertStatus(202);

        $event = AppEvent::query()->latest('id')->firstOrFail();

        $this->assertSame('FoMaintenanceRecord', $event->entity_type);
        $this->assertSame('123', $event->entity_id);
        $this->assertSame(4200, $event->duration_ms);
        $this->assertSame(['step' => 'submit', 'client_reported' => true], $event->properties);
    }

    public function test_store_requires_event_name_app_and_session_id(): void
    {
        $this->postJson('/api/v1/events', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_name', 'app', 'session_id']);
    }

    public function test_store_rejects_event_name_outside_the_catalog(): void
    {
        $this->postJson('/api/v1/events', $this->validPayload(['event_name' => 'made_up_event']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_name']);
    }

    public function test_store_rejects_app_outside_the_catalog(): void
    {
        $this->postJson('/api/v1/events', $this->validPayload(['app' => 'some_other_app']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['app']);
    }

    public function test_store_rejects_oversized_properties_payload(): void
    {
        $this->postJson('/api/v1/events', $this->validPayload([
            'properties' => ['blob' => str_repeat('x', 6000)],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['properties']);
    }

    // ── RATE LIMITING ─────────────────────────────────────────────────────

    public function test_events_route_is_throttled(): void
    {
        $middleware = collect(\Illuminate\Support\Facades\Route::getRoutes()->getByName('api.events.store')->gatherMiddleware());

        $this->assertTrue($middleware->contains(fn ($m) => str_starts_with($m, 'throttle:')));
    }
}
