<?php

namespace Modules\Knx\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Organization;
use Modules\Knx\Models\KnxEmployee;

/**
 * @extends Factory<KnxEmployee>
 */
class KnxEmployeeFactory extends Factory
{
    protected $model = KnxEmployee::class;

    public function definition(): array
    {
        $name = fake()->name();

        return [
            'organization_id' => Organization::factory(),
            'user_id' => null,
            'name' => $name,
            'initials' => KnxEmployee::initialsFromName($name),
            'kind' => KnxEmployee::KIND_FIELD,
            'knx_role' => null,
            'active' => true,
        ];
    }

    /** Office staff — the people who appear as project lead, author, updater… */
    public function office(): static
    {
        return $this->state(fn (): array => [
            'kind' => KnxEmployee::KIND_OFFICE,
            'knx_role' => KnxEmployee::ROLES[0],
        ]);
    }

    /** Field technician — the people the planning assigns work to. */
    public function field(): static
    {
        return $this->state(fn (): array => [
            'kind' => KnxEmployee::KIND_FIELD,
            'knx_role' => 'technician',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['active' => false]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (): array => ['organization_id' => $organization->id]);
    }
}
