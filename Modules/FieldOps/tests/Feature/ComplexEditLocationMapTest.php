<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Modules\Core\Models\User;
use Modules\FieldOps\Models\Complex;
use Modules\Intelligence\Services\GeminiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplexEditLocationMapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_edit_page_renders_location_map_without_lat_lng_inputs(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'FieldOps Test User',
            'email' => 'fieldops-test-user-'.Str::uuid().'@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::findOrFail($userId);
        if (! $user->hasRole('super_admin')) {
            $user->assignRole('super_admin');
        }
        $this->actingAs($user);
        $this->withoutMiddleware();

        $complex = Complex::factory()->create([
            'lat' => 51.1635,
            'lng' => 5.1640,
            'zoom' => 17,
        ]);

        $this->get("/complexes/{$complex->id}/edit")
            ->assertOk()
            ->assertSee('fi-width-full', false)
            ->assertSee('fieldops-complex-location-picker', false)
            ->assertSee('Adjust the complex location')
            ->assertDontSee('Delete', false)
            ->assertDontSee('fi-sc-tabs', false)
            ->assertDontSee('Terrains', false)
            ->assertSee('name', false)
            ->assertSee('zoom', false)
            ->assertDontSee('Latitude')
            ->assertDontSee('Longitude');
    }
}
