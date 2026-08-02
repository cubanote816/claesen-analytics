<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminairePosition;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Services\LuminaireReplacementService;
use Modules\FieldOps\Services\LuminaireRemovalService;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LuminaireReplacementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private LuminaireFrame $frame;

    private LuminaireSubgroup $originalSubgroup;

    private LuminaireType $originalType;

    private LuminaireSubgroup $replacementSubgroup;

    private LuminaireType $replacementType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GeminiService::class, fn ($mock) => $mock
            ->shouldReceive('translateAndDetect')
            ->andReturn(['translations' => [], 'detected_locale' => 'nl']));

        $this->user = User::factory()->create();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->user->assignRole('super_admin');
        $this->frame = LuminaireFrame::factory()->create();
        $this->originalSubgroup = LuminaireSubgroup::factory()->create();
        $this->originalType = LuminaireType::factory()->create([
            'luminaire_subgroup_id' => $this->originalSubgroup->id,
        ]);
        $this->replacementSubgroup = LuminaireSubgroup::factory()->create();
        $this->replacementType = LuminaireType::factory()->create([
            'luminaire_subgroup_id' => $this->replacementSubgroup->id,
        ]);

        FoMaintenanceType::query()->updateOrCreate(
            ['code' => FoMaintenanceType::CODE_REPLACEMENT],
            ['name' => ['nl' => 'Vervanging', 'en' => 'Replacement']],
        );
        FoMaintenanceType::query()->updateOrCreate(
            ['code' => FoMaintenanceType::CODE_REMOVAL],
            ['name' => ['nl' => 'Verwijdering', 'en' => 'Removal']],
        );
    }

    public function test_creating_luminaire_creates_stable_position(): void
    {
        $luminaire = $this->originalLuminaire();

        $this->assertNotNull($luminaire->luminaire_position_id);
        $this->assertSame($luminaire->luminaire_position_id, $luminaire->active_position_id);
        $this->assertDatabaseHas('fo_luminaire_positions', [
            'id' => $luminaire->luminaire_position_id,
            'luminaire_frame_id' => $this->frame->id,
            'frame_position' => 3,
            'frame_x' => 0.2713,
            'frame_y' => 0.6842,
            'position_version' => 7,
            'position_source' => 'frontend',
        ]);
    }

    public function test_replacement_api_requires_authentication(): void
    {
        $previous = $this->originalLuminaire();

        $this->postJson("/api/v1/fieldops/luminaires/{$previous->id}/replacement", $this->replacementPayload())
            ->assertUnauthorized();

        $this->assertSame(1, Luminaire::count());
        $this->assertSame(0, FoMaintenanceRecord::count());
    }

    public function test_backoffice_replacement_forbids_non_admin_user(): void
    {
        $previous = $this->originalLuminaire();
        $standardUser = User::factory()->create();

        $this->actingAs($standardUser)
            ->postJson("/fieldops/luminaire-frame-editor/luminaires/{$previous->id}/replacement", $this->replacementPayload())
            ->assertForbidden();

        $this->assertSame(1, Luminaire::count());
        $this->assertSame(0, FoMaintenanceRecord::count());
    }

    public function test_replacement_preserves_exact_position_and_creates_history(): void
    {
        $previous = $this->originalLuminaire();
        $positionBefore = $previous->position->only([
            'luminaire_frame_id',
            'frame_position',
            'frame_x',
            'frame_y',
            'scale_x',
            'scale_y',
            'position_version',
            'position_source',
            'position_verified_by_user_id',
            'position_verified_at',
        ]);
        $positionBefore['position_verified_at'] = $positionBefore['position_verified_at']?->toISOString();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaires/{$previous->id}/replacement", $this->replacementPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.previous_luminaire.id', $previous->id)
            ->assertJsonPath('data.current_luminaire.serial_number', 'SN-REPLACEMENT-001')
            ->assertJsonPath('data.current_luminaire.luminaire_position_id', $previous->luminaire_position_id)
            ->assertJsonPath('data.current_luminaire.frame_x', 0.2713)
            ->assertJsonPath('data.current_luminaire.frame_y', 0.6842)
            ->assertJsonPath('data.current_luminaire.scale_x', 1.75)
            ->assertJsonPath('data.current_luminaire.position_version', 7)
            ->assertJsonPath('data.maintenance_record.replacement.from_luminaire_id', $previous->id);

        $currentId = (int) $response->json('data.current_luminaire.id');
        $current = Luminaire::findOrFail($currentId);
        $previous->refresh();

        $this->assertNotNull($previous->removed_at);
        $this->assertNull($previous->active_position_id);
        $this->assertSame($currentId, $previous->replaced_by_luminaire_id);
        $this->assertSame($previous->luminaire_position_id, $current->luminaire_position_id);
        $this->assertSame($previous->luminaire_position_id, $current->active_position_id);
        $positionAfter = $current->position->only(array_keys($positionBefore));
        $positionAfter['position_verified_at'] = $positionAfter['position_verified_at']?->toISOString();
        $this->assertSame($positionBefore, $positionAfter);
        $this->assertSame(1, LuminairePosition::count());
        $this->assertSame([$currentId], $this->frame->fresh()->luminaires()->pluck('id')->all());

        $maintenance = FoMaintenanceRecord::where('replacement_from_luminaire_id', $previous->id)->firstOrFail();
        $this->assertSame($currentId, $maintenance->replacement_to_luminaire_id);
        $this->assertSame($previous->luminaire_position_id, $maintenance->luminaire_position_id);
        $this->assertSame('Damaged beyond repair', $maintenance->replacement_reason);

        $this->actingAs($this->user)
            ->getJson("/api/v1/fieldops/luminaires/{$currentId}/maintenance-records")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.replacement.from_luminaire_id', $previous->id)
            ->assertJsonPath('data.0.replacement.to_luminaire_id', $currentId);
    }

    public function test_stale_position_version_rejects_replacement_without_changes(): void
    {
        $previous = $this->originalLuminaire();

        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaires/{$previous->id}/replacement", $this->replacementPayload([
                'position_version' => 6,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('position_version');

        $previous->refresh();
        $this->assertNull($previous->removed_at);
        $this->assertSame($previous->luminaire_position_id, $previous->active_position_id);
        $this->assertDatabaseMissing('fo_luminaires', ['serial_number' => 'SN-REPLACEMENT-001']);
        $this->assertSame(0, FoMaintenanceRecord::count());
    }

    public function test_duplicate_serial_rejects_replacement_without_closing_current_installation(): void
    {
        $previous = $this->originalLuminaire();
        Luminaire::factory()->create(['serial_number' => 'SN-REPLACEMENT-001']);

        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaires/{$previous->id}/replacement", $this->replacementPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('serial_number')
            ->assertJsonPath(
                'errors.serial_number.0',
                __('fieldops::resource.luminaires.replacement.serial_taken'),
            );

        $previous->refresh();
        $this->assertNull($previous->removed_at);
        $this->assertSame($previous->luminaire_position_id, $previous->active_position_id);
        $this->assertSame(0, FoMaintenanceRecord::count());
    }

    public function test_service_rejects_reusing_current_serial_and_rolls_back(): void
    {
        $previous = $this->originalLuminaire();

        try {
            app(LuminaireReplacementService::class)->replace(
                $previous,
                $this->replacementPayload(['serial_number' => $previous->serial_number]),
                $this->user->id,
            );
            $this->fail('Expected duplicate serial validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('fieldops::resource.luminaires.replacement.serial_taken'),
                $exception->errors()['serial_number'][0] ?? null,
            );
        }

        $previous->refresh();
        $this->assertNull($previous->removed_at);
        $this->assertNull($previous->replaced_by_luminaire_id);
        $this->assertSame($previous->luminaire_position_id, $previous->active_position_id);
        $this->assertSame(1, Luminaire::count());
        $this->assertSame(0, FoMaintenanceRecord::count());
    }

    public function test_already_replaced_installation_cannot_be_replaced_again(): void
    {
        $previous = $this->originalLuminaire();

        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaires/{$previous->id}/replacement", $this->replacementPayload())
            ->assertCreated();

        $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaires/{$previous->id}/replacement", $this->replacementPayload([
                'serial_number' => 'SN-REPLACEMENT-002',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('luminaire');

        $this->assertSame(2, Luminaire::count());
        $this->assertSame(1, FoMaintenanceRecord::count());
    }

    public function test_position_history_includes_maintenance_before_replacement_and_after(): void
    {
        $previous = $this->originalLuminaire();
        $preventiveType = FoMaintenanceType::factory()->preventive()->create();
        FoMaintenanceRecord::factory()->forMaintainable($previous)->create([
            'fo_maintenance_type_id' => $preventiveType->id,
            'maintenance_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/fieldops/luminaires/{$previous->id}/replacement", $this->replacementPayload([
                'maintenance_at' => now()->subDay()->toIso8601String(),
            ]))
            ->assertCreated();

        $current = Luminaire::findOrFail((int) $response->json('data.current_luminaire.id'));
        $correctiveType = FoMaintenanceType::factory()->corrective()->create();
        FoMaintenanceRecord::factory()->forMaintainable($current)->create([
            'fo_maintenance_type_id' => $correctiveType->id,
            'maintenance_at' => now(),
        ]);

        $this->assertSame(1, $current->maintenanceRecords()->count());
        $this->assertSame(3, $current->position->maintenanceRecords()->count());

        $this->withHeader('Accept-Language', 'en-US')
            ->get(FoMaintenanceRecordResource::getUrl('index', ['luminaire' => $current->id]))
            ->assertOk()
            ->assertSee('Preventive')
            ->assertSee('Replacement')
            ->assertSee('Corrective');

        $positionHistoryUrl = FoMaintenanceRecordResource::getUrl('index', [
            'luminaire' => $current->id,
            'position' => $current->luminaire_position_id,
        ]);

        $this->withHeader('Accept-Language', 'en-US')
            ->get("/luminaire-frames/{$this->frame->id}")
            ->assertOk()
            ->assertSee(__('fieldops::resource.luminaires.actions.view_history'))
            ->assertSee($positionHistoryUrl);
    }

    public function test_removal_preserves_the_installation_and_records_vacant_position_history(): void
    {
        $luminaire = $this->originalLuminaire();

        $result = app(LuminaireRemovalService::class)->remove($luminaire, [
            'removal_reason' => 'Frame is being decommissioned.',
            'maintenance_at' => now()->toIso8601String(),
            'position_version' => 7,
            'notes' => 'No replacement is planned.',
        ], $this->user->id);

        $luminaire->refresh();

        $this->assertNull($luminaire->deleted_at);
        $this->assertNotNull($luminaire->removed_at);
        $this->assertNull($luminaire->active_position_id);
        $this->assertSame('Frame is being decommissioned.', $luminaire->removal_reason);
        $this->assertSame($luminaire->luminaire_position_id, $result['maintenance']->luminaire_position_id);
        $this->assertSame(FoMaintenanceType::CODE_REMOVAL, $result['maintenance']->maintenanceType->code);
        $this->assertTrue($result['maintenance']->details['removal']);
        $this->assertSame(1, $luminaire->position->maintenanceRecords()->count());
        $this->assertSame(0, $this->frame->fresh()->luminaires()->count());
    }

    public function test_new_installation_reuses_a_vacant_position_without_losing_history(): void
    {
        $retired = $this->originalLuminaire();
        $positionId = $retired->luminaire_position_id;

        app(LuminaireRemovalService::class)->remove($retired, [
            'removal_reason' => 'Replacement will be installed later.',
            'maintenance_at' => now()->toIso8601String(),
            'position_version' => 7,
        ], $this->user->id);

        $response = $this->actingAs($this->user)->postJson('/fieldops/luminaire-frame-editor/luminaires', [
            'luminaire_frame_id' => $this->frame->id,
            'luminaire_position_id' => $positionId,
            'luminaire_type_id' => $this->replacementType->id,
            'luminaire_subgroup_id' => $this->replacementSubgroup->id,
            'serial_number' => 'SN-REINSTALLED-001',
        ])->assertCreated();

        $installed = Luminaire::findOrFail((int) $response->json('data.id'));

        $this->assertSame($positionId, $installed->luminaire_position_id);
        $this->assertSame($positionId, $installed->active_position_id);
        $this->assertSame(1, LuminairePosition::count());
        $this->assertSame([$installed->id], $this->frame->fresh()->luminaires()->pluck('id')->all());
        $this->assertSame(1, $installed->position->maintenanceRecords()->count());
    }

    public function test_new_installation_cannot_reuse_an_occupied_position(): void
    {
        $current = $this->originalLuminaire();

        $this->actingAs($this->user)->postJson('/fieldops/luminaire-frame-editor/luminaires', [
            'luminaire_frame_id' => $this->frame->id,
            'luminaire_position_id' => $current->luminaire_position_id,
            'luminaire_type_id' => $this->replacementType->id,
            'luminaire_subgroup_id' => $this->replacementSubgroup->id,
            'serial_number' => 'SN-DUPLICATE-POSITION-001',
        ])->assertUnprocessable()->assertJsonValidationErrors('luminaire_position_id');

        $this->assertSame(1, Luminaire::count());
    }

    /** @param array<string, mixed> $overrides */
    private function replacementPayload(array $overrides = []): array
    {
        return array_merge([
            'luminaire_type_id' => $this->replacementType->id,
            'luminaire_subgroup_id' => $this->replacementSubgroup->id,
            'serial_number' => 'SN-REPLACEMENT-001',
            'replacement_reason' => 'Damaged beyond repair',
            'root_cause' => 'Driver and housing failure',
            'solution_applied' => 'Installed replacement luminaire',
            'maintenance_at' => now()->toIso8601String(),
            'position_version' => 7,
        ], $overrides);
    }

    private function originalLuminaire(): Luminaire
    {
        return Luminaire::factory()->create([
            'luminaire_frame_id' => $this->frame->id,
            'luminaire_type_id' => $this->originalType->id,
            'luminaire_subgroup_id' => $this->originalSubgroup->id,
            'frame_position' => 3,
            'serial_number' => 'SN-ORIGINAL-001',
            'frame_x' => 0.2713,
            'frame_y' => 0.6842,
            'scale_x' => 1.75,
            'scale_y' => 1.75,
            'position_version' => 7,
            'position_source' => 'frontend',
            'position_verified_by_user_id' => $this->user->id,
            'position_verified_at' => now()->subHour()->startOfSecond(),
        ])->load('position');
    }
}
