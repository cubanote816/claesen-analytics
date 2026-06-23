<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;
use Tests\TestCase;

class LuminaireCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private LuminaireFrame $frame;
    private LuminaireSubgroup $subgroup;
    private LuminaireType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user     = User::factory()->create();
        $this->subgroup = LuminaireSubgroup::factory()->create();
        $this->type     = LuminaireType::factory()->create(['luminaire_subgroup_id' => $this->subgroup->id]);
        $this->frame    = LuminaireFrame::factory()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
            'serial_number'         => 'SN-TEST-001',
        ], $overrides);
    }

    // ── AUTH ──────────────────────────────────────────────────────────────

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/fieldops/luminaires', [])->assertUnauthorized();
    }

    // ── STORE ─────────────────────────────────────────────────────────────

    public function test_store_creates_luminaire_201(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaires', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.serial_number', 'SN-TEST-001');

        $this->assertDatabaseHas('fo_luminaires', [
            'serial_number'      => 'SN-TEST-001',
            'created_by_user_id' => $this->user->id,
        ]);
    }

    public function test_store_auto_assigns_frame_position(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaires', $this->validPayload())
            ->assertCreated();

        $this->assertNotNull($response->json('data.frame_position'));
        $this->assertEquals(1, $response->json('data.frame_position'));
    }

    public function test_store_respects_explicit_frame_position(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaires', $this->validPayload(['frame_position' => 5]))
            ->assertCreated()
            ->assertJsonPath('data.frame_position', 5);
    }

    public function test_store_auto_increments_frame_position_for_second_luminaire(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaires', $this->validPayload(['serial_number' => 'SN-001']))
            ->assertCreated()
            ->assertJsonPath('data.frame_position', 1);

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaires', $this->validPayload(['serial_number' => 'SN-002']))
            ->assertCreated()
            ->assertJsonPath('data.frame_position', 2);
    }

    public function test_store_fails_with_duplicate_serial_number(): void
    {
        Luminaire::factory()->create(array_merge(
            ['luminaire_frame_id' => $this->frame->id, 'luminaire_type_id' => $this->type->id, 'luminaire_subgroup_id' => $this->subgroup->id],
            ['serial_number' => 'SN-DUP-001']
        ));

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaires', $this->validPayload(['serial_number' => 'SN-DUP-001']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);
    }

    public function test_store_fails_with_mismatched_type_and_subgroup(): void
    {
        $otherSubgroup = LuminaireSubgroup::factory()->create();

        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaires', $this->validPayload([
                'luminaire_subgroup_id' => $otherSubgroup->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['luminaire_type_id']);
    }

    public function test_store_required_fields(): void
    {
        $this->actingAs($this->user)
            ->postJson('/api/v1/fieldops/luminaires', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['luminaire_frame_id', 'luminaire_type_id', 'luminaire_subgroup_id', 'serial_number']);
    }

    // ── SHOW ──────────────────────────────────────────────────────────────

    public function test_show_returns_luminaire(): void
    {
        $luminaire = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/fieldops/luminaires/{$luminaire->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $luminaire->id)
            ->assertJsonStructure(['data' => ['id', 'serial_number', 'frame_position', 'frame_x', 'frame_y', 'info']]);
    }

    public function test_show_404_for_deleted(): void
    {
        $luminaire = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
        ]);
        $luminaire->delete();

        $this->actingAs($this->user)
            ->getJson("/api/v1/fieldops/luminaires/{$luminaire->id}")
            ->assertNotFound();
    }

    // ── UPDATE ────────────────────────────────────────────────────────────

    public function test_update_serial_number_same_value_passes(): void
    {
        $luminaire = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
            'serial_number'         => 'SN-SAME',
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaires/{$luminaire->id}", [
                'serial_number' => 'SN-SAME',
            ])
            ->assertOk();
    }

    public function test_update_serial_number_taken_by_another_fails(): void
    {
        $other = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
            'serial_number'         => 'SN-OTHER',
        ]);
        $luminaire = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
            'serial_number'         => 'SN-MINE',
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaires/{$luminaire->id}", [
                'serial_number' => 'SN-OTHER',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number']);
    }

    public function test_update_info_merges_locales(): void
    {
        $luminaire = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
            'info'                  => ['nl' => 'Origineel NL', 'en' => 'Original EN'],
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaires/{$luminaire->id}", [
                'info' => ['nl' => 'Bijgewerkt NL'],
            ])
            ->assertOk();

        $fresh = $luminaire->fresh();
        $this->assertEquals('Bijgewerkt NL', $fresh->getTranslation('info', 'nl'));
        $this->assertEquals('Original EN',  $fresh->getTranslation('info', 'en'));
    }

    public function test_update_mismatch_type_subgroup_fails(): void
    {
        $luminaire     = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
        ]);
        $otherSubgroup = LuminaireSubgroup::factory()->create();

        $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaires/{$luminaire->id}", [
                'luminaire_subgroup_id' => $otherSubgroup->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['luminaire_type_id']);
    }

    public function test_update_move_to_different_frame_auto_assigns_position(): void
    {
        $targetFrame = LuminaireFrame::factory()->create();
        // Seed target frame with one luminaire at position 1
        Luminaire::factory()->create([
            'luminaire_frame_id'    => $targetFrame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
            'frame_position'        => 1,
        ]);

        $luminaire = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaires/{$luminaire->id}", [
                'luminaire_frame_id' => $targetFrame->id,
                // no frame_position sent — should auto-assign max+1 = 2
            ])
            ->assertOk();

        $this->assertEquals(2, $response->json('data.frame_position'));
        $this->assertDatabaseHas('fo_luminaires', [
            'id'                 => $luminaire->id,
            'luminaire_frame_id' => $targetFrame->id,
            'frame_position'     => 2,
        ]);
    }

    public function test_update_move_to_different_frame_with_explicit_position(): void
    {
        $targetFrame = LuminaireFrame::factory()->create();
        $luminaire   = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaires/{$luminaire->id}", [
                'luminaire_frame_id' => $targetFrame->id,
                'frame_position'     => 7,
            ])
            ->assertOk();

        $this->assertEquals(7, $response->json('data.frame_position'));
    }

    public function test_update_same_frame_position_not_recalculated(): void
    {
        $luminaire = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
            'frame_position'        => 3,
        ]);

        // Update within same frame without frame_position — should stay at 3
        $this->actingAs($this->user)
            ->patchJson("/api/v1/fieldops/luminaires/{$luminaire->id}", [
                'luminaire_frame_id' => $this->frame->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.frame_position', 3);
    }

    // ── DESTROY ───────────────────────────────────────────────────────────

    public function test_destroy_returns_204(): void
    {
        $luminaire = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/fieldops/luminaires/{$luminaire->id}")
            ->assertNoContent();
    }

    public function test_destroy_soft_deletes_and_get_returns_404(): void
    {
        $luminaire = Luminaire::factory()->create([
            'luminaire_frame_id'    => $this->frame->id,
            'luminaire_type_id'     => $this->type->id,
            'luminaire_subgroup_id' => $this->subgroup->id,
        ]);
        $this->actingAs($this->user)
            ->deleteJson("/api/v1/fieldops/luminaires/{$luminaire->id}");

        $this->assertSoftDeleted('fo_luminaires', ['id' => $luminaire->id]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/fieldops/luminaires/{$luminaire->id}")
            ->assertNotFound();
    }
}
