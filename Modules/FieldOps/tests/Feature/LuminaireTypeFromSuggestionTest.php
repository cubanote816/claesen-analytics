<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;
use Tests\TestCase;

/**
 * CLA-389 — a technician confirming an out-of-catalog vision suggestion (brand/model
 * the AI recognized but that isn't in fo_luminaire_types yet) creates the catalog
 * entry immediately, flagged source=ai_suggestion/verified_by_user_id=null so a
 * super_admin can review it later from Filament. Deduplicated so the same real
 * product suggested by two different technicians doesn't create two rows.
 */
class LuminaireTypeFromSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/fieldops/luminaire-types/from-suggestion', [])->assertUnauthorized();
    }

    public function test_store_requires_brand_and_model_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/fieldops/luminaire-types/from-suggestion', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['brand', 'model_name']);
    }

    public function test_store_creates_a_new_subgroup_and_type_when_the_brand_is_unknown(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/fieldops/luminaire-types/from-suggestion', [
                'brand' => 'Schréder',
                'model_name' => 'OMNISTAR LED XL',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'OMNISTAR LED XL');

        $this->assertDatabaseHas('fo_luminaire_subgroups', [
            'brand' => 'Schréder',
            'source' => 'ai_suggestion',
            'created_by_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('fo_luminaire_types', [
            'name' => 'OMNISTAR LED XL',
            'luminaire_subgroup_id' => $response->json('data.luminaire_subgroup_id'),
            'source' => 'ai_suggestion',
            'created_by_user_id' => $user->id,
        ]);

        $subgroup = LuminaireSubgroup::where('brand', 'Schréder')->firstOrFail();
        $this->assertNull($subgroup->verified_by_user_id);
        $type = LuminaireType::where('name', 'OMNISTAR LED XL')->firstOrFail();
        $this->assertNull($type->verified_by_user_id);
    }

    public function test_store_reuses_an_existing_subgroup_case_insensitively(): void
    {
        $user = User::factory()->create();
        $existing = LuminaireSubgroup::factory()->create(['brand' => 'Thorn', 'source' => 'manual']);

        $this->actingAs($user)
            ->postJson('/api/v1/fieldops/luminaire-types/from-suggestion', [
                'brand' => 'THORN',
                'model_name' => 'Altis Sport LED XL',
            ])
            ->assertCreated();

        $this->assertSame(1, LuminaireSubgroup::where('brand', 'Thorn')->count());
        $this->assertDatabaseHas('fo_luminaire_types', [
            'name' => 'Altis Sport LED XL',
            'luminaire_subgroup_id' => $existing->id,
        ]);
    }

    public function test_store_deduplicates_the_same_model_suggested_twice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/fieldops/luminaire-types/from-suggestion', [
            'brand' => 'Musco',
            'model_name' => 'TLC for LED XL',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/v1/fieldops/luminaire-types/from-suggestion', [
            'brand' => 'Musco',
            'model_name' => 'tlc for led xl',
        ])->assertCreated();

        $this->assertSame(1, LuminaireType::where('name', 'TLC for LED XL')->count());
    }
}
