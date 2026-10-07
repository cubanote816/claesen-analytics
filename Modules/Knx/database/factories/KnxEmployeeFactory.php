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
        return [
            'organization_id' => Organization::factory(),
            'user_id' => null,
            'name' => fake()->name(),
            'kind' => KnxEmployee::KIND_FIELD,
            'knx_role' => null,
            'active' => true,
        ];
    }

    /**
     * `initials` is not set in definition() on purpose: it must be derived from
     * the name the caller actually passed (`create(['name' => 'Lien Smet'])`), not
     * from the random one the factory started with — otherwise every seeded person
     * would carry someone else's initials.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (KnxEmployee $employee): void {
            if (blank($employee->initials)) {
                $employee->initials = KnxEmployee::initialsFromName((string) $employee->name);
            }
        });
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
