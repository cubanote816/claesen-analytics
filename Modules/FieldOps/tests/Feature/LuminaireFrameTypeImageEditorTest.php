<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LuminaireFrameTypeImageEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_upload_endpoint_requires_auth(): void
    {
        $this->postJson(route('fieldops.catalogs.luminaire-frame-types.image-store'), [
            'file' => UploadedFile::fake()->image('frame.png'),
        ])->assertUnauthorized();
    }

    public function test_upload_endpoint_stores_image_and_returns_public_url(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)
            ->post(route('fieldops.catalogs.luminaire-frame-types.image-store'), [
                'file' => UploadedFile::fake()->image('frame.png'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data.url'));
        $this->assertCount(1, Storage::disk('public')->allFiles('luminaire-frame-types'));
    }
}
