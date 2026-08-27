<?php

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\User;
use Tests\TestCase;

class CustomLuminaireFrameTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/fieldops/luminaire-frame-types/custom', [])->assertUnauthorized();
    }

    public function test_store_creates_custom_frame_type(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/v1/fieldops/luminaire-frame-types/custom', [
                'name'  => 'Custom frame from photo',
                'photo' => UploadedFile::fake()->image('frame.jpg'),
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Custom frame from photo');

        $this->assertDatabaseHas('fo_luminaire_frame_types', [
            'name'                => 'Custom frame from photo',
            'created_by_user_id'  => $user->id,
        ]);

        // CLA-444 — must be a relative path (resolved client-side by
        // resolveApiAssetUrl), never an absolute URL baked from this
        // server's own APP_URL at write time.
        $image = $response->json('data.image');
        $this->assertNotNull($image);
        $this->assertStringStartsWith('/storage/', $image);
    }

    public function test_store_requires_name_and_photo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/fieldops/luminaire-frame-types/custom', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'photo']);
    }
}
