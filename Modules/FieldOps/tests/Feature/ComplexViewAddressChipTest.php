<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\Complex;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplexViewAddressChipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_view_page_shows_address_in_header_and_not_google_map_thumb(): void
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
            'name' => 'Sporthal de Alk',
            'street' => 'Koutermanstraat 2',
            'zipcode' => 'BE3570',
            'city' => 'Alken',
            'lat' => 50.875535,
            'lng' => 5.311814,
            'zoom' => 17,
        ]);

        $this->get("/complexes/{$complex->id}")
            ->assertOk()
            ->assertSee('Sporthal de Alk')
            ->assertSee('Koutermanstraat 2, BE3570, Alken')
            ->assertDontSee('https://www.google.com/maps/search/?api=1&query=');
    }
}
