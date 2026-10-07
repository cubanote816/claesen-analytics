<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Knx\Support\KnxTenant;
use RuntimeException;
use Tests\TestCase;

/**
 * The KNX domain belongs to Electro Bertels and to nobody else (CLA-604 K0).
 */
final class KnxTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_configured_organization(): void
    {
        $organization = Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->assertSame('electro-bertels', KnxTenant::slug());
        $this->assertSame($organization->id, KnxTenant::organizationId());
    }

    public function test_it_fails_loudly_instead_of_falling_back_to_claesen(): void
    {
        // Claesen exists (seeded by migration) but Bertels does not: writing KNX
        // rows now would orphan them under the wrong tenant, so this must throw.
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->assertSame('electro-bertels', KnxTenant::slug());

        Organization::query()->where('slug', 'electro-bertels')->delete();

        $this->expectException(RuntimeException::class);
        KnxTenant::organization();
    }
}
