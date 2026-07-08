<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminaireType;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_luminaire_frame_type_content_grid_renders_with_and_without_image(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        LuminaireFrameType::factory()->create(['name' => 'Traverse 1', 'image' => null]);
        LuminaireFrameType::factory()->create(['name' => 'Custom frame', 'image' => 'https://example.test/frame.jpg']);

        $this->get('/catalogs/luminaire-frame-types')->assertOk();
    }

    public function test_luminaire_type_content_grid_renders_with_and_without_image(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        LuminaireType::factory()->create(['name' => 'BVP525', 'image' => null]);
        LuminaireType::factory()->create(['name' => 'Custom type', 'image' => 'https://example.test/type.jpg']);

        $this->get('/catalogs/luminaire-types')->assertOk();
    }
}
