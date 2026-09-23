<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Models\Organization;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\Core\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = \Modules\Core\Models\User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'password_set_at' => now(),
            'is_active' => true,
            'remember_token' => Str::random(10),
            // F1/P2 (docs/ai/adr-multi-organization.md): every user belongs to
            // an organization. Defaults to the seeded Claesen row so the
            // hundreds of existing User::factory() call sites keep working
            // without changes; falls back to creating it only for isolated
            // tests that skip the phase P1 seed migration.
            'organization_id' => fn () => Organization::query()->where('slug', Organization::CLAESEN_SLUG)->value('id')
                ?? Organization::factory()->create(['slug' => Organization::CLAESEN_SLUG])->id,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
