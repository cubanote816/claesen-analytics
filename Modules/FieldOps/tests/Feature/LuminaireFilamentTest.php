<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LuminaireFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_luminaire_pages_render_with_maintenance_history(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create(['frame_x' => 40, 'frame_y' => 60, 'scale_x' => 1.2]);
        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create([
            'problem_reported_at' => now()->subDays(3),
            'problem_solved_at' => now()->subDays(2),
        ]);
        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create([
            'problem_reported_at' => now()->subHours(5),
            'problem_solved_at' => null,
        ]);

        $this->get('/luminaires')->assertOk();
        $this->get("/luminaires/{$luminaire->id}")->assertOk();
        $this->get("/luminaires/{$luminaire->id}/edit")->assertOk();
    }

    public function test_luminaire_without_maintenance_renders(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create();

        $this->get("/luminaires/{$luminaire->id}")->assertOk();
    }
}
